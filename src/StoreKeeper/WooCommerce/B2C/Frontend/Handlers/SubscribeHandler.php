<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes\FormShortCode;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class SubscribeHandler
{
    /**
     * Redirects user to the current page with a state added to the url as a query.
     *
     * @param $state string State to send with the redirect
     */
    private static function stateRedirect($state)
    {
        $url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $explodedUrl = explode('?', $url);
        $urlToRedirect = $explodedUrl[0];
        $query = $urlToRedirect.'?state='.urlencode($state);

        wp_redirect($query);
        exit;
    }

    public function register()
    {
        if (isset($_POST['form_id']) && FormShortCode::ID === $_POST['form_id']) {
            if (!empty($_POST['firstname']) && !empty($_POST['lastname']) && !empty($_POST['email'])) {
                $this->addContact(
                    sanitize_text_field($_POST['firstname']),
                    sanitize_text_field($_POST['lastname']),
                    sanitize_email($_POST['email'])
                );
            } else {
                self::stateRedirect('error');
            }
        }
    }

    private function addContact($firstName, $lastName, $email)
    {
        $api = StoreKeeperApi::getApiByAuthName();
        $ShopModule = $api->getModule('ShopModule');

        $logger = LoggerFactory::create('subscribe');

        try {
            $ShopModule->newShopContact(
                [
                    'relation' => [
                        'isprivate' => 'true',
                        'contact_person' => [
                            'firstname' => $firstName,
                            'familyname' => $lastName,
                            'ismale' => 'true',
                            'contact_set' => [
                                'email' => $email,
                            ],
                        ],
                        'contact_address' => [
                            'country_iso2' => 'NL',
                            'contact_set' => [
                                'email' => $email,
                            ],
                        ],
                        'subuser' => [
                            'login' => 'user',
                            'email' => $email,
                        ],
                    ],
                    'send_welcome_email' => 'false',
                ]
            );

            self::stateRedirect('success');
        } catch (GeneralException $e) {
            if ('Subuser with this email already exists' === $e->getMessage()) {
                self::stateRedirect('already_subscribed');
            } else {
                $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
                self::stateRedirect('error');
            }
        } catch (\Throwable $e) {
            $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
}
