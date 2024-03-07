<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class CategoryFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return CategoryFileExport::class;
    }

    public function testCategoryExportTest()
    {
        $expectedParentCategory = [
            'name' => 'Parent Category',
            'description' => 'Parent description',
            'seo_title' => uniqid('title'),
            'seo_keywords' => uniqid('keywords'),
            'seo_description' => uniqid('description'),
        ];
        $parentCategory = $this->createCategory($expectedParentCategory);

        $expectedChildCategory = [
            'name' => 'Child Category',
            'description' => 'Child description',
            'seo_title' => uniqid('title'),
            'seo_keywords' => uniqid('keywords'),
            'seo_description' => uniqid('description'),
        ];
        $childCategory = $this->createCategory($expectedChildCategory + [
                'parent' => $parentCategory->term_id,
            ]);

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedParentRow = $this->getMappedDataRow($spreadSheet, 2);
        $mappedChildRow = $this->getMappedDataRow($spreadSheet, 3);

        $this->assertCategory($expectedParentCategory, $parentCategory, $mappedParentRow, 'Parent');
        $this->assertCategory($expectedChildCategory, $childCategory, $mappedChildRow, 'Child');

        $this->assertEquals(
            $parentCategory->slug,
            $mappedChildRow['parent_slug'],
            'Child category parent_slug does not matches'
        );
    }

    private function assertCategory(array $expected, \WP_Term $category, array $mappedFirstRow, string $type): void
    {
        $expected += [
            'lang' => $mappedFirstRow['translatable.lang'],
            'published' => 'yes',
        ];

        $got = [
            'name' => $mappedFirstRow['title'],
            'lang' => Language::getSiteLanguageIso2(),
            'slug' => $category->slug,
            'description' => $mappedFirstRow['description'],
            'published' => $mappedFirstRow['published'],
            'seo_title' => $mappedFirstRow['seo_title'],
            'seo_keywords' => $mappedFirstRow['seo_keywords'],
            'seo_description' => $mappedFirstRow['seo_description'],
        ];

        $this->assertArraySubset(
            $expected, $got, true, 'Category '.$type
        );
    }
}
