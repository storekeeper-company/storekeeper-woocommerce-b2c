<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;
use StoreKeeper\WooCommerce\B2C\UnitTest\Interfaces\IFileExportTest;
use WC_Customer;
use WC_Helper_Customer;
use WC_Helper_Product;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WC_Tax;

abstract class AbstractFileExportTest extends AbstractTest implements IFileExportTest
{
    /**
     * @var string[]
     */
    private $files = [];

    protected function addFile(string $filePath)
    {
        $this->files[] = $filePath;
    }

    private function cleanFiles()
    {
        foreach ($this->files as $file) {
            $this->unlink($file);
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->cleanFiles();
    }

    protected function getNewFileExportInstance(): IFileExport
    {
        $Class = $this->getFileExportClass();

        return new $Class();
    }

    protected function readSpreadSheetFromPath(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    protected function getRowFromActiveSheet(Spreadsheet $spreadsheet, int $rowNumber): Row
    {
        $workSheet = $spreadsheet->getActiveSheet();
        $foundRow = null;
        foreach ($workSheet->getRowIterator() as $index => $row) {
            if ($index - 1 === $rowNumber) {
                $foundRow = $row;
            }
        }

        if (null === $foundRow) {
            throw new Exception("Row with index ($rowNumber) not found");
        }

        return $foundRow;
    }

    protected function getArrayFromRow(Row $row): array
    {
        $arr = [];

        foreach ($row->getCellIterator() as $cell) {
            $arr[] = $cell->getValue();
        }

        return $arr;
    }

    protected function getMappedDataRow(Spreadsheet $spreadSheet, int $dataRowNumber): array
    {
        $map = [];
        $pathRow = $this->getRowFromActiveSheet($spreadSheet, 0);
        $dataRow = $this->getRowFromActiveSheet($spreadSheet, $dataRowNumber);

        $paths = $this->getArrayFromRow($pathRow);
        $data = $this->getArrayFromRow($dataRow);

        foreach ($paths as $index => $path) {
            $name = substr($path, strlen('path://'));
            $map[$name] = $data[$index];
        }

        return $map;
    }

    protected function getSpreadSheetLinkNumbers(Spreadsheet $spreadsheet): int
    {
        return $spreadsheet
            ->getActiveSheet()
            ->getHighestRow();
    }

    protected function mapSpreadSheetRows(Spreadsheet $spreadSheet, callable $keyCallable): array
    {
        $map = [];
        $workSheet = $spreadSheet->getActiveSheet();
        $highestIndex = $workSheet->getHighestRow();
        for ($i = 2; $i < $highestIndex; ++$i) {
            $rowData = $this->getMappedDataRow($spreadSheet, $i);
            $key = $keyCallable($rowData);
            $map[$key] = $rowData;
        }

        return $map;
    }

    public function spreadSheetToArray(Spreadsheet $spreadSheet): array
    {
        $array = [];

        foreach ($spreadSheet->getAllSheets() as $worksheet) {
            $worksheetArray = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $rowArray = [];
                foreach ($row->getCellIterator() as $cell) {
                    $rowArray[] = $cell->getValue();
                }
                $worksheetArray[] = $rowArray;
            }
            $array[] = $worksheetArray;
        }

        return $array;
    }

    /**
     * Creation functions.
     */
    protected function createTag(array $data)
    {
        $term = wp_insert_term('Dummy Category', 'product_tag', $data);

        return get_term($term['term_id']);
    }

    protected function createTaxRates(): array
    {
        $taxRate = [
            'tax_rate_country' => 'NL',
            'tax_rate_state' => '',
            'tax_rate' => '21.0000',
            'tax_rate_name' => 'Tax rate 21%',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => '0',
            'tax_rate_shipping' => '0',
            'tax_rate_order' => '1',
            'tax_rate_class' => '21percent',
        ];
        $taxRate21Id = WC_Tax::_insert_tax_rate($taxRate);

        $taxRate = [
            'tax_rate_country' => 'NL',
            'tax_rate_state' => '',
            'tax_rate' => '9.0000',
            'tax_rate_name' => 'Tax rate 9%',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => '0',
            'tax_rate_shipping' => '0',
            'tax_rate_order' => '1',
            'tax_rate_class' => '9percent',
        ];
        $taxRate9Id = WC_Tax::_insert_tax_rate($taxRate);

        $taxRate21 = WC_Tax::_get_tax_rate($taxRate21Id, OBJECT);
        $taxRate9 = WC_Tax::_get_tax_rate($taxRate9Id, OBJECT);

        return [$taxRate21, $taxRate9];
    }

    protected function createSimpleProduct($taxRateObject = null): WC_Product_Simple
    {
        $product = new WC_Product_Simple();
        $taxRateObject ? $product->set_tax_class($taxRateObject->tax_rate_class) : null;
        $unique = sanitize_title(uniqid('', true));
        $product->set_props(
            [
                'name' => "Simple product $unique",
                'regular_price' => 15,
                'price' => 10,
                'sku' => "simple-product-$unique",
                'manage_stock' => false,
                'tax_status' => 'taxable',
                'downloadable' => false,
                'virtual' => false,
                'stock_status' => 'instock',
                'description' => 'This is a simple product',
                'short_description' => 'A simple product',
            ]
        );

        $attribute = new WC_Product_Attribute();
        $attribute_data = WC_Helper_Product::create_attribute('size', ['small', 'large', 'huge']);
        $attribute->set_id($attribute_data['attribute_id']);
        $attribute->set_name($attribute_data['attribute_taxonomy']);
        $attribute->set_options($attribute_data['term_ids']);
        $attribute->set_position(1);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        $product->set_attributes([$attribute]);

        $product->save();

        return wc_get_product($product->get_id());
    }

    protected function addImageToProduct(WC_Product $product)
    {
        $product->set_image_id($this->createAttachment($product->get_id()));
        $product->save();
    }

    protected function createAttachment(int $parentId): int
    {
        $name = uniqid("wp-image-$parentId-", true);

        return wp_insert_attachment(
            [
                'guid' => "https://example.com/$name.png",
                'post_mime_type' => 'image/png',
                'post_title' => $name,
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $parentId,
            ]
        );
    }

    protected function createVariableProduct($taxRateObject = null): WC_Product_Variable
    {
        $product = new WC_Product_Variable();
        $taxRateObject ? $product->set_tax_class($taxRateObject->tax_rate_class) : null;
        $unique = sanitize_title(uniqid('', true));
        $product->set_props(
            [
                'name' => "Variable product $unique",
                'sku' => "variable-product-$unique",
                'manage_stock' => false,
                'tax_status' => 'taxable',
                'downloadable' => false,
                'virtual' => false,
                'stock_status' => 'instock',
                'description' => 'This is a variable product',
                'short_description' => 'A variable product',
            ]
        );

        $attribute = new WC_Product_Attribute();
        $attribute_data = WC_Helper_Product::create_attribute('Size', ['small', 'large', 'huge']);
        $attribute->set_id($attribute_data['attribute_id']);
        $attribute->set_name($attribute_data['attribute_taxonomy']);
        $attribute->set_options($attribute_data['term_ids']);
        $attribute->set_position(1);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;

        $product->set_attributes($attributes);
        $product->save();

        $variation_1 = new WC_Product_Variation();
        $variation_1->set_props(
            [
                'parent_id' => $product->get_id(),
                'sku' => 'variation-product-1-small',
                'regular_price' => 10,
                'description' => 'This is a variation product',
                'short_description' => 'A variation product',
            ]
        );
        $variation_1->set_attributes(['pa_size' => 'small']);
        $variation_1->save();

        $variation_2 = new WC_Product_Variation();
        $variation_2->set_props(
            [
                'parent_id' => $product->get_id(),
                'sku' => 'variation-product-1-large',
                'regular_price' => 15,
                'description' => 'This is a variation product',
                'short_description' => 'A variation product',
            ]
        );
        $variation_2->set_attributes(['pa_size' => 'large']);
        $variation_2->save();

        $product->save();

        return wc_get_product($product->get_id());
    }

    protected function createCustomer(): WC_Customer
    {
        $customer = WC_Helper_Customer::create_customer();
        $customer->set_last_name('Dank');
        $customer->set_billing_phone('04 123 456 78');
        $customer->set_billing_company('tmp.dev');
        $customer->set_shipping_first_name('Foo');
        $customer->set_shipping_last_name('Bar');
        $customer->set_billing_first_name('John');
        $customer->set_billing_last_name('Doe');
        $customer->save();

        return $customer;
    }

    protected function createCategory(array $data)
    {
        $term = wp_insert_term('Dummy Category', 'product_cat', $data);

        return get_term($term['term_id']);
    }
}
