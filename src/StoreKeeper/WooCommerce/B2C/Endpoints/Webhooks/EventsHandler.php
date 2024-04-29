<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressRestRequestWrapper;

class EventsHandler
{
    use LoggerAwareTrait;

    private $module;

    private $backref_data;

    private $id;

    public function getModule()
    {
        return $this->module;
    }

    public function getBackrefData()
    {
        return $this->backref_data;
    }

    public function setBackrefData($backref_data)
    {
        $this->backref_data = $backref_data;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setModule($module)
    {
        $this->module = $module;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @var WordpressRestRequestWrapper
     */
    private $request;

    /**
     * InitHandler constructor.
     */
    public function __construct(WordpressRestRequestWrapper $request)
    {
        $this->setLogger(new NullLogger());
        $this->request = $request;
    }

    public function run()
    {
        if (!is_null($this->request->getPayloadParam('backref'))) {
            $this->updateBackref($this->request->getPayloadParam('backref'));
        }
        if (!is_null($this->request->getPayloadParam('events'))) {
            $this->processEvents($this->request->getPayloadParam('events'));

            return true;
        }

        return false;
    }

    private function updateBackref($backref)
    {
        $regex = '/(\w+\:\:\w+)\(/';

        preg_match($regex, $backref, $matches);

        $this->setModule($matches[1]);
        $data = self::backRefToData($backref);
        if (isset($data['id'])) {
            $this->setId($data['id']);
        }
        $this->setBackrefData($data);
    }

    /**
     * gets backrefference based on data.
     *
     * @return array
     */
    public static function backRefToData($backref)
    {
        $backref = trim(substr($backref, strpos($backref, '(')), '()');
        $data = [];
        $pieces = explode(',', $backref);
        foreach ($pieces as $piece) {
            $p = strpos($piece, '=');
            $k = substr($piece, 0, $p);
            $v = trim(substr($piece, $p + 1), '\'"');
            $data[$k] = $v;
        }

        return $data;
    }

    private function processFullSyncEvents($events)
    {
        if (!is_array($events)) {
            return;
        }

        $taskData = ['storekeeper_id' => (int) $this->getId()];
        $storekeeper_id = (int) $this->getId();
        $categoryType = $this->getCategoryType($storekeeper_id) ?? $this->getCategoryTypeFromWordpress($storekeeper_id);
        $module = $this->getModule();

        foreach ($events as $id => $event) {
            $details = $event['details'] ?? [];
            $scope = $event['scope'] ?? '';
            $eventType = $event['event'];
            $fullEventType = "$module::$eventType";

            if (empty($scope) && is_string($details)) {
                $scope = $details;
            }

            $this->handleProductEvents($fullEventType, $taskData, $scope);
            $this->handleCategoryEvents($fullEventType, $taskData, $categoryType);
            $this->handleCouponCodeEvents($fullEventType, $taskData, $details);
            $this->handleOrderEvents($fullEventType, $taskData, $details);
            $this->handleFeaturedAttributeEvents($fullEventType, $details);
            $this->handleMenuItemEvents($fullEventType, $taskData);
            $this->handleSiteRedirectEvents($fullEventType, $taskData);
            $this->handleShippingMethodEvents($fullEventType, $taskData);
        }
    }

    private function processProductOnlyEvents($events)
    {
        if (!is_array($events)) {
            return;
        }

        $taskData = ['storekeeper_id' => (int) $this->getId()];
        $storekeeper_id = (int) $this->getId();
        $categoryType = $this->getCategoryType($storekeeper_id) ?? $this->getCategoryTypeFromWordpress($storekeeper_id);
        $module = $this->getModule();

        foreach ($events as $id => $event) {
            $details = $event['details'] ?? [];
            $scope = $event['scope'] ?? '';
            $eventType = $event['event'];
            $fullEventType = "$module::$eventType";

            if (empty($scope) && is_string($details)) {
                $scope = $details;
            }

            $this->handleProductEvents($fullEventType, $taskData, $scope);
            $this->handleCategoryEvents($fullEventType, $taskData, $categoryType);
            $this->handleCouponCodeEvents($fullEventType, $taskData, $details);
            $this->handleFeaturedAttributeEvents($fullEventType, $details);
            $this->handleMenuItemEvents($fullEventType, $taskData);
            $this->handleSiteRedirectEvents($fullEventType, $taskData);
        }
    }

    private function processOrderOnlyEvents($events)
    {
        if (!is_array($events)) {
            return;
        }

        $taskData = ['storekeeper_id' => (int) $this->getId()];
        $module = $this->getModule();

        foreach ($events as $id => $event) {
            $details = $event['details'] ?? [];
            $eventType = $event['event'];

            $fullEventType = "$module::$eventType";

            $this->handleOrderEvents($fullEventType, $taskData, $details);
            $this->handleProductStockEvents($fullEventType, $taskData);
            $this->handleShippingMethodEvents($fullEventType, $taskData);
        }
    }

    private function handleShippingMethodEvents(string $eventType, array $taskData): void
    {
        if (BackofficeCore::isShippingMethodUsed()) {
            switch ($eventType) {
                case 'ShippingModule::ShippingMethod::created':
                case 'ShippingModule::ShippingMethod::updated':
                    TaskHandler::scheduleTask(TaskHandler::SHIPPING_METHOD_IMPORT, $this->getId(), $taskData);
                    break;
                case 'ShippingModule::ShippingMethod::deleted':
                    TaskHandler::scheduleTask(TaskHandler::SHIPPING_METHOD_DELETE, $this->getId(), $taskData);
                    break;
            }
        }
    }

    private function handleProductEvents(string $eventType, array $taskData, string $scope): void
    {
        switch ($eventType) {
            // ShopModule::ShopProduct
            case 'ShopModule::ShopProduct::updated':
                $metaData = [
                    'scope' => $scope,
                ];
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_UPDATE, $this->getId(), array_merge($taskData, $metaData));
                break;
            case 'ShopModule::ShopProduct::created':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_IMPORT, $this->getId(), $taskData);
                break;
            case 'ShopModule::ShopProduct::deleted':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_DELETE, $this->getId(), $taskData);
                break;
            case 'ShopModule::ShopProduct::deactivated':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_DEACTIVATED, $this->getId(), $taskData, true);
                break;
            case 'ShopModule::ShopProduct::activated':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_ACTIVATED, $this->getId(), $taskData, true);
                break;
        }
    }

    private function handleProductStockEvents(string $eventType, array $taskData): void
    {
        switch ($eventType) {
            // ShopModule::ShopProduct
            // For product stock only
            case 'ShopModule::ShopProduct::created':
            case 'ShopModule::ShopProduct::updated':
            case 'ShopModule::ShopProduct::activated':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_STOCK_UPDATE, $this->getId(), $taskData);
                break;
        }
    }

    private function handleCategoryEvents(string $eventType, array $taskData, string $categoryType): void
    {
        switch ($eventType) {
            // BlogModule::Category
            case 'BlogModule::Category::updated':
            case 'BlogModule::Category::created':
                if ('category' === $categoryType) {
                    TaskHandler::scheduleTask(TaskHandler::CATEGORY_IMPORT, $this->getId(), $taskData);
                }
                if ('label' === $categoryType) {
                    TaskHandler::scheduleTask(TaskHandler::TAG_IMPORT, $this->getId(), $taskData);
                }
                break;
            case 'BlogModule::Category::deleted':
                if ('category' === $categoryType) {
                    TaskHandler::scheduleTask(TaskHandler::CATEGORY_DELETE, $this->getId(), $taskData);
                }
                if ('label' === $categoryType) {
                    TaskHandler::scheduleTask(TaskHandler::TAG_DELETE, $this->getId(), $taskData);
                }
                break;
        }
    }

    private function handleCouponCodeEvents(string $eventType, array $taskData, $details): void
    {
        switch ($eventType) {
            // ShopModule::CouponCode
            case 'ShopModule::CouponCode::updated':
            case 'ShopModule::CouponCode::created':
                $taskData = array_merge(
                    $taskData,
                    [
                        'code' => $details['code'] ?? null,
                    ]
                );
                TaskHandler::scheduleTask(TaskHandler::COUPON_CODE_IMPORT, $this->getId(), $taskData);
                break;
            case 'ShopModule::CouponCode::deleted':
                TaskHandler::scheduleTask(TaskHandler::COUPON_CODE_DELETE, $this->getId(), $taskData);
                break;
        }
    }

    private function handleOrderEvents(string $eventType, array $taskData, $details): void
    {
        switch ($eventType) {
            // ShopModule::Order
            case 'ShopModule::Order::updated':
                $metaData = [
                    'old_order' => json_encode($details['old_order'], JSON_THROW_ON_ERROR),
                    'order' => json_encode($details['order'], JSON_THROW_ON_ERROR),
                ];
                TaskHandler::scheduleTask(
                    TaskHandler::ORDERS_IMPORT,
                    $this->getId(),
                    array_merge($taskData, $metaData),
                    true
                );
                break;
            case 'ShopModule::Order::deleted':
                TaskHandler::scheduleTask(TaskHandler::PRODUCT_DELETE, $this->getId(), $taskData);
                break;
            case 'ShopModule::Order::payment_status_change':
                // We only handle expired because user gets redirected to checkout if they click cancel button on payment, and they can try again.
                if (isset($details['payment']['status']) && 'expired' === $details['payment']['status'] && !empty($details['order'])) {
                    $metaData = [
                        'order' => json_encode($details['order'], JSON_THROW_ON_ERROR),
                        'payment' => json_encode($details['payment'], JSON_THROW_ON_ERROR),
                    ];
                    TaskHandler::scheduleTask(
                        TaskHandler::ORDERS_PAYMENT_STATUS_CHANGE,
                        $this->getId(),
                        array_merge($taskData, $metaData),
                        true
                    );
                }
                break;
        }
    }

    private function handleFeaturedAttributeEvents(string $eventType, $details): void
    {
        switch ($eventType) {
            // ProductsModule::FeaturedAttribute
            case 'ProductsModule::FeaturedAttribute::updated':
                $alias = $details['alias'];
                FeaturedAttributeOptions::setAttribute(
                    $alias,
                    $details['attribute_id'],
                    $details['attribute']['name']
                );
                break;
            case 'ProductsModule::FeaturedAttribute::deleted':
                $alias = $details['alias'];
                FeaturedAttributeOptions::deleteAttribute($alias);
                break;
        }
    }

    private function handleMenuItemEvents(string $eventType, array $taskData): void
    {
        switch ($eventType) {
            // BlogModule::MenuItem
            case 'BlogModule::MenuItem::updated':
            case 'BlogModule::MenuItem::created':
                TaskHandler::scheduleTask(TaskHandler::MENU_ITEM_IMPORT, $this->getId(), $taskData);
                break;
            case 'BlogModule::MenuItem::deleted':
                TaskHandler::scheduleTask(TaskHandler::MENU_ITEM_DELETE, $this->getId(), $taskData);
                break;
        }
    }

    private function handleSiteRedirectEvents(string $eventType, array $taskData): void
    {
        switch ($eventType) {
            // BlogModule::SiteRedirect
            case 'BlogModule::SiteRedirect::updated':
            case 'BlogModule::SiteRedirect::created':
                TaskHandler::scheduleTask(TaskHandler::REDIRECT_IMPORT, $this->getId(), $taskData);
                break;
            case 'BlogModule::SiteRedirect::deleted':
                TaskHandler::scheduleTask(TaskHandler::REDIRECT_DELETE, $this->getId(), $taskData);
                break;
        }
    }

    private function processEvents($events)
    {
        try {
            if (StoreKeeperOptions::SYNC_MODE_FULL_SYNC === StoreKeeperOptions::getSyncMode()) {
                $this->processFullSyncEvents($events);
            }
            if (StoreKeeperOptions::SYNC_MODE_ORDER_ONLY === StoreKeeperOptions::getSyncMode()) {
                $this->processOrderOnlyEvents($events);
            }
            if (StoreKeeperOptions::SYNC_MODE_PRODUCT_ONLY === StoreKeeperOptions::getSyncMode()) {
                $this->processProductOnlyEvents($events);
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Failed to handle events',
                [
                    'exception' => BaseException::getAsString($exception),
                    'events' => $events,
                ]
            );
        }
    }

    /**
     * @return bool
     *
     * @throws WordpressException
     */
    private function getCategoryTypeFromWordpress($storekeeper_id)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $storekeeper_id,
                ]
            )
        );

        $is_tag = false;
        if (1 === count($labels)) {
            $is_tag = true;
        }

        $is_cat = false;
        if (!$is_tag) {
            $is_cat = (bool) Categories::getCategoryById($storekeeper_id);
        }

        if ($is_tag) {
            return 'label';
        } else {
            if ($is_cat) {
                return 'category';
            } else {
                return false;
            }
        }
    }

    /**
     * @return bool|string|null
     */
    private function getCategoryType($storekeeper_id)
    {
        try {
            $go_api = StoreKeeperApi::getApiByAuthName();
            $category = $go_api->getModule('BlogModule')->getCategory($storekeeper_id, false);
            $categoryType = $category['category_type'];
            if (
                'ProductsModule' === $categoryType['module_name']
                && 'Product' === $categoryType['alias']
            ) {
                return 'category';
            } else {
                if (
                    'ProductsModule' === $categoryType['module_name']
                    && 'Label' === $categoryType['alias']
                ) {
                    return 'label';
                }
            }
        } catch (GeneralException $exception) {
            return false;
        } catch (\Exception $exception) {
            return null;
        }

        return false;
    }
}
