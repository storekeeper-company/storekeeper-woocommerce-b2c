<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\TagFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class TagFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return TagFileExport::class;
    }

    public function dataProviderTagExportTest()
    {
        $tests = [];

        $tests['skip empty tags'] = [
            true,
            3,
            '3 rows only which consist of path, title, and 1 data row',
        ];

        $tests['include empty tags'] = [
            false,
            4,
            '4 rows only which consist of path, title, and 2 data rows',
        ];

        return $tests;
    }

    /**
     * @dataProvider dataProviderTagExportTest
     */
    public function testTagExportTest(bool $shouldSkipEmptyTags, int $expectedRowCount, string $assertMessage)
    {
        $tagWithProduct = $this->createTag(
            [
                'name' => 'Dummy title 1',
                'description' => 'Dummy description',
            ],
            'Dummy Tag With Product'
        );

        $tagWithoutProduct = $this->createTag(
            [
                'name' => 'Dummy title 2',
                'description' => 'Dummy description',
            ],
            'Dummy Tag Without Product'
        );

        $product = $this->createSimpleProduct('simple product');
        $product->set_tag_ids([
            $tagWithProduct->term_id,
        ]);
        $product->save();

        /* @var TagFileExport $instance */
        $instance = $this->getNewFileExportInstance();
        $instance->setShouldSkipEmptyTag($shouldSkipEmptyTags);
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);

        $this->assertEquals(
            $expectedRowCount,
            $spreadSheet->getActiveSheet()->getHighestRow(),
            $assertMessage
        );

        $mappedFirstRow = $this->getMappedDataRow($spreadSheet, 2);

        $this->assertEquals(
            $tagWithProduct->name,
            $mappedFirstRow['title'],
            'Tag title is incorrectly exported'
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $mappedFirstRow['translatable.lang'],
            'Tag language is incorrectly exported'
        );

        $this->assertEquals(
            $tagWithProduct->slug,
            $mappedFirstRow['slug'],
            'Tag slug is incorrectly exported'
        );

        $this->assertEquals(
            $tagWithProduct->description,
            $mappedFirstRow['description'],
            'Tag description is incorrectly exported'
        );

        $this->assertEquals(
            'yes',
            $mappedFirstRow['published'],
            'Tag published is incorrectly exported'
        );
    }
}
