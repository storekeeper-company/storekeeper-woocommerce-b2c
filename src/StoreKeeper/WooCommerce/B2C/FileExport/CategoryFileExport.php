<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\YoastSeo;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WP_Term;

class CategoryFileExport extends AbstractCSVFileExport
{
    public function getType(): string
    {
        return FileExportTypeHelper::CATEGORY;
    }

    public function getPaths(): array
    {
        return [
            'title' => 'Title',
            'translatable.lang' => 'Language',
            'is_main_lang' => 'Is main language',
            'slug' => 'Slug',
            'summary' => 'Summary',
            'icon' => 'Icon',
            'description' => 'Description',
            'seo_title' => 'SEO title',
            'seo_keywords' => 'SEO keywords',
            'seo_description' => 'SEO description',
            'image_url' => 'Image url',
            'published' => 'Published',
            'order' => 'Order',
            'parent_slug' => 'Parent slug',
            'protected' => 'Protected',
        ];
    }

    /**
     * Runs the export, once done it returns the path to the exported file.
     */
    public function runExport(string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $arguments = [
            'taxonomy' => 'product_cat',
            'orderby' => 'ID',
            'order' => 'ASC',
            'hide_empty' => false,
        ];

        $map = $this->keyValueMapArray(
            get_categories($arguments),
            function ($item) {
                return $item->term_id;
            });

        $categories = $this->sortCategoriesByParent($map);

        $total = count($categories);
        $index = 0;
        /* @var WP_Term $item */
        foreach ($categories as $item) {
            // Default category for when a product does not has a category.
            if ('uncategorized' !== $item->slug) {
                $lineData = [];

                $lineData = $this->exportSEO($lineData, $item);

                $lineData['title'] = $item->name;
                $lineData['translatable.lang'] = $exportLanguage;
                $lineData['slug'] = $item->slug;
                $lineData['description'] = $item->description;
                $lineData['image_url'] = $this->getThumbnailUrl($item->term_id);
                $lineData['published'] = true;
                $lineData['parent_slug'] = $this->getParentSlug($map, $item->parent);

                $this->writeLineData($lineData);
                ++$index;

                if (0 === $index % 10) {
                    $this->reportUpdate($total, $index, 'Exported 10 categories');
                }
            }
        }

        return $this->filePath;
    }

    private function exportSEO(array $lineData, WP_Term $category): array
    {
        $lineData['seo_title'] = $category->name;
        $lineData['seo_description'] = $category->description;

        if (YoastSeo::isSelectedHandler()) {
            $lineData['seo_title'] = YoastSeo::getCategoryTitle($category->term_id);
            $lineData['seo_description'] = YoastSeo::getCategoryDescription($category->term_id);
        }

        return $lineData;
    }

    /**
     * Gets the categories thumbnail url.
     */
    private function getThumbnailUrl(int $id): string
    {
        $thumbnail_id = get_term_meta($id, 'thumbnail_id', true);
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);

        return $thumbnail_url ? $thumbnail_url : '';
    }

    /**
     * Searches for the parent in the map and returns its slug.
     *
     * @return string
     */
    private function getParentSlug(array $map, int $parentId = 0)
    {
        $parentSlug = '';

        if (array_key_exists($parentId, $map)) {
            $parent = $map[$parentId];
            if (0 !== $parentId && !empty($parent)) {
                $parentSlug = $parent->slug;
            }
        }

        return $parentSlug;
    }

    private function sortCategoriesByParent(array $mappedCategories): array
    {
        $categories = [];

        foreach ($mappedCategories as $term) {
            $parentCategory = $this->getParentCategory($mappedCategories, $term->parent);
            if (null !== $parentCategory) {
                $parentCategoryPosition = $this->getCategoryPosition($categories, $parentCategory->term_id);
                if (null === $parentCategoryPosition) {
                    $parentCategoryPosition = array_push($categories, $parentCategory);
                }

                array_splice($categories, $parentCategoryPosition - 1, 0, [$term]);
            } else {
                $categories[] = $term;
            }
        }

        return array_reverse($categories);
    }

    private function getCategoryPosition($categories, $termId)
    {
        $position = null;
        foreach ($categories as $index => $category) {
            if ($termId === $category->term_id) {
                $position = $index + 1;
                break;
            }
        }

        return $position;
    }

    /**
     * Searches for the parent in the map.
     *
     * @return WP_Term|null
     */
    private function getParentCategory(array $map, int $parentId = 0)
    {
        $parentCategory = null;

        if (array_key_exists($parentId, $map)) {
            $parent = $map[$parentId];
            if (0 !== $parentId && !empty($parent)) {
                $parentCategory = $parent;
            }
        }

        return $parentCategory;
    }
}
