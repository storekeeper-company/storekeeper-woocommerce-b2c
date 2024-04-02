<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AllFileExport;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;

class AllFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return AllFileExport::class;
    }

    public const EXPECTED_LINE_COUNT = [
        FileExportTypeHelper::ATTRIBUTE => 1,
        FileExportTypeHelper::ATTRIBUTE_OPTION => 6,
        FileExportTypeHelper::CATEGORY => 2,
        FileExportTypeHelper::TAG => 1,
        FileExportTypeHelper::CUSTOMER => 1,
        FileExportTypeHelper::PRODUCT => 4,
        FileExportTypeHelper::PRODUCT_BLUEPRINT => 1,
    ];

    public function testAllExportTest()
    {
        $this->setupTest();

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport('nl');
        $this->addFile($path);

        $this->assertFileIsReadable(
            $path,
            'Zip file is not readable'
        );

        $zip = new \ZipArchive();
        $this->assertTrue(
            $zip->open($path),
            'Failed to open zip file'
        );

        $this->assertEquals(
            7,
            $zip->count(),
            'Found 7 files in the ZIP archive'
        );

        $tmpPath = sys_get_temp_dir().'/'.time();
        mkdir($tmpPath);
        $this->assertTrue(
            $zip->extractTo($tmpPath),
            'Failed to extract zip file'
        );

        foreach (scandir($tmpPath) as $file) {
            if (!in_array($file, ['.', '..'])) {
                $filePath = "$tmpPath/$file";
                $this->addFile($filePath);
                $this->assertTrue(
                    filesize($filePath) > 0,
                    "file $file is empty"
                );

                $this->assertExportFile($filePath);
            }
        }
    }

    private function assertExportFile(string $filePath)
    {
        $type = $this->getTypeFromFilePath($filePath);
        $actualLines = self::EXPECTED_LINE_COUNT[$type];

        $spreadSheet = $this->readSpreadSheetFromPath($filePath);
        $lineCount = $this->getSpreadSheetLinkNumbers($spreadSheet);

        $this->assertEquals(
            $actualLines + 2,
            $lineCount,
            "Incorrect line number for $filePath"
        );
    }

    private function getTypeFromFilePath(string $filePath): string
    {
        $filename = basename($filePath);

        foreach (FileExportTypeHelper::TYPES as $TYPE) {
            $pos = strpos($filename, $TYPE);
            if (false !== $pos && $pos >= 0) {
                return $TYPE;
            }
        }
    }

    private function setupTest()
    {
        \WC_Helper_Product::create_attribute();
        list($taxRate21) = $this->createTaxRates();
        $this->createSimpleProduct('Simple product', $taxRate21);
        $this->createVariableProduct($taxRate21);
        $parentCategory = $this->createCategory(
            [
                'name' => 'Parent Category',
                'description' => 'Parent description',
            ]
        );
        $this->createCategory(
            [
                'name' => 'Child Category',
                'description' => 'Child description',
                'parent' => $parentCategory->term_id,
            ]
        );
        $this->createCustomer();
        $this->createTag(
            [
                'name' => 'Dummy title',
                'description' => 'Dummy description',
            ]
        );
    }
}
