<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCouponCodes;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class SyncWoocommerceCouponCodeTest extends AbstractTest
{
    public const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-coupon-codes';
    public const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listCouponCodesForHook.success.602c86cc5cf0f.json';

    public function testRun()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $originalData = $this->getOriginalData();

        $this->assertEquals(
            0,
            count($this->getCurrentCoupons()),
            'Test was not ran in an empty environment'
        );

        $this->runner->execute(SyncWoocommerceCouponCodes::getCommandName());

        $this->assertEquals(
            count($originalData),
            count($this->getCurrentCoupons()),
            'Did not imported the correct amount of coupons'
        );

        foreach ($originalData as $data) {
            $original = new Dot($data);
            $coupon = $this->getCoupon($original);
            $this->assertCouponCode($original, $coupon);
        }
    }

    protected function getCouponByStoreKeeperID($storekeeper_id): ?\WC_Coupon
    {
        $posts = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'shop_coupon',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $storekeeper_id,
                ]
            )
        );

        if (1 === count($posts)) {
            $post = current($posts);

            return new \WC_Coupon($post->ID);
        }

        return null;
    }

    protected function getOriginalData()
    {
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $couponData = $file->getReturn()['data'];

        return $couponData;
    }

    /**
     * @return int[]|\WP_Post[]
     */
    protected function getCurrentCoupons(): array
    {
        return get_posts(
            [
                'post_type' => 'shop_coupon',
                'numberposts' => -1, // all
            ]
        );
    }

    protected function getCoupon(Dot $original): ?\WC_Coupon
    {
        $storekeeperId = $original->get('id');

        $coupon = $this->getCouponByStoreKeeperID($storekeeperId);
        $this->assertNotNull(
            $coupon,
            "Unable to find coupon with storekeeper id $storekeeperId"
        );

        return $coupon;
    }
}
