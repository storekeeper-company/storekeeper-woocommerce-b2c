<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Helpers\ProductHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\WpCliHelper;
use StoreKeeper\WooCommerce\B2C\I18N;

class SyncWoocommerceFullSync extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync everything to WooCommerce.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        $description = __('Sync everything (shop info, categories, tags, attributes, featured attributes, attribute options, products, upsell products, cross-sell products) from Storekeeper Backoffice to WooCommerce.', I18N::DOMAIN);
        $orderSyncLabel = "\n## ".__('SYNCHRONIZATION SEQUENCE', I18N::DOMAIN)."\n";
        $description .= "
        $orderSyncLabel
  - Shop info (SyncWoocommerceShopInfo) (sync-woocommerce-shop-info)\n
  - Categories (SyncWoocommerceCategories) (sync-woocommerce-categories)\n
  - Tags (SyncWoocommerceTags) (sync-woocommerce-tags)\n
  - Attributes (SyncWoocommerceAttributes) (sync-woocommerce-attributes)\n
  - Featured Attributes (SyncWoocommerceFeaturedAttributes) (sync-woocommerce-featured-attributes)\n
  - Attribute Options (SyncWoocommerceAttributeOptions) (sync-woocommerce-attribute-options)\n
  - Products (SyncWoocommerceProducts) (sync-woocommerce-products)\n
  - Upsell Products (SyncWoocommerceUpsellProducts) (sync-woocommerce-upsell-products)\n
  - Cross Sell Products (SyncWoocommerceCrossSellProducts) (sync-woocommerce-cross-sell-products)";

        return $description;
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'flag',
                'name' => 'skip-products',
                'description' => __('Skip synchronization of products', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => 'skip-upsell-products',
                'description' => __('Skip synchronization of upsell products, this will save a significant amount of time when the user does not have them', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => 'skip-cross-sell-products',
                'description' => __('Skip synchronization of cross-sell products, this will save a significant amount of time when the user does not have them', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting shop information synchronization...', I18N::DOMAIN)));
            // Sync the shop info
            $this->executeSubCommand(SyncWoocommerceShopInfo::getCommandName(), [], [], true);

            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting product categories synchronization...', I18N::DOMAIN)));
            // Sync all categories
            $depth = $this->getCategoryDepth();
            for ($level = 0; $level <= $depth; ++$level) {
                WpCliHelper::attemptLineOutput(sprintf(__('Product categories level (%s)', I18N::DOMAIN), $level + 1));
                $this->executeSubCommand(SyncWoocommerceCategories::getCommandName(), [], ['level' => $level], true);
            }

            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting coupon codes synchronization...', I18N::DOMAIN)));
            // Sync coupon code
            $this->executeSubCommand(SyncWoocommerceCouponCodes::getCommandName(), [], [], true);

            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting tags synchronization...', I18N::DOMAIN)));
            // Sync the tags
            $this->executeSubCommand(SyncWoocommerceTags::getCommandName(), [], [], true);

            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting product attributes synchronization...', I18N::DOMAIN)));
            // Sync the attributes
            $this->executeSubCommand(SyncWoocommerceAttributes::getCommandName(), [], [], true);

            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting featured attributes synchronization...', I18N::DOMAIN)));
            // Sync the featured attributes
            $this->executeSubCommand(SyncWoocommerceFeaturedAttributes::getCommandName(), [], [], true);

            // Sync the attribute options(with pagination)
            WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting attribute options synchronization...', I18N::DOMAIN)));
            $attribute_options_totals = $this->getAmountOfAttributeOptionsInBackend();
            $this->executeSubCommand(
                SyncWoocommerceAttributeOptions::getCommandName(),
                [],
                [
                    'total-amount' => $attribute_options_totals,
                ],
                true
            );

            // Sync the products(with pagination)
            if (!array_key_exists('skip-products', $assoc_arguments)) {
                WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting products synchronization...', I18N::DOMAIN)));
                $product_totals = $this->getAmountOfProductsInBackend();
                $this->executeSubCommand(
                    SyncWoocommerceProducts::getCommandName(),
                    [],
                    [
                        'total-amount' => $product_totals,
                    ],
                    true
                );
            }

            // Sync the cross/upsell(with pagination)
            $sync_upsell = !array_key_exists('skip-upsell-products', $assoc_arguments);
            $sync_cross_sell = !array_key_exists('skip-cross-sell-products', $assoc_arguments);

            if ($sync_upsell || $sync_cross_sell) {
                // Get the total amount of products that should be sync
                $cross_up_sell_product_totals = ProductHelper::getAmountOfProductsInWooCommerce();
                $args = [
                    'total-amount' => $cross_up_sell_product_totals,
                ];

                if ($sync_upsell) {
                    WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting upsell products synchronization...', I18N::DOMAIN)));
                    // Sync the upsell
                    $this->executeSubCommand(SyncWoocommerceUpsellProducts::getCommandName(), [], $args, true);
                }

                if ($sync_cross_sell) {
                    WpCliHelper::attemptLineOutput(WpCliHelper::setYellowOutputColor(__('Starting cross-sell products synchronization...', I18N::DOMAIN)));
                    // Sync the cross sell
                    $this->executeSubCommand(SyncWoocommerceCrossSellProducts::getCommandName(), [], $args, true);
                }
            }
        }
    }

    /**
     * Fetch the category depth from the backend.
     */
    private function getCategoryDepth(): int
    {
        $response = $this->api->getModule('ShopModule')->listTranslatedCategoryForHooks(
            0,
            0,
            1,
            [
                [
                    'name' => 'category_tree/level',
                    'dir' => 'DESC',
                ],
            ]
        );
        $level = 0;
        if (!empty($response['data'])) {
            $level = $response['data'][0]['category_tree']['level'];
        }

        return $level;
    }
}
