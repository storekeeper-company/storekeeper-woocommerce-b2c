<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WP_Term;

class CategoryFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return CategoryFileExport::class;
    }

    public function testCategoryExportTest()
    {
        $parentCategory = $this->createCategory(
            [
                'name' => 'Parent Category',
                'description' => 'Parent description',
            ]
        );
        $childCategory = $this->createCategory(
            [
                'name' => 'Child Category',
                'description' => 'Child description',
                'parent' => $parentCategory->term_id,
            ]
        );

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedParentRow = $this->getMappedDataRow($spreadSheet, 2);
        $mappedChildRow = $this->getMappedDataRow($spreadSheet, 3);

        $this->assertCategory($parentCategory, $mappedParentRow, 'Parent');
        $this->assertCategory($childCategory, $mappedChildRow, 'Child');

        $this->assertEquals(
            $parentCategory->slug,
            $mappedChildRow['parent_slug'],
            'Child category parent_slug does not matches'
        );
    }

    private function assertCategory(WP_Term $category, array $mappedFirstRow, string $type): void
    {
        $this->assertEquals(
            $category->name,
            $mappedFirstRow['title'],
            "$type Category title is incorrectly exported"
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $mappedFirstRow['translatable.lang'],
            "$type Category language is incorrectly exported"
        );

        $this->assertEquals(
            $category->slug,
            $mappedFirstRow['slug'],
            "$type Category slug is incorrectly exported"
        );

        $this->assertEquals(
            $category->description,
            $mappedFirstRow['description'],
            "$type Category description is incorrectly exported"
        );

        $this->assertEquals(
            'yes',
            $mappedFirstRow['published'],
            "$type Category published is incorrectly exported"
        );
    }
}
