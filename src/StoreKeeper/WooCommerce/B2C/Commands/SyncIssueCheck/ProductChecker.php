<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class ProductChecker implements SyncIssueCheckerInterface
{
    use LoggerAwareTrait;

    /**
     * @var ApiWrapper
     */
    protected $api;

    /**
     * @var array
     */
    private $backend_products = [];

    /**
     * @var array
     */
    private $woocommerce_products = [];

    /**
     * cache.
     */
    private $formattedInactiveProducts = [];
    private $formattedActiveProducts = [];
    private $formattedProductMissMatch = [];

    /**
     * ProductChecker constructor.
     */
    public function __construct(ApiWrapper $api)
    {
        $this->setLogger(new NullLogger());
        $this->api = $api;

        $this->backend_products = $this->fetchBackendProducts();
        $this->woocommerce_products = $this->fetchWooCommerceProductsForActiveCheck();
    }

    public function isSuccess(): bool
    {
        $success = true;
        foreach ($this->backend_products as $shop_product_id => $product) {
            if (!key_exists(
                $shop_product_id,
                $this->woocommerce_products
            ) || !$this->woocommerce_products[$shop_product_id]['enabled']) {
                $success = false;
                break;
            }
        }

        foreach ($this->woocommerce_products as $shop_product_id => $woocommerce_product) {
            if ($woocommerce_product['enabled'] && !key_exists($shop_product_id, $this->backend_products)) {
                $success = false;
                break;
            }
        }

        return $success;
    }

    public function getReportTextOutput(): string
    {
        $productsActiveInBackendNotActiveInWoocommerce = $this->getProductsActiveInBackendNotActiveInWoocommerce();
        $productActiveInWoocommerceNotActiveInBackend = $this->getProductActiveInWoocommerceNotActiveInBackend();
        $variableProductMissMatch = $this->getVariableProductMissMatch();

        // Formatting inactive products that should be active.
        list($inactiveProducts_amount, $inactiveProducts_shopProductIds, $inactiveProducts_productIds) = $this->formatInactiveProductsInWooCommerce(
            $productsActiveInBackendNotActiveInWoocommerce
        );

        // Formatting active products that should be inactive.
        list($activeProducts_amount, $activeProducts_shopProductIds, $activeProducts_postIds) = $this->formatActiveProducts(
            $productActiveInWoocommerceNotActiveInBackend
        );

        // Formatting attribute miss match
        $variable_product_attribute_miss_match = $this->formatAttributeMissMatch($variableProductMissMatch);

        return "
=== products active in backend not active in woocommerce ===
quantity: $inactiveProducts_amount
shop_product_ids: $inactiveProducts_shopProductIds
product_ids: $inactiveProducts_productIds

=== product active in woocommerce not active in backend ===
quantity: $activeProducts_amount
shop_product_ids: $activeProducts_shopProductIds
post_ids: $activeProducts_postIds

=== Variable product attribute miss match ===
$variable_product_attribute_miss_match";
    }

    public function getReportData(): array
    {
        $productsActiveInBackendNotActiveInWoocommerce = $this->getProductsActiveInBackendNotActiveInWoocommerce();
        $productActiveInWoocommerceNotActiveInBackend = $this->getProductActiveInWoocommerceNotActiveInBackend();
        $variableProductMissMatch = $this->getVariableProductMissMatch();

        return [
            'missing_active_product_in_woocommerce' => [
                'amount' => $productsActiveInBackendNotActiveInWoocommerce['amount'],
                'product_ids' => $productsActiveInBackendNotActiveInWoocommerce['product_ids'],
                'shop_product_ids' => $productsActiveInBackendNotActiveInWoocommerce['shop_product_ids'],
                'simple' => $productsActiveInBackendNotActiveInWoocommerce['simple'],
                'configurable' => $productsActiveInBackendNotActiveInWoocommerce['configurable'],
                'assign' => $productsActiveInBackendNotActiveInWoocommerce['assign'],
            ],
            'products_that_need_deactivation' => [
                'amount' => $productActiveInWoocommerceNotActiveInBackend['amount'],
                'post_ids' => $productActiveInWoocommerceNotActiveInBackend['post_ids'],
                'shop_product_ids' => $productActiveInWoocommerceNotActiveInBackend['shop_product_ids'],
                'simple' => $productActiveInWoocommerceNotActiveInBackend['simple'],
                'configurable' => $productActiveInWoocommerceNotActiveInBackend['configurable'],
                'assign' => $productActiveInWoocommerceNotActiveInBackend['assign'],
            ],
            'variable_product_miss_match' => [
                'amount' => $variableProductMissMatch['amount'],
                'products' => $variableProductMissMatch['products'],
            ],
        ];
    }

    /**
     * @return array
     */
    private function fetchBackendProducts()
    {
        $this->logger->debug('- Fetching Backend Products');

        $ShopModule = $this->api->getModule('ShopModule');

        $backend_products = [];
        $can_fetch_more = true;
        $start = 0;
        $limit = 250;
        $filters = [];

        if (StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID) && StoreKeeperOptions::get(
            StoreKeeperOptions::MAIN_CATEGORY_ID
        ) > 0) {
            $cat_id = StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID);
            $filters[] = [
                'name' => 'flat_product/category_ids__overlap',
                'multi_val' => [$cat_id],
            ];

            $this->logger->debug("Fetching with category restriction: $cat_id");
        }

        while ($can_fetch_more) {
            $response = $ShopModule->naturalSearchShopFlatProductForHooks(
                0,
                0,
                $start,
                $limit,
                [
                    [
                        'name' => 'id',
                        'dir' => 'asc',
                    ],
                ],
                $filters
            );

            $data = $response['data'];
            $count = $response['count'];
            $total = $response['total'];
            foreach ($data as $item) {
                $backend_products[$item['id']] = [
                    'type' => $item['flat_product']['product']['type'],
                    'id' => $item['id'],
                    'product_id' => $item['product_id'],
                ];
            }
            $start = $start + $limit;
            $can_fetch_more = $count >= $limit;
            if ($can_fetch_more) {
                $this->logger->debug("$start/$total fetched");
            } else {
                $this->logger->debug("$start/$total fetched, DONE!");
            }
        }

        $this->logger->debug('- Done fetching Backend Products');

        return $backend_products;
    }

    /**
     * @return array
     */
    private function fetchWooCommerceProductsForActiveCheck()
    {
        $this->logger->debug('- Fetching WooCommerce products');

        global $wpdb;
        $woocommerce_products = [];

        $sql = <<<SQL
    SELECT posts.post_status as status, meta.meta_value as shop_product_id, posts.ID as post_id
    FROM {$wpdb->prefix}posts as posts
    INNER JOIN {$wpdb->prefix}postmeta as meta
    ON posts.ID=meta.post_id
    WHERE posts.post_status IN ("publish", "pending", "draft", "auto-draft", "future", "private", "inherit")
    AND meta.meta_key="storekeeper_id"
    AND posts.post_type IN ("product", "product_variation")
SQL;

        $response = $wpdb->get_results($sql);

        foreach ($response as $woocommerce_product) {
            $woocommerce_products[$woocommerce_product->shop_product_id] = [
                'enabled' => 'publish' === $woocommerce_product->status,
                'post_id' => $woocommerce_product->post_id,
            ];
        }

        $this->logger->debug('- Fetched WooCommerce Products');

        return $woocommerce_products;
    }

    /**
     * @return array
     */
    private function formatInactiveProductsInWooCommerce($productsActiveInBackendNotActiveInWoocommerce)
    {
        $productsActiveInBackendNotActiveInWoocommerce_amount = (string) $productsActiveInBackendNotActiveInWoocommerce['amount'];
        $productsActiveInBackendNotActiveInWoocommerce_shopProductIds = implode(
            ', ',
            $productsActiveInBackendNotActiveInWoocommerce['shop_product_ids']
        );
        $productsActiveInBackendNotActiveInWoocommerce_productIds = implode(
            ', ',
            $productsActiveInBackendNotActiveInWoocommerce['product_ids']
        );

        return [
            $productsActiveInBackendNotActiveInWoocommerce_amount,
            $productsActiveInBackendNotActiveInWoocommerce_shopProductIds,
            $productsActiveInBackendNotActiveInWoocommerce_productIds,
        ];
    }

    /**
     * @return array
     */
    private function formatActiveProducts($productActiveInWoocommerceNotActiveInBackend)
    {
        $productActiveInWoocommerceNotActiveInBackend_amount = (string) $productActiveInWoocommerceNotActiveInBackend['amount'];
        $productActiveInWoocommerceNotActiveInBackend_shopProductIds = implode(
            ', ',
            $productActiveInWoocommerceNotActiveInBackend['shop_product_ids']
        );
        $productActiveInWoocommerceNotActiveInBackend_postIds = implode(
            ', ',
            $productActiveInWoocommerceNotActiveInBackend['post_ids']
        );

        return [
            $productActiveInWoocommerceNotActiveInBackend_amount,
            $productActiveInWoocommerceNotActiveInBackend_shopProductIds,
            $productActiveInWoocommerceNotActiveInBackend_postIds,
        ];
    }

    /**
     * @return string
     */
    private function formatAttributeMissMatch($variableProductMissMatch)
    {
        $variable_product_attribute_miss_match = '';
        foreach ($variableProductMissMatch['products'] as $post_id => $data) {
            $link = get_site_url(null, "/wp-admin/post.php?post=$post_id&action=edit");
            $backoffice_hash = '#shop-product/redirect/'.get_post_meta($post_id, 'storekeeper_id', true);

            // Checking if there is some variable miss matches
            $extra_variable_attributes = '  - No extra variable attributes';
            $variable_data = $data['variable']['extra'];
            if (count($variable_data) > 0) {
                $extra_variable_attributes = '';
                foreach ($variable_data as $attribute => $options) {
                    $options_string = implode(', ', $options);
                    $extra_variable_attributes .= "   - $attribute: $options_string";
                }
            }

            // Checking if there is some variation miss matches
            $extra_variation_attributes = '  - No extra variation attributes';
            $variation_data = $data['variation']['extra'];
            if (count($variation_data) > 0) {
                $extra_variation_attributes = '';
                foreach ($variation_data as $attribute => $options) {
                    $options_string = implode(', ', $options);
                    $extra_variation_attributes .= "   - $attribute: $options_string";
                }
            }

            // Check if there are any other issues.
            $other_issues = '  - No issues';
            $issue_data = $data['issue'];
            if (count($issue_data) > 0) {
                $other_issues = '';
                foreach ($issue_data as $issue) {
                    $other_issues .= "   - $issue";
                }
            }

            // Adding the product
            $variable_product_attribute_miss_match .= "{
	Variable product: {$data['name']} ($post_id) has some attribute miss match:
	- link: $link
	- backoffice hash: $backoffice_hash
	- Extra variable attributes:
	$extra_variable_attributes
	- Extra variation attributes:
	$extra_variation_attributes
	- Other issues:
	$other_issues
}";
        }

        return $variable_product_attribute_miss_match;
    }

    /**
     * @return array
     */
    private function getProductActiveInWoocommerceNotActiveInBackend()
    {
        if (!$this->formattedActiveProducts) {
            $amount = 0;
            $shop_product_ids = [];
            $post_ids = [];

            foreach ($this->woocommerce_products as $shop_product_id => $woocommerce_product) {
                if ($woocommerce_product['enabled'] && !array_key_exists($shop_product_id, $this->backend_products)) {
                    ++$amount;
                    $shop_product_ids[] = $shop_product_id;
                    $post_ids[] = $woocommerce_product['post_id'];
                }
            }

            $this->formattedActiveProducts = [
                'amount' => $amount,
                'post_ids' => $post_ids,
                'shop_product_ids' => $shop_product_ids,
            ];
        }

        return $this->formattedActiveProducts;
    }

    /**
     * @return array
     */
    private function getVariableProductIds()
    {
        $products = wc_get_products(
            [
                'status' => 'publish',
                'limit' => -1,
                'type' => ['variable'],
            ]
        );
        $ids = [];
        /**
         * @var $product \WC_Product
         */
        foreach ($products as $product) {
            $ids[] = $product->get_id();
        }

        return $ids;
    }

    /**
     * @return array
     */
    private function getVariableProductMissMatch()
    {
        if (!$this->formattedProductMissMatch) {
            $products = [];

            $variable_product_ids = $this->getVariableProductIds();
            foreach ($variable_product_ids as $variable_product_id) {
                $product = new \WC_Product_Variable($variable_product_id);
                $variation_extra = [];
                $variable_extra = [];
                $issue = [];

                $variable_attribute_map = $product->get_variation_attributes();

                // Get all assigned product below this configurable product and map its variation attributes
                $variation_attribute_map = [];
                foreach ($product->get_children() as $product_variation_id) {
                    $product_variation = new \WC_Product_Variation($product_variation_id);

                    foreach ($product_variation->get_variation_attributes() as $attribute => $attribute_value) {
                        // get_variation_attributes from a variation product prepends with attribute_, which I remove here.
                        $fixed_attribute_name = substr($attribute, strlen('attribute_'));

                        if (!array_key_exists($fixed_attribute_name, $variation_attribute_map)) {
                            $variation_attribute_map[$fixed_attribute_name] = [];
                        }
                        $variation_attribute_map[$fixed_attribute_name][] = $attribute_value;
                    }
                }

                // Loop over the variable attribute, and see if they are all in the variation map
                foreach ($variable_attribute_map as $attribute => $variable_options) {
                    if (array_key_exists($attribute, $variation_attribute_map)) {
                        // Check if there are extra items in the variable array;
                        $variation_options = $variation_attribute_map[$attribute];
                        $array_diff_variable = array_diff($variable_options, $variation_options);
                        $array_diff_variation = array_diff($variation_options, $variable_options);

                        // Check if any has a difference, if so. this means there are some extra attribute options
                        if (count($array_diff_variable) > 0) {
                            $variable_extra[$attribute] = $array_diff_variable;
                        }
                        if (count($array_diff_variable) > 0) {
                            $variation_extra[$attribute] = $array_diff_variation;
                        }
                    } else {
                        $issue[] = "Variable product with post id '$variable_product_id' has no variation products below him that are able to be sold.";
                    }
                }

                if (
                    count($variable_extra) > 0
                    || count($variation_extra) > 0
                    || count($issue) > 0
                ) {
                    $products[$variable_product_id] = [
                        'name' => $product->get_name(),
                        'issue' => $issue,
                        'variable' => ['extra' => $variable_extra],
                        'variation' => ['extra' => $variation_extra],
                    ];
                }
            }

            $this->formattedProductMissMatch = [
                'amount' => count($products),
                'products' => $products,
            ];
        }

        return $this->formattedProductMissMatch;
    }

    /**
     * @return array
     */
    private function getProductsActiveInBackendNotActiveInWoocommerce()
    {
        if (!$this->formattedInactiveProducts) {
            $amount = 0;
            $shop_product_ids = [];
            $product_ids = [];

            $simple_shop_product_ids = [];
            $simple_product_ids = [];
            $assign_shop_product_ids = [];
            $assign_product_ids = [];
            $configurable_shop_product_ids = [];
            $configurable_product_ids = [];

            foreach ($this->backend_products as $shop_product_id => $product) {
                if (!key_exists(
                    $shop_product_id,
                    $this->woocommerce_products
                ) || !$this->woocommerce_products[$shop_product_id]['enabled']) {
                    ++$amount;
                    $shop_product_ids[] = $shop_product_id;
                    $product_ids[] = $product['product_id'];

                    // deviding types
                    $type = $product['type'];

                    if ('simple' === $type) {
                        $simple_shop_product_ids[] += $product['id'];
                        $simple_product_ids[] += $product['product_id'];
                    } else {
                        if ('configurable' === $type) {
                            $configurable_shop_product_ids[] += $product['id'];
                            $configurable_product_ids[] += $product['product_id'];
                        } else {
                            if ('configurable_assign' === $type) {
                                $assign_shop_product_ids[] += $product['id'];
                                $assign_product_ids[] += $product['product_id'];
                            }
                        }
                    }
                }
            }

            $this->formattedInactiveProducts = [
                'amount' => $amount,
                'product_ids' => $product_ids,
                'shop_product_ids' => $shop_product_ids,
                'simple' => [
                    'shop_product_ids' => $simple_shop_product_ids,
                    'product_ids' => $simple_product_ids,
                ],
                'configurable' => [
                    'shop_product_ids' => $configurable_shop_product_ids,
                    'product_ids' => $configurable_product_ids,
                ],
                'assign' => [
                    'shop_product_ids' => $assign_shop_product_ids,
                    'product_ids' => $assign_product_ids,
                ],
            ];
        }

        return $this->formattedInactiveProducts;
    }
}
