<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\Exceptions\SsoAuthException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class SsoHelper
{
    public const TRANSIENT_PREFIX = 'sk_sso_login';
    public const OPTION_ROLE_PREFIX = 'storekeeper_role';

    public const DISABLED_SSO_ROLE = '';
    public const DEFAULT_SSO_FOR_KNOWN_ROLES = RoleHelper::ROLE_WEBSHOP_MANAGER; // default is use for known roles, that are not set in settings yet.

    public const FALLBACK_SSO_ROLE_NAME = 'fallback'; // fallback is used for unknown roles.
    public const ADMIN_SSO_ROLE_NAME = 'admin';
    public const MANAGER_SSO_ROLE_NAME = 'manager';
    public const USER_SSO_ROLE_NAME = 'user';

    public const KNOWN_ROLES = [
        self::ADMIN_SSO_ROLE_NAME,
        self::MANAGER_SSO_ROLE_NAME,
        self::USER_SSO_ROLE_NAME,
    ];

    /**
     * @return bool
     */
    public static function setUserBackofficeRole($user_id, $backoffice_role)
    {
        return (bool) update_user_meta($user_id, 'storekeeper_role', $backoffice_role);
    }

    /**
     * @return string
     */
    public static function getUserBackofficeRole($user_id)
    {
        return (string) get_user_meta($user_id, 'storekeeper_role', true);
    }

    /**
     * @return int
     *
     * @throws SsoAuthException
     * @throws WordpressException
     */
    public static function createUser($name, $shortname, $backoffice_role)
    {
        // Gathering data
        $password = StringFunctions::generateRandomHash(); // Generate random password
        $role = self::backofficeToWordpressRole(
            $backoffice_role
        ); // convert backoffice role to WordPress using the backoffice role settings

        // Check if the role is not disabled
        if (self::DISABLED_SSO_ROLE === $role) {
            throw new SsoAuthException("User with role \"$backoffice_role\" is not allowed to login");
        }

        // Creating user
        $user_id = (int) WordpressExceptionThrower::throwExceptionOnWpError(
            wp_insert_user(
                [
                    'first_name' => $name,
                    'nickname' => $name,
                    'user_login' => self::getShortname($shortname),
                    'role' => $role,
                    'user_pass' => $password,
                ]
            )
        );

        // Adding the backoffice role
        self::setUserBackofficeRole($user_id, $backoffice_role);

        return $user_id;
    }

    /**
     * Returns the role set in the backoffice settings, fallback to editor role.
     *
     * @return string
     */
    private static function backofficeToWordpressRole($backoffice_role)
    {
        $optionKey = self::formatRoleOptionKey($backoffice_role);
        $value = get_option($optionKey, false);

        if (self::DISABLED_SSO_ROLE === $value) {
            return $value;
        }

        if (false === $value && in_array($backoffice_role, self::KNOWN_ROLES, true)) {
            $value = self::DEFAULT_SSO_FOR_KNOWN_ROLES;
        } else {
            if (false === $value) {
                $fallbackKey = self::formatRoleOptionKey(self::FALLBACK_SSO_ROLE_NAME);
                $value = (string) get_option($fallbackKey, self::DISABLED_SSO_ROLE);
            }
        }

        return $value;
    }

    /**
     * @return bool
     */
    public static function existsUser($shortname)
    {
        return (bool) get_user_by('login', self::getShortname($shortname));
    }

    /**
     * @return bool
     *
     * @throws SsoAuthException
     * @throws WordpressException
     */
    public static function updateUser($name, $shortname, $backoffice_role)
    {
        if (self::existsUser($shortname)) {
            $role = self::backofficeToWordpressRole(
                $backoffice_role
            ); // convert backoffice role to WordPress using the backoffice role settings

            $user = get_user_by('login', self::getShortname($shortname));

            // Updates the user, if the role is disabled, it disables the login for that user.
            $update = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_user(
                    [
                        'ID' => $user->ID,
                        'first_name' => $name,
                        'nickname' => $name,
                        'role' => $role,
                    ]
                )
            );

            self::setUserBackofficeRole($user->ID, $backoffice_role);

            if (self::DISABLED_SSO_ROLE === $role) {
                throw new SsoAuthException("User with role \"$backoffice_role\" is not allowed to login");
            }

            return (bool) $update;
        }
        throw new \Exception("Could not find a user with shortname \"$shortname\"");
    }

    private static function createTransientKey($key)
    {
        return self::TRANSIENT_PREFIX.'-'.$key;
    }

    /**
     * Creates an SSO for the user.
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function createSso($shortname, $expiration = 60)
    {
        if (!self::existsUser($shortname)) {
            throw new \Exception('Failed to create key: User not found');
        }

        $transient_key = StringFunctions::generateRandomHash(30);
        $data = [
            'shortname' => $shortname,
            'expiration' => time() + $expiration,
        ];

        // Setting the transient
        if (set_transient(self::createTransientKey($transient_key), json_encode($data), $expiration)) {
            return $transient_key;
        }
        throw new \Exception('Failed to create transient key for SSO');
    }

    /**
     * checks if the transient key is correct. and returns the shortname for who the SSO is intended if successful.
     *
     * @return string shortname
     *
     * @throws SsoAuthException
     */
    public static function verifySso($transient_key)
    {
        $logger = LoggerFactory::create('sso');
        // Checking  if there is any transient data
        $raw_data = get_transient(self::createTransientKey($transient_key));
        if (!$raw_data) {
            $logger->debug("No transient found with key \"$transient_key\"");
            throw new SsoAuthException(__('Login expired, please try again', I18N::DOMAIN));
        }

        // Checking  if the data is parseable
        $data = json_decode($raw_data, true);
        if (null === $data) {
            $logger->debug("Invalid JSON data found in \"$transient_key\"");
            throw new SsoAuthException(__('Login expired, please try again', I18N::DOMAIN));
        }

        // Check if shortname exists in the data.
        if (!array_key_exists('shortname', $data)) {
            $logger->debug("No shortname key found in \"$transient_key\"");
            throw new SsoAuthException(__('Login expired, please try again', I18N::DOMAIN));
        }

        if (array_key_exists('expiration', $data)) {
            $time = (int) $data['expiration'];
            if ($time < time()) {
                $logger->debug("Login expired of \"$transient_key\"");
                throw new SsoAuthException(__('Login expired, please try again', I18N::DOMAIN));
            }
        }

        // Kill transient;
        delete_transient(self::createTransientKey($transient_key));

        return (string) $data['shortname'];
    }

    /**
     * @throws SsoAuthException
     */
    public static function loginUser($shortname)
    {
        if (!self::existsUser($shortname)) {
            throw new SsoAuthException("User with shortname \"$shortname\" does no exists");
        }

        $user = get_user_by('login', self::getShortname($shortname));
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
    }

    /**
     * Simple redirect.
     */
    public static function redirectUser()
    {
        wp_redirect(user_admin_url());
        exit;
    }

    public static function createSsoLink($transient_key)
    {
        return site_url("/?rest_route=/storekeeper-woocommerce-b2c/v1/sso/&key=$transient_key");
    }

    /**
     * @return string
     */
    public static function formatRoleOptionKey($backoffice_role)
    {
        return self::OPTION_ROLE_PREFIX.'_'.$backoffice_role;
    }

    /**
     * @return bool|string
     */
    public static function getBackofficeRoleFromRoleOptionKey($role_option_key)
    {
        $str_length = strlen(self::OPTION_ROLE_PREFIX);

        return substr($role_option_key, $str_length);
    }

    public static function getShortname($shortname)
    {
        return "storekeeper-$shortname";
    }

    public static function updateUsersRoles($backoffice_role, $wordpress_role)
    {
        $users = get_users(
            [
                'meta_query' => [
                    [
                        'key' => 'storekeeper_role',
                        'value' => $backoffice_role,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        foreach ($users as $user) {
            WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_user(
                    [
                        'ID' => $user->ID,
                        'role' => $wordpress_role,
                    ]
                )
            );
        }
    }
}
