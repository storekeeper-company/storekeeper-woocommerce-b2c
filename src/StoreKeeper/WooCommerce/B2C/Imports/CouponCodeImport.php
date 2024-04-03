<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;
use WC_Coupon;

class CouponCodeImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;
    public const SK_COUPON_TYPE_PERCENTAGE = 'percent';
    public const SK_COUPON_TYPE_FIXED_ORDER = 'fixed_order';

    public const WC_COUPON_TYPE_FIXED_CARD = 'fixed_cart';
    public const WC_COUPON_TYPE_PERCENT = 'percent';

    public const SK_TO_WC_TYPE_MAP = [
        self::SK_COUPON_TYPE_FIXED_ORDER => self::WC_COUPON_TYPE_FIXED_CARD,
        self::SK_COUPON_TYPE_PERCENTAGE => self::WC_COUPON_TYPE_PERCENT,
    ];

    public const WC_POST_TYPE_COUPON_CODE = 'shop_coupon';

    /**
     * @var int
     */
    protected $storekeeper_id = 0;

    /**
     * @var string
     */
    protected $code;

    /**
     * CouponCodeImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->code = key_exists('code', $settings) ? (int) $settings['code'] : null;
        unset($settings['code']);
        unset($settings['storekeeper_id']);
        parent::__construct($settings);
    }

    public static function getCouponCodeDateFormatted(?\DateTime $date): ?string
    {
        return $date instanceof \DateTimeInterface ? date_format($date, 'Y-m-d') : null;
    }

    protected function getModule(): string
    {
        return 'ShopModule';
    }

    protected function getFunction(): string
    {
        return 'listCouponCodesForHook';
    }

    protected function getFilters(): array
    {
        $filters = [];

        if ($this->storekeeper_id > 0) {
            $filters[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $filters;
    }

    /**
     * @return bool|string|null
     */
    protected function getLanguage()
    {
        return null;
    }

    public static function getCouponCodeByStorekeeperId($storekeeperId): ?\WP_Post
    {
        $couponCodes = get_posts(
            [
                'post_type' => self::WC_POST_TYPE_COUPON_CODE,
                'number' => 1,
                'meta_key' => 'storekeeper_id',
                'meta_value' => $storekeeperId,
                'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
            ]
        );

        WordpressExceptionThrower::throwExceptionOnWpError($couponCodes);

        $couponCode = current($couponCodes);

        if ($couponCode) {
            return $couponCode;
        }

        return null;
    }

    protected function processItem(Dot $dotObject, array $options = []): ?int
    {
        $couponCode = self::getCouponCodeByStorekeeperId($dotObject->get('id'));

        // Check if the coupon exists
        if (empty($couponCode)) {
            $this->createCouponCode($dotObject);
        } else {
            $this->updateCouponCode($couponCode, $dotObject);
        }

        return null;
    }

    protected function createCouponCode(Dot $dotObject)
    {
        if (true === $dotObject->get('active')) {
            $coupon = new \WC_Coupon();

            $this->applyCouponProperties($coupon, $dotObject);

            $coupon_id = $coupon->save();

            update_post_meta($coupon_id, 'storekeeper_id', $dotObject->get('id'));
        }
    }

    protected function updateCouponCode(\WP_Post $couponCode, Dot $dotObject)
    {
        if (isset($couponCode->ID)) {
            $couponId = $couponCode->ID;

            if (false === $dotObject->get('active')) {
                wp_trash_post($couponId);
            } else {
                $coupon = new \WC_Coupon($couponId);

                $this->applyCouponProperties($coupon, $dotObject);

                $coupon->save();
            }
        }
    }

    protected function applyCouponProperties(\WC_Coupon $coupon, Dot $data): void
    {
        // Default
        $coupon->set_code($data->get('code'));
        $coupon->set_description($data->get('title'));

        // General
        $this->applyType($coupon, $data);
        $this->applyAmount($coupon, $data);
        $coupon->set_free_shipping($data->get('free_shipping', false));
        $this->applyExpireDate($coupon, $data);

        // Usage limitations
        $coupon->set_minimum_amount($data->get('min_order_value_wt'));
        $coupon->set_maximum_amount($data->get('max_order_value_wt'));
        $coupon->set_individual_use(!$data->get('allow_with_other_codes', true));
        $coupon->set_exclude_sale_items(!$data->get('allow_on_discounted_products', false));
        $this->applyIncludedCategories($coupon, $data);
        $this->applyExcludedCategories($coupon, $data);
        $coupon->set_email_restrictions($data->get('allowed_emails', []));

        // Limit
        $coupon->set_usage_limit($data->get('times_it_can_be_used_per_shop'));
        $coupon->set_usage_limit_per_user($data->get('times_per_customer_per_shop'));
    }

    protected function applyType(\WC_Coupon &$coupon, Dot $data): void
    {
        $wcType = self::getCouponType($data);
        $coupon->set_discount_type($wcType);
    }

    protected function applyAmount(\WC_Coupon &$coupon, Dot $data): void
    {
        $amount = self::getCouponAmount($data);
        $coupon->set_amount($amount);
    }

    protected function applyIncludedCategories(\WC_Coupon &$coupon, Dot $data): void
    {
        $categoryIds = self::getCouponIncludedCategoryIds($data);
        $coupon->set_product_categories($categoryIds);
    }

    protected function applyExcludedCategories(\WC_Coupon &$coupon, Dot $data): void
    {
        $categoryIds = self::getCouponExcludedCategoryIds($data);
        $coupon->set_excluded_product_categories($categoryIds);
    }

    protected function applyExpireDate(\WC_Coupon &$coupon, Dot $data): void
    {
        $date = self::getCouponExpireDate($data);
        $coupon->set_date_expires($date);
    }

    private static function getCategoryTermIds(array $skCategoryIds = []): array
    {
        $categoryIds = [];

        foreach ($skCategoryIds as $id) {
            $term = Categories::getCategoryById($id);
            if ($term instanceof \WP_Term) {
                $categoryIds[] = $term->term_id;
            }
        }

        return $categoryIds;
    }

    public static function getCouponExpireDate(Dot $data)
    {
        $dateString = $data->get('date_expires_excl');
        if ($dateString) {
            $date = new \DateTime($dateString);

            return self::getCouponCodeDateFormatted($date);
        }

        return null;
    }

    public static function getSanitizedFilteredEmails(array $emails = [])
    {
        // Taken from the method `wc_coupon::set_email_restrictions` method;
        return array_filter(array_map('sanitize_email', array_map('strtolower', (array) $emails)));
    }

    public static function getCouponIncludedCategoryIds(Dot $data): array
    {
        $skCategoryIds = $data->get('included_category_ids', []);

        return self::getCategoryTermIds($skCategoryIds);
    }

    public static function getCouponExcludedCategoryIds(Dot $data): array
    {
        $skCategoryIds = $data->get('excluded_category_ids', []);

        return self::getCategoryTermIds($skCategoryIds);
    }

    public static function getCouponAmount(Dot $data)
    {
        if (self::SK_COUPON_TYPE_PERCENTAGE === $data->get('type')) {
            $amount = $data->get('percent');
        } else {
            $amount = $data->get('fixed_value_wt');
        }

        return $amount;
    }

    public static function getCouponType(Dot $data): string
    {
        $skType = $data->get('type');
        $wcType = self::SK_TO_WC_TYPE_MAP[$skType];
        if (empty($wcType)) {
            throw new \Exception("Unknown StoreKeeper discount type: $skType");
        }

        return $wcType;
    }

    protected function afterRun(array $storeKeeperIds)
    {
        $this->makeMissingCouponPrivate();
        parent::afterRun($storeKeeperIds);
    }

    protected function makeMissingCouponPrivate(): void
    {
        if ($this->storekeeper_id > 0 && 0 === $this->total_fetched) {
            $coupon = new \WC_Coupon($this->code);
            if ($coupon) {
                $post = get_post($coupon->get_id());
                if ($post instanceof \WP_Post) {
                    $post->post_status = 'private';
                }
            }
        }
    }

    protected function getImportEntityName(): string
    {
        return __('coupon codes', I18N::DOMAIN);
    }
}
