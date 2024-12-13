<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\WebService;

use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class LocationsEndpoint extends \WP_REST_Controller
{

    /**
     * StoreKeeper route base URL
     */
    public const ROUTE = 'locations';

    /**
     * Location route namespace
     */
    protected $namespace;

    /**
     * Initialize
     *
     * @param string $namespace
     */
    public function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @inheritDoc
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            self::ROUTE,
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_items'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                    'args' => $this->get_collection_params()
                ],
                'schema' => [$this, 'get_item_schema'],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . self::ROUTE . '/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __('Unique identifier for the location.', I18N::DOMAIN),
                        'type' => 'integer'
                    ]
                ],
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_item'],
                    'permission_callback' => [$this, 'get_item_permissions_check']
                ],
                'schema' => [$this, 'get_public_item_schema']
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function get_item($request) {
        try {
            $location = LocationModel::get($request['id']);
            if (null === $location) {
                return new \WP_Error(
                    'rest_location_invalid_id',
                    __('Invalid user ID.', I18N::DOMAIN),
                    ['status' => 404]
                );
            }
        } catch (\Exception $e) {
            return new \WP_Error(
                'rest_location_invalid_data',
                $e->getMessage(),
                ['status' => 500]
            );
        }

        $location = $this->prepare_item_for_response($location, $request);
        $response = rest_ensure_response($location);

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function get_items($request) {
        global $wpdb;

        $data  = [];

        $select = LocationModel::getSelectHelper()
            ->cols(array_keys(LocationModel::getFieldsWithRequired()));

        if ($request->has_param('search') && '' !== ($search = sanitize_text_field($request->get_param('search')))) {
            $select->where('name LIKE :search');
            $select->bindValue('search', '%' . $search . '%');
        }

        if ($request->has_param('active')) {
            $select->where('is_active = :is_active');
            $select->bindValue('is_active', !!$request->get_param('active'));
        }

        if ($request->has_param('orderby') && $request->has_param('order') &&
            '' !== ($orderBy = sanitize_text_field($request->get_param('orderby'))) &&
            '' !== ($orderDirection = sanitize_text_field($request->get_param('order')))) {
            $select->orderBy(["$orderBy $orderDirection"]);
        }

        $page = $this->get_collection_params()['page']['default'];
        $perPage = $this->get_collection_params()['per_page']['default'];

        if ($request->has_param('page')) {
            $page = absint($request->get_param('page')) ?: $page;
        }
        if ($request->has_param('per_page')) {
            $perPage = absint($request->get_param('per_page')) ?: $perPage;
        }

        $select->limit($perPage);

        if (1 < $page) {
            $select->offset($perPage * ($page - 1));
        }

        $query = LocationModel::prepareQuery($select);
        foreach ($wpdb->get_results($query, ARRAY_A) as $location) {
            $location = $this->prepare_item_for_response($location, $request);
            $data[$location[LocationModel::PRIMARY_KEY]] = $this->prepare_response_for_collection($location);

            unset($location);
        }

        return rest_ensure_response($data);
    }

    /**
     * @inheritDoc
     */
    public function prepare_item_for_response($location, $request)
    {
        $location[LocationModel::PRIMARY_KEY] = (int) $location[LocationModel::PRIMARY_KEY];
        $location['storekeeper_id'] = (int) $location['storekeeper_id'];
        $location['is_default'] = (bool) (int) $location['is_default'];
        $location['is_active'] = (bool) (int) $location['is_active'];

        return $location;
    }

    /**
     * @inheritDoc
     */
    public function get_items_permissions_check($request) {
        return $this->permissions_check($request);
    }

        /**
     * @inheritDoc
     */
    public function get_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    /**
     * @inheritDoc
     */
    public function get_collection_params() {
        $queryParams = parent::get_collection_params();

        unset($queryParams['context']);

        $queryParams['active'] = [
            'default' => true,
            'description' => __('Limit results to those matching active filter.', I18N::DOMAIN),
            'type' => 'boolean'
        ];

        $queryParams['order'] = [
            'default' => 'asc',
            'description' => __('Order sort attribute ascending or descending.', I18N::DOMAIN),
            'enum' => ['asc', 'desc'],
            'type' => 'string'
        ];

        $queryParams['orderby'] = [
            'default' => 'name',
            'description' => __('Sort collection by user attribute.', I18N::DOMAIN),
            'enum' => [
                'id',
                'storekeeper_id',
                'name',
                'is_default',
                'is_active'
            ],
            'type' => 'string'
        ];

        return $queryParams;
    }

    /**
     * Check to see if the current user is allowed to use this endpoint.
     *
     * @return bool|\WP_Error
     */
    protected function permissions_check($request)
    {
        if (!current_user_can(RoleHelper::CAP_CONTENT_BUILDER)) {
            return new \WP_Error(
                'rest_forbidden_context',
                __('Sorry, you are not allowed to list locations.', I18N::DOMAIN),
                ['status' => rest_authorization_required_code()]
            );
        }
        return true;
    }
}
