<?php

namespace StoreKeeper\WooCommerce\B2C\Objects;

use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Exceptions\EmailIsAdminUserException;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class ShopCustomer extends \WC_Customer
{
    public const CONTEXT_EDIT = 'edit';

    /**
     * Role names are based on WP user roles.
     *
     * @see https://github.com/WordPress/wordpress-develop/blob/master/tests/phpunit/tests/user/capabilities.php
     */
    public const CUSTOMER_ROLE_NAME = 'customer';
    public const SUBSCRIBER_ROLE_NAME = 'subscriber';
    public const ADMINISTRATOR_ROLE_NAME = 'administrator';
    public const EDITOR_ROLE_NAME = 'editor';
    public const AUTHOR_ROLE_NAME = 'author';
    public const CONTRIBUTOR_ROLE_NAME = 'contributor';

    public const VALID_ROLES = [
        self::CUSTOMER_ROLE_NAME,
        self::SUBSCRIBER_ROLE_NAME,
    ];

    public const INVALID_ROLES = [
        self::ADMINISTRATOR_ROLE_NAME,
        self::EDITOR_ROLE_NAME,
        self::AUTHOR_ROLE_NAME,
        self::CONTRIBUTOR_ROLE_NAME,
    ];
    /* @var ApiWrapper */
    private $api;

    protected $data = [
        'date_created' => null,
        'date_modified' => null,
        'email' => '',
        'first_name' => '',
        'last_name' => '',
        'display_name' => '',
        'role' => 'customer',
        'username' => '',
        'billing' => [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
            'email' => '',
            'phone' => '',
        ],
        'shipping' => [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
        ],
        'is_paying_customer' => false,
    ];

    /**
     * GOCustomer constructor.
     *
     * @param int  $data
     * @param bool $is_session
     *
     * @throws \Exception
     */
    public function __construct($data = 0, $is_session = false)
    {
        parent::__construct($data, $is_session);
    }

    protected function getApi()
    {
        if (is_null($this->api)) {
            $this->api = StoreKeeperApi::getApiByAuthName();
        }

        return $this->api;
    }

    /**
     * @return bool
     *
     * @throws EmailIsAdminUserException
     */
    public function is_customer_email_known()
    {
        $email = $this->get_email(self::CONTEXT_EDIT);
        if (!empty($email)) {
            try {
                $this->getApi()->getModule('ShopModule')->findShopCustomerBySubuserEmail(['email' => $email]);

                return true;
            } catch (\Throwable $exception) {
                // Email exists but as admin
                if ('ShopModule::EmailIsAdminUser' === $exception->getApiExceptionClass()) {
                    throw new EmailIsAdminUserException($exception->getMessage());
                }

                // Customer not found in the backend.
            }
        }

        return false;
    }

    public static function isRoleValid(string $role): bool
    {
        return in_array($role, self::VALID_ROLES);
    }

    public function sync_customer_to_manage()
    {
        if (!empty($this->has_storekeeper_id()) || !self::isRoleValid($this->get_role())) {
            return $this->get_storekeeper_id();
        }

        $call_data = [
            'relation' => [
                'contact_person' => [
                    'familyname' => empty($this->get_last_name('edit')) ? null : $this->get_last_name('edit'),
                    'firstname' => empty($this->get_first_name('edit')) ? null : $this->get_first_name('edit'),
                    'ismale' => true,
                ],
                'contact_set' => [
                    'email' => $this->get_email('edit'),
                    'phone' => $this->get_billing_phone('edit'),
                ],
                'contact_address' => [
                    'country_iso2' => empty($this->get_shipping_country('edit')) ? null : $this->get_shipping_country(
                        'edit'
                    ),
                    'state' => empty($this->get_shipping_state('edit')) ? null : $this->get_shipping_state('edit'),
                    'city' => empty($this->get_shipping_city('edit')) ? null : $this->get_shipping_city('edit'),
                    'zipcode' => empty($this->get_shipping_postcode('edit')) ? null : $this->get_shipping_postcode(
                        'edit'
                    ),
                    'street' => $this->get_full_shipping_address('edit'),
                ],
                'address_billing' => [
                    'country_iso2' => empty($this->get_billing_country('edit')) ? null : $this->get_billing_country(
                        'edit'
                    ),
                    'state' => empty($this->get_billing_state('edit')) ? null : $this->get_billing_state('edit'),
                    'city' => empty($this->get_billing_city('edit')) ? null : $this->get_billing_city('edit'),
                    'zipcode' => empty($this->get_billing_postcode('edit')) ? null : $this->get_billing_postcode(
                        'edit'
                    ),
                    'street' => $this->get_full_billing_address('edit'),
                ],
                'subuser' => [
                    'login' => $this->get_email('edit'),
                    'email' => $this->get_email('edit'),
                ],
            ],
        ];

        $storekeeper_id = $this->getApi()->getModule('ShopModule')->newShopCustomer($call_data);
        $this->set_storekeeper_id($storekeeper_id);

        return $storekeeper_id;
    }

    /**
     * Set customer's Backoffice id.
     *
     * @param int|string $storekeeper_id
     *
     * @since 3.1.0
     */
    public function set_storekeeper_id($storekeeper_id)
    {
        update_user_meta($this->get_id(), 'storekeeper_id', $storekeeper_id);
        $this->data['storekeeper_id'] = $storekeeper_id;
    }

    /**
     * Get customer's Backoffice id.
     *
     * @return int
     */
    public function get_storekeeper_id()
    {
        return get_user_meta($this->get_id(), 'storekeeper_id', true);
    }

    /**
     * Get customer's Backoffice id.
     *
     * @return int
     */
    public function has_storekeeper_id()
    {
        return !empty(get_user_meta($this->get_id(), 'storekeeper_id', true));
    }

    /**
     * Get customer's Backoffice id.
     *
     * @return int
     */
    public function is_storekeeper_id_set()
    {
        return (bool) get_user_meta($this->get_id(), 'storekeeper_id', true);
    }

    public function get_full_shipping_address($context = 'view')
    {
        $address_1 = $this->get_shipping_address_1($context);
        $address_2 = $this->get_shipping_address_2($context);

        return "$address_1
        $address_2";
    }

    public function get_full_billing_address($context = 'view')
    {
        $address_1 = $this->get_billing_address_1($context);
        $address_2 = $this->get_billing_address_2($context);

        return "$address_1
        $address_2";
    }
}
