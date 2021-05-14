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

    public function testTagExportTest()
    {
        $tag = $this->createTag(
            [
                'name' => 'Dummy title',
                'description' => 'Dummy description',
            ]
        );

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedFirstRow = $this->getMappedDataRow($spreadSheet, 2);

        $this->assertEquals(
            $tag->name,
            $mappedFirstRow['title'],
            'Tag title is incorrectly exported'
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $mappedFirstRow['translatable.lang'],
            'Tag language is incorrectly exported'
        );

        $this->assertEquals(
            $tag->slug,
            $mappedFirstRow['slug'],
            'Tag slug is incorrectly exported'
        );

        $this->assertEquals(
            $tag->description,
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
