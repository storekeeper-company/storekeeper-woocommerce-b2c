<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Exceptions\ProductSkuEmptyException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\RankMathSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\YoastSeo;
use StoreKeeper\WooCommerce\B2C\Interfaces\ProductExportInterface;
use StoreKeeper\WooCommerce\B2C\Query\ProductQueryBuilder;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Base36Coder;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\BlueprintExport;

class ProductFileExport extends AbstractCSVFileExport implements ProductExportInterface
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_CONFIGURABLE = 'configurable';
    public const TYPE_ASSIGNED = 'configurable_assign';

    public const SUPPORTED_WC_TYPES = [
        'simple',
        'variable',
    ];
    public const PRODUCT_SKU_FIELD = 'product.sku';

    protected $tax_rate_country_iso;
    protected $price_field;
    private $shouldExportActiveProductsOnly = true;

    public function setShouldExportActiveProductsOnly(bool $shouldExportActiveProductsOnly): void
    {
        $this->shouldExportActiveProductsOnly = $shouldExportActiveProductsOnly;
    }

    public static function getTaxRate(\WC_Product $product, string $country_iso): ?object
    {
        $taxRateClass = $product->get_tax_class();
        $taxRates = \WC_Tax::get_rates_for_tax_class($taxRateClass) ?? [];

        foreach ($taxRates as $rate) {
            if ($rate->tax_rate_country === $country_iso) {
                return $rate;
            }
        }

        return array_shift($taxRates);
    }

    public function getType(): string
    {
        return FileExportTypeHelper::PRODUCT;
    }

    public function getPaths(): array
    {
        $paths = [
            'product.type' => 'Type',
            self::PRODUCT_SKU_FIELD => 'Product number',
            'title' => 'Product name',
            'summary' => 'Short description',
            'body' => 'Long description',
            'slug' => 'slug',

            'seo_title' => 'SEO title',
            'seo_keywords' => 'SEO keywords',
            'seo_description' => 'SEO description',

            'product.active' => 'Active',
            'product.product_stock.in_stock' => 'In Stock',
            'product.product_stock.value' => 'Stock Value',
            'product.product_stock.unlimited' => 'Always on stock',
            'shop_products.main.backorder_enabled' => 'Backorder enabled',

            'product.product_price.tax' => 'VAT Rate',
            'product.product_price.tax_rate.country_iso2' => 'VAT Rate Country code (iso2)',
            'product.product_price.tax_rate.alias' => 'VAT Rate alias',

            'product.product_price.currency_iso3' => 'Currency',
            'product.product_price.ppu' => 'Price',
            'product.product_price.ppu_wt' => 'Price with VAT',
            'product.product_discount_price.ppu' => 'Discount price',
            'product.product_discount_price.ppu_wt' => 'Discount price with VAT',
            'product.product_purchase_price.ppu' => 'Purchase price',
            'product.product_purchase_price.ppu_wt' => 'Purchase price with VAT',
            'product.product_bottom_price.ppu' => 'Bottom price',
            'product.product_bottom_price.ppu_wt' => 'Bottom price with VAT',
            'product.product_cost_price.ppu' => 'Cost price',
            'product.product_cost_price.ppu_wt' => 'Cost price with VAT',

            'product.configurable_product_kind.alias' => 'Product kind alias',
            'product.configurable_product.sku' => 'Configurable product sku',

            'main_category.title' => 'Category',
            'main_category.slug' => 'Category slug',
            'extra_category_slugs' => 'Extra Category slugs',
            'extra_label_slugs' => 'Extra Label slugs',

            'attribute_set_name' => 'Attribute set name',
            'attribute_set_alias' => 'Attribute set alias',

            'shop_products.main.active' => 'Sales active',
            'shop_products.main.relation_limited' => 'Sales relation limited',
        ];

        foreach (AttributeExport::getAllAttributes() as $attribute) {
            $label = $attribute['label'];
            $encodedName = Base36Coder::encode($attribute['name']);

            $paths["content_vars.encoded__$encodedName.value"] = "$label (raw)";
            $paths["content_vars.encoded__$encodedName.value_label"] = "$label (label)";
        }

        $mostImage = $this->getProductWithMostImagesCount();
        for ($index = 0; $index < $mostImage; ++$index) {
            $paths["product.product_images.$index.download_url"] = 'Image '.($index + 1);
        }

        return $paths;
    }

    private function getProductWithMostImagesCount(): int
    {
        global $wpdb;

        $amount = $wpdb->get_var(ProductQueryBuilder::getProductCount());
        $count = 0;

        for ($index = 0; $index < $amount; ++$index) {
            $meta = $wpdb->get_results(ProductQueryBuilder::getProductMetaDataAtIndex($index), ARRAY_A);
            $data = [];
            foreach ($meta as $item) {
                $data[$item['meta_key']] = $item['meta_value'];
            }
            $imageIds = [];
            array_key_exists('_thumbnail_id', $data) ? $imageIds[] = $data['_thumbnail_id'] : null;
            if (array_key_exists('_product_image_gallery', $data)) {
                $gallery = explode(',', $data['_product_image_gallery']);
                $imageIds = array_merge($imageIds, $gallery);
            }

            $currentCount = count(array_unique($imageIds));
            if ($currentCount > $count) {
                $count = $currentCount;
            }
        }

        return $count;
    }

    public function runExport(?string $exportLanguage = null): string
    {
        $next = true;
        $index = 0;
        while ($next) {
            $product = $this->getProductAtIndex($index++);
            $next = (bool) $product;

            if ($product instanceof \WC_Product) {
                if (!in_array($product->get_type(), self::SUPPORTED_WC_TYPES)) {
                    continue;
                }

                try {
                    $lineData = [];

                    $lineData = $this->exportGenericInfo($lineData, $product);
                    $lineData = $this->exportTitleAndSku($lineData, $product);
                    $lineData = $this->exportTagsAndCategories($lineData, $product);
                    $lineData = $this->exportVat($lineData, $product);
                    $lineData = $this->exportSEO($lineData, $product);
                    $lineData = $this->exportStock($lineData, $product);
                    $lineData = $this->exportImage($lineData, $product);
                    $lineData = $this->exportAttributes($lineData, $product);

                    if ($product instanceof \WC_Product_Variable) {
                        $lineData = $this->exportConfigurablePrice($lineData, $product);
                        $lineData = $this->exportBlueprintField($lineData, $product);
                        $this->writeLineData($lineData);

                        $this->exportAssignedProducts($product);
                    } else {
                        $lineData = $this->exportPrice($lineData, $product);
                        $this->writeLineData($lineData);
                    }
                } catch (ProductSkuEmptyException $e) {
                    $this->logger->warning('Skipping product because it have no sku', [
                        'id' => $e->getProduct()->get_id(),
                        'name' => $e->getProduct()->get_title(),
                    ]);
                }
            }
        }

        return $this->filePath;
    }

    private function getProductAtIndex($index): ?\WC_Product
    {
        global $wpdb;

        $query = ProductQueryBuilder::getProductIdsByPostType('product', $index, $this->shouldExportActiveProductsOnly);
        $results = $wpdb->get_results($query);
        $result = current($results);
        if (false !== $result) {
            $product = wc_get_product($result->product_id);
            if (false !== $product) {
                return $product;
            }
        }

        return null;
    }

    private function exportAssignedProducts(\WC_Product_Variable $parentProduct)
    {
        $objectsToGenerate = $this->getObjectsToGenerate($parentProduct);
        foreach ($objectsToGenerate as $object) {
            $product = $object['product'];
            $attributes = $object['attributes'];

            try {
                $lineData = [];
                $lineData = $this->exportGenericInfo($lineData, $product);
                $lineData = $this->exportAssignedTitleAndSku($lineData, $product, $parentProduct, $attributes);
                $lineData = $this->exportCategories($lineData, $product);
                $lineData = $this->exportPrice($lineData, $product);
                $lineData = $this->exportVat($lineData, $product);
                $lineData = $this->exportSEO($lineData, $product);
                $lineData = $this->exportStock($lineData, $product);
                $lineData = $this->exportAssignedImage($lineData, $product, $parentProduct);
                $lineData = $this->exportBlueprintField($lineData, $parentProduct);
                $lineData = $this->exportConfigurableSku($lineData, $parentProduct);
                $lineData = $this->exportAssignedAttributes($lineData, $attributes);

                $this->writeLineData($lineData);
            } catch (ProductSkuEmptyException $e) {
                $this->logger->warning('Skipping product variation because it have no sku', [
                    'id' => $parentProduct->get_id(),
                    'name' => $parentProduct->get_title(),
                    'variation_id' => $e->getProduct()->get_id(),
                    'variation_name' => $e->getProduct()->get_title(),
                ]);
            }
        }
    }

    private function getSku(\WC_Product $product): string
    {
        $sku = $product->get_sku('edit'); // edit will retund empty sku for varaible is not set directly
        if (empty($sku)) {
            throw new ProductSkuEmptyException($product);
        }

        return $sku;
    }

    private function exportTitleAndSku(array $lineData, \WC_Product $product): array
    {
        $lineData['title'] = $product->get_title();
        $lineData[self::PRODUCT_SKU_FIELD] = $this->getSku($product);

        return $lineData;
    }

    private function exportGenericInfo(array $lineData, \WC_Product $product): array
    {
        $lineData['summary'] = $product->get_short_description();
        $lineData['body'] = $product->get_description();
        $lineData['product.type'] = self::getProductType($product);
        $lineData['slug'] = $product->get_slug();

        return $lineData;
    }

    public static function getProductType(\WC_Product $product): string
    {
        if ($product instanceof \WC_Product_Variable) {
            return self::TYPE_CONFIGURABLE;
        }

        if ($product instanceof \WC_Product_Variation) {
            return self::TYPE_ASSIGNED;
        }

        return self::TYPE_SIMPLE;
    }

    private function exportTagsAndCategories(array $lineData, \WC_Product $product): array
    {
        $lineData = $this->exportTags($lineData, $product);

        return $this->exportCategories($lineData, $product);
    }

    private function exportTags(array $lineData, \WC_Product $product): array
    {
        $lineData['extra_label_slugs'] = self::getTagSlugs($product);

        return $lineData;
    }

    public static function getTagSlugs(\WC_Product $product): string
    {
        $ids = $product->get_tag_ids();
        if (count($ids) <= 0) {
            return '';
        }

        $arguments = [
            'hide_empty' => false,
            'include' => $ids,
        ];

        $slugs = array_map(
            static function ($tag) {
                return $tag->slug;
            },
            get_terms('product_tag', $arguments)
        );

        return implode('|', $slugs);
    }

    private function exportCategories(array $lineData, \WC_Product $product): array
    {
        $lineData['extra_category_slugs'] = self::getCategorySlugs($product);

        return $lineData;
    }

    public static function getCategorySlugs(\WC_Product $product): string
    {
        $ids = $product->get_category_ids();
        if (count($ids) <= 0) {
            return '';
        }

        $arguments = [
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'include' => $ids,
        ];
        $slugs = array_map(
            function ($category) {
                return $category->slug;
            },
            get_categories($arguments)
        );

        return implode('|', $slugs);
    }

    private function exportPrice(array $lineData, \WC_Product $product): array
    {
        $lineData['product.product_price.currency_iso3'] = get_woocommerce_currency();
        $priceField = $this->getPriceField();
        $lineData['product.product_price.'.$priceField] = $product->get_regular_price();
        $lineData['product.product_discount_price.'.$priceField] = $product->get_sale_price();

        return $lineData;
    }

    private function exportConfigurablePrice(array $lineData, \WC_Product_Variable $product): array
    {
        $priceField = $this->getPriceField();
        $lineData['product.product_price.currency_iso3'] = get_woocommerce_currency();
        $lineData['product.product_price.'.$priceField] = $product->get_variation_regular_price();
        $lineData['product.product_discount_price.'.$priceField] = $product->get_variation_sale_price();

        return $lineData;
    }

    public function getTaxRateCountryIso(): string
    {
        if (is_null($this->tax_rate_country_iso)) {
            /* @var \WooCommerce $woocommerce */
            global $woocommerce;
            $this->tax_rate_country_iso = $woocommerce->countries->get_base_country();
        }

        return $this->tax_rate_country_iso;
    }

    public function setTaxRateCountryIso(string $iso)
    {
        $this->tax_rate_country_iso = $iso;
    }

    private function exportVat(array $lineData, \WC_Product $product): array
    {
        $taxRate = self::getTaxRate($product, $this->getTaxRateCountryIso());

        if (!empty($taxRate)) {
            $lineData['product.product_price.tax'] = $taxRate->tax_rate / 100;
            $lineData['product.product_price.tax_rate.country_iso2'] = $taxRate->tax_rate_country;
        }

        return $lineData;
    }

    /**
     * @throws WordpressException
     */
    private function exportSEO(array $lineData, \WC_Product $product): array
    {
        if (YoastSeo::isSelectedHandler()) {
            $lineData['seo_title'] = YoastSeo::getPostTitle($product->get_id());
            $lineData['seo_description'] = YoastSeo::getPostDescription($product->get_id());
            $lineData['seo_keywords'] = YoastSeo::getPostKeywords($product->get_id());
        } elseif (RankMathSeo::isSelectedHandler()) {
            $lineData['seo_title'] = RankMathSeo::getPostTitle($product->get_id());
            $lineData['seo_description'] = RankMathSeo::getPostDescription($product->get_id());
            $lineData['seo_keywords'] = RankMathSeo::getPostKeywords($product->get_id());
        } elseif (StoreKeeperSeo::isSelectedHandler()) {
            $lineData += StoreKeeperSeo::getProductSeo($product);
        }

        return $lineData;
    }

    private function exportStock(array $lineData, \WC_Product $product): array
    {
        $stockValue = 0;
        if ($product->is_in_stock()) {
            $stockValue = $product->get_stock_quantity() ?? 1;
        }

        $lineData['product.active'] = 'publish' === $product->get_status();
        $lineData['product.product_stock.in_stock'] = $product->is_in_stock();
        $lineData['product.product_stock.value'] = $stockValue;
        $lineData['product.product_stock.unlimited'] = !$product->get_manage_stock();
        $lineData['shop_products.main.backorder_enabled'] = 'no' !== $product->get_backorders();

        return $lineData;
    }

    private function exportImage(array $lineData, \WC_Product $product): array
    {
        foreach ($this->getProductImageIds($product) as $index => $imageId) {
            $imageUrl = wp_get_attachment_url($imageId);
            $lineData["product.product_images.$index.download_url"] = filter_var($imageUrl, FILTER_VALIDATE_URL) ? $imageUrl : '';
        }

        return $lineData;
    }

    private function exportAssignedImage(array $lineData, \WC_Product $product, \WC_Product $parentProduct): array
    {
        $parentProductImageIds = $this->getProductImageIds($parentProduct);
        $assignedProductImageIds = $this->getProductImageIds($product);
        $imageIds = array_diff($assignedProductImageIds, $parentProductImageIds);
        foreach ($imageIds as $index => $imageId) {
            $imageUrl = wp_get_attachment_url($imageId);
            $lineData["product.product_images.$index.download_url"] = false === $imageUrl ? '' : $imageUrl;
        }

        return $lineData;
    }

    private function exportAttributes(array $lineData, \WC_Product $product): array
    {
        foreach ($product->get_attributes() as $alias => $attribute) {
            $name = AttributeExport::getProductAttributeKey($attribute);
            $encodedName = Base36Coder::encode($name);
            $isCustomAttribute = 0 === $attribute->get_id();
            if (!$isCustomAttribute) {
                $labelPath = "content_vars.encoded__$encodedName.value_label";
                $valuePath = "content_vars.encoded__$encodedName.value";

                // only take the first option, backend does not handle multiple options on one
                $hasMultiple = count($attribute->get_options());
                if ($hasMultiple) {
                    $this->logger->warning('Product has multiple options, choosing first', [
                        'id' => $product->get_id(),
                        'name' => $attribute->get_name(),
                        'options' => $attribute->get_options(),
                    ]);
                }
                $option = current($attribute->get_options());
                $terms = get_terms(
                    $alias,
                    [
                        'hide_empty' => false,
                        'include' => [$option],
                    ]
                );
                $term = array_shift($terms);
                $lineData[$labelPath] = $term->name;
                $lineData[$valuePath] = $term->slug;
            } else {
                $lineData["content_vars.encoded__$encodedName.value"] = current($attribute->get_options());
            }
        }

        return $lineData;
    }

    private function exportAssignedTitleAndSku(
        array $lineData,
        \WC_Product_Variation $product,
        \WC_Product_Variable $parentProduct,
        array $attributes
    ): array {
        $valueLabels = [];
        $values = [];

        foreach ($attributes as $attribute) {
            $valueLabels[] = $attribute['value_label'];
            $values[] = $attribute['value'];
        }

        $title = $parentProduct->get_title().' - '.implode(' ', $valueLabels);
        $sku = $this->getSku($product);

        $lineData['title'] = $title;
        $lineData[self::PRODUCT_SKU_FIELD] = $sku;

        return $lineData;
    }

    private function exportAssignedAttributes(
        array $lineData,
        array $attributes
    ): array {
        foreach ($attributes as $attribute) {
            $value = $attribute['value'];
            $valueLabel = $attribute['value_label'];

            $encodedName = Base36Coder::encode($attribute['name']);
            $labelPath = "content_vars.encoded__$encodedName.value_label";
            $valuePath = "content_vars.encoded__$encodedName.value";

            $lineData[$labelPath] = $valueLabel;
            $lineData[$valuePath] = $value;
        }

        return $lineData;
    }

    public static function isAttributeWithOptions($name)
    {
        return array_key_exists(
            $name,
            Attributes::getAttributeOptionsMap()
        );
    }

    private function exportBlueprintField(array $lineData, \WC_Product_Variable $product): array
    {
        $blueprintExport = new BlueprintExport($product);
        $lineData['product.configurable_product_kind.alias'] = $blueprintExport->getAlias();

        return $lineData;
    }

    private function exportConfigurableSku(array $lineData, \WC_Product_Variable $parentProduct): array
    {
        $lineData['product.configurable_product.sku'] = $this->getSku($parentProduct);

        return $lineData;
    }

    private function getProductImageIds(\WC_Product $product): array
    {
        $galleryImages = $product->get_gallery_image_ids();
        $imageId = (int) $product->get_image_id();
        if (in_array($imageId, $galleryImages, true)) {
            $imageIds = $galleryImages;
        } else {
            $imageIds = array_merge(
                [
                    $imageId,
                ],
                $galleryImages
            );
        }

        $imageIds = array_filter(
            $imageIds,
            function ($image) {
                return !empty($image);
            }
        );

        return $imageIds;
    }

    private function getObjectsToGenerate(\WC_Product_Variable $parentProduct): array
    {
        $parentAttributes = $parentProduct->get_attributes();

        $objectsToGenerate = [];

        foreach ($parentProduct->get_children() as $productId) {
            $product = new \WC_Product_Variation($productId);
            $object = [
                'product' => $product,
                'attributes' => [],
            ];
            $anyAttributes = [];

            foreach ($product->get_attributes() as $name => $value) {
                $parentAttribute = $parentAttributes[$name];
                if ($parentAttribute) {
                    if ($value) {
                        $term = get_term_by('slug', $value, $name);
                        $object['attributes'][] = [
                            'title' => AttributeExport::getProductAttributeLabel($parentAttribute),
                            'name' => AttributeExport::getProductAttributeKey($parentAttribute),
                            'value' => sanitize_title($value),
                            'value_label' => $term ? $term->name : $value,
                        ];
                    } else {
                        $anyAttributes[$name] = AttributeExport::getProductAttributeOptions($parentAttribute);
                    }
                }
            }

            if (count($anyAttributes) > 0) {
                foreach ($anyAttributes as $name => $options) {
                    $parentAttribute = $parentAttributes[$name];
                    foreach ($options as $option) {
                        $optionObject = $object;
                        $optionObject['attributes'][] = [
                            'title' => AttributeExport::getProductAttributeLabel($parentAttribute),
                            'name' => AttributeExport::getProductAttributeKey($parentAttribute),
                            'value' => $option['alias'],
                            'value_label' => $option['title'],
                        ];
                        $objectsToGenerate[] = $optionObject;
                    }
                }
            } else {
                $objectsToGenerate[] = $object;
            }
        }

        return $objectsToGenerate;
    }

    private function getPriceField(): string
    {
        if (is_null($this->price_field)) {
            if (!wc_tax_enabled()) {
                $this->price_field = 'ppu_wt'; // b2c sells always with VAT
            } else {
                $this->price_field = wc_prices_include_tax() ? 'ppu_wt' : 'ppu';
            }
        }

        return $this->price_field;
    }
}
