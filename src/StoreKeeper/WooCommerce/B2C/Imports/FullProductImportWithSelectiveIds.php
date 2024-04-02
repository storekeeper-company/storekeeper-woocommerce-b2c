<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

class FullProductImportWithSelectiveIds extends ProductImport
{
    protected $file_path_with_ids;

    protected $shop_product_ids = [];

    protected $syncProductVariations = true;

    /**
     * ProductImportWithSelectiveIds constructor.
     *
     * @throws \Exception
     */
    public function __construct($settings)
    {
        $this->file_path_with_ids = key_exists(
            'file_path_with_ids',
            $settings
        ) ? (string) $settings['file_path_with_ids'] : null;

        if (file_exists($this->file_path_with_ids)) {
            $ids_json = file_get_contents($this->file_path_with_ids);
            $ids = json_decode($ids_json);
            if (false !== $ids) {
                $this->shop_product_ids = $ids;
            } else {
                throw new \Exception('File content is not a json: '.$this->file_path_with_ids);
            }
        }

        unset($settings['file_path_with_ids']);
        parent::__construct($settings);
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'flat_product/product/type__in_list',
                'multi_val' => [
                    'simple',
                    'configurable',
                ],
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        if (!empty($this->shop_product_ids)) {
            $f[] = [
                'name' => 'id__in_list',
                'multi_val' => $this->shop_product_ids,
            ];
        }

        if ($this->product_id > 0) {
            $f[] = [
                'name' => 'product_id__=',
                'val' => $this->product_id,
            ];
        }

        return $f;
    }

    public function run(array $options = []): void
    {
        if (!empty($this->shop_product_ids)) {
            $this->debug(
                'RUNNING IMPORT WITH CERTAIN PRODUCT IDS',
                [
                    'path' => $this->file_path_with_ids,
                    'ids' => $this->shop_product_ids,
                ]
            );
        } else {
            $this->debug(
                'RUNNING IMPORT WITHOUT CERTAIN PRODUCT IDS',
                [
                    'path' => $this->file_path_with_ids,
                    'ids' => $this->shop_product_ids,
                ]
            );
        }

        parent::run();
    }

    // During fullsync we dont set the upsell ids.
    // Because with a full sync we executeInWpCli `wp sk sync-woocommerce-upsell-products` to sync them
    protected function setUpsellIds(&$newProduct, $product)
    {
        return [];
    }

    // During fullsync we dont set the cross sell ids.
    // Because with a full sync we executeInWpCli `wp sk sync-woocommerce-cross-products` to sync them
    protected function setCrossSellIds(&$newProduct, $product)
    {
        return [];
    }
}
