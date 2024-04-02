<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\ApiWrapper\Auth;
use StoreKeeper\ApiWrapper\Wrapper\FullJsonAdapter;
use StoreKeeper\ApiWrapperDev\DebugApiWrapper;
use StoreKeeper\ApiWrapperDev\Wrapper\MockAdapter;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class StoreKeeperApi
{
    /**
     * @var string|null
     */
    private static $clientName;
    /**
     * @var MockAdapter
     */
    public static $mockAdapter;

    public const SYNC_AUTH_DATA = 'sync-data';
    public const GUEST_AUTH_DATA = 'guess-data';

    /**
     * StoreKeeperApi constructor.
     *
     * @param array|string $authData
     *
     * @throws \Exception
     */
    public static function getApiByAuthName($authData = self::SYNC_AUTH_DATA, $apiUrl = null): ApiWrapper
    {
        if (is_null($apiUrl)) {
            $apiUrl = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        }

        if (is_array($authData)) {
            $authData = (!empty($authData)) ? $authData : StoreKeeperOptions::get(StoreKeeperOptions::SYNC_AUTH);
        } else {
            if (is_string($authData)) {
                switch ($authData) {
                    case self::SYNC_AUTH_DATA:
                        $authData = StoreKeeperOptions::get(StoreKeeperOptions::SYNC_AUTH);
                        break;
                    case self::GUEST_AUTH_DATA:
                        $authData = StoreKeeperOptions::get(StoreKeeperOptions::GUEST_AUTH);
                        break;
                    default:
                        break;
                }
            }
        }

        StoreKeeperApi::requiredKeysCheck($authData);

        /*
         * Checks if the apiUrl is set. if not it will throw an error.
         */
        if (empty($apiUrl)) {
            throw new \Exception('No api url set');
        }

        /**
         * Creating the FullJsonAdapter and setting the logger.
         */
        $adapter = new FullJsonAdapter($apiUrl, [
            'headers' => [
                'User-Agent' => self::getClientName(),
            ],
        ]);
        $adapter->setLogger(LoggerFactory::create('storekeeper_api'));

        /**
         * Creating the Auth, Needs to change in the future so it uses also the user who logged in there information.
         */
        $auth = new Auth();
        $auth->setSubuser($authData['subaccount'], $authData['user']);
        $auth->setApiKey($authData['apikey']);
        $auth->setAccount($authData['account']);
        $auth->setClientName(self::getClientName());

        return self::getApiWrapper($adapter, $auth);
    }

    /**
     * @throws \Exception
     */
    private static function requiredKeysCheck($authData)
    {
        $missingAuthKeys = [];
        $wantedAuthKeys = [
            'subaccount',
            'user',
            'apikey',
            'account',
        ];
        foreach ($wantedAuthKeys as $wantedAuthKey) {
            if (!key_exists($wantedAuthKey, $authData) && empty($authData[$wantedAuthKey])) {
                $missingAuthKeys[] = $wantedAuthKey;
            }
        }
        if (count($missingAuthKeys) > 0) {
            throw new \Exception('Auth data has some missing required keys: '.join(', ', $missingAuthKeys));
        }
    }

    public static function getApiByEmailAndPassword($email, $password)
    {
        // setting up the adapter
        $apiUrl = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        $adapter = new FullJsonAdapter($apiUrl);
        $adapter->setLogger(LoggerFactory::create('storekeeper_api'));

        // Calling startSessionByEmail
        $syncAuth = StoreKeeperOptions::get(StoreKeeperOptions::SYNC_AUTH);
        $account = $syncAuth['account'];

        $authAnonymous = new Auth(
            [
                'account' => $account,
                'user' => 'anonymous',
                'rights' => 'anonymous',
                'mode' => 'none',
                'client_name' => 'WooCommerceSyncPlugin',
            ]
        );

        $wrapper = self::getApiWrapper($adapter, $authAnonymous);
        $response = $wrapper->getModule('RelationsModule')->startSessionByEmail($email, $password, $account);

        // Creating auth
        $auth = new Auth($response);

        return self::getApiWrapper($adapter, $auth);
    }

    protected static function getApiWrapper(FullJsonAdapter $adapter, Auth $auth): ApiWrapper
    {
        if (Core::isTest()) {
            if (!(self::$mockAdapter instanceof MockAdapter)) {
                throw new BaseException(__CLASS__.'::$mockAdapter is not set or not of class '.MockAdapter::class);
            }
            $wrapper = new ApiWrapper(self::$mockAdapter, $auth);
        } else {
            if (Core::isDataDump()) {
                $wrapper = new DebugApiWrapper($adapter, $auth);
                $wrapper->enableDumping(Core::getDumpDir());
            } else {
                $wrapper = new ApiWrapper($adapter, $auth);
            }
        }

        return $wrapper;
    }

    /**
     * Gets the mainType with its options from the $backref string.
     *
     * @return array
     */
    public static function extractMainTypeAndOptions($backref)
    {
        $main_type = $backref;
        $options = [];
        if (($p = strpos($backref, '(')) !== false) {
            $options = self::extractOptions($backref);
            if (!empty($options)) {
                $main_type = substr($backref, 0, $p);
            }
        }

        return [$main_type, $options];
    }

    /**
     * Extracts the options from the backref.
     *
     * @return array
     */
    private static function extractOptions($backref)
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

    public static function getResourceUrl(?string $url)
    {
        if (!empty($url)) {
            if ('/download/public/' === substr($url, 0, 17)) {
                $apiUrl = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);

                return rtrim($apiUrl, '/').$url;
            }

            return $url;
        }

        return null;
    }

    protected static function getClientName(): string
    {
        if (is_null(self::$clientName)) {
            $missingInfo = false;
            $plugins = [];
            if (function_exists('get_plugins')) {
                $plugins = \get_plugins();
            } else {
                $missingInfo = true;
            }
            $url = '?';
            if (function_exists('get_site_url')) {
                $url = \get_site_url();
            } else {
                $missingInfo = true;
            }
            $woocommerce_version = '?';
            if (!empty($plugins['woocommerce/woocommerce.php'])) {
                $woocommerce_version = $plugins['woocommerce/woocommerce.php']['Version'];
            } else {
                $missingInfo = true;
            }
            $wordpress_version = '?';
            if (function_exists('get_bloginfo')) {
                $wordpress_version = \get_bloginfo('version');
            } else {
                $missingInfo = true;
            }

            $clientName = 'storekeeper-woocommerce-b2c/'.STOREKEEPER_WOOCOMMERCE_B2C_VERSION
                .' ('
                .'id='.WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_UUID).'; '
                .'url='.$url
                .') '
                .' Wordpress/'.$wordpress_version
                .' WooCommerce/'.$woocommerce_version;

            if ($missingInfo) {
                // do not cache so later we can get better one
                return $clientName;
            }

            self::$clientName = $clientName;
        }

        return self::$clientName;
    }
}
