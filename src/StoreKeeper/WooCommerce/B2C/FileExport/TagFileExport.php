<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Interfaces\TagExportInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class TagFileExport extends AbstractCSVFileExport implements TagExportInterface
{
    private $shouldSkipEmptyTag = false;

    public function setShouldSkipEmptyTag(bool $shouldSkipEmptyTag): void
    {
        $this->shouldSkipEmptyTag = $shouldSkipEmptyTag;
    }

    public function getType(): string
    {
        return FileExportTypeHelper::TAG;
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
    public function runExport(?string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $arguments = [
            'taxonomy' => 'product_tag',
            'orderby' => 'ID',
            'order' => 'ASC',
            'hide_empty' => $this->shouldSkipEmptyTag,
        ];

        $map = $this->keyValueMapArray(
            get_tags($arguments),
            function ($item) {
                return $item->term_id;
            }
        );

        $total = count($map);
        $index = 0;
        foreach ($map as $id => $item) {
            $lineData = [];
            $lineData['title'] = $item->name;
            $lineData['translatable.lang'] = $exportLanguage;
            $lineData['slug'] = $item->slug;
            $lineData['description'] = $item->description;
            $lineData['image_url'] = $this->getThumbnailUrl($id);
            $lineData['published'] = true;
            $lineData['parent_slug'] = $this->getParentSlug($map, $item->parent);

            $this->writeLineData($lineData);
            ++$index;

            if (0 === $index % 10) {
                $this->reportUpdate($total, $index, 'Exported 10 categories');
            }
        }

        return $this->filePath;
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
}
