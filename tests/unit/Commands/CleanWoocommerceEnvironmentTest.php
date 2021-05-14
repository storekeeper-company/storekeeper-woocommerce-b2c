<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\CleanWoocommerceEnvironment;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFullSync;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use WC_Helper_Coupon;
use WC_Helper_Order;

class CleanWoocommerceEnvironmentTest extends AbstractTest
{
    const FULL_SYNC_DIR = 'commands/full-sync';

    public function testCleaning()
    {
        $this->initApiConnection();

        $this->mockMediaFromDirectory(self::FULL_SYNC_DIR.'/media');
        $this->mockApiCallsFromDirectory(self::FULL_SYNC_DIR);

        // Create 5 orders
        WC_Helper_Order::create_order();
        WC_Helper_Order::create_order();
        WC_Helper_Order::create_order();
        WC_Helper_Order::create_order();
        WC_Helper_Order::create_order();

        // Create 3 coupons because they are not in the fullSync data
        WC_Helper_Coupon::create_coupon();
        WC_Helper_Coupon::create_coupon();
        WC_Helper_Coupon::create_coupon();

        $this->runner->execute(SyncWoocommerceFullSync::getCommandName());

        /*
         * Check if there are even any items
         */
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getTagTerms(),
            'No tags/labels imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getCouponIds(),
            'No coupons imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getAttributeValueTerms(),
            'No attribute options imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getAttributeValueAttachmentIds(),
            'No attribute option images imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getAttributeIds(),
            'No attributes imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getCategoryTerms(),
            'No categories imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getCategoryAttachmentIds(),
            'No category images imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getProductIds(),
            'No products imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getProductAttachmentIds(),
            'No product images imported'
        );
        $this->assertNotCount(
            0,
            CleanWoocommerceEnvironment::getOrderIds(),
            'No orders created'
        );
        $this->assertNotEquals(
            0,
            TaskModel::count(),
            'No tasks created'
        );
        $this->assertNotEquals(
            0,
            WebhookLogModel::count(),
            'No web hook logs created'
        );

        /*
         * `yes` and `silent` are passed because WP_CLI is for some reason not in the CLEAN command
         */
        $this->runner->execute(
            CleanWoocommerceEnvironment::getCommandName(),
            [],
            [
                'yes' => true,
                'silent' => true,
            ]
        );

        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getTagTerms(),
            'No tags/labels removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getCouponIds(),
            'No coupons removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getAttributeValueTerms(),
            'No attribute options removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getAttributeValueAttachmentIds(),
            'No attribute option images removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getAttributeIds(),
            'No attributes removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getCategoryTerms(),
            'No categories removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getCategoryAttachmentIds(),
            'No category images removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getProductIds(),
            'No products removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getProductAttachmentIds(),
            'No product images removed'
        );
        $this->assertCount(
            0,
            CleanWoocommerceEnvironment::getOrderIds(),
            'No orders removed'
        );
        $this->assertEquals(
            0,
            TaskModel::count(),
            'No tasks removed'
        );
        $this->assertEquals(
            0,
            WebhookLogModel::count(),
            'No web hook logs removed'
        );
    }
}
