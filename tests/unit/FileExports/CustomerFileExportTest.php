<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\FileExport\AbstractCSVFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\CustomerFileExport;
use StoreKeeper\WooCommerce\B2C\Objects\GOCustomer;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use WP_User;

class CustomerFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return CustomerFileExport::class;
    }

    public function dataProviderUserCreateByRoles()
    {
        $tests = [];

        $valid = [];
        foreach (GOCustomer::VALID_ROLES as $role) {
            $valid[] = [
                $role,
                $role.'_nickname',
                $role.'_userlogin',
                $role.'_userpass',
            ];
        }
        $tests['valid'] = [$valid];

        $invalid = [];
        foreach (GOCustomer::INVALID_ROLES as $role) {
            $invalid[] = [
                $role,
                $role.'_nickname',
                $role.'_userlogin',
                $role.'_userpass',
            ];
        }
        $tests['invalid'] = [$invalid];

        return $tests;
    }

    /**
     * @dataProvider dataProviderUserCreateByRoles
     */
    public function testUserCreateByRoles($users): void
    {
        $this->initApiConnection();
        // Setting is_logged_in to true
        wp_set_current_user(1);
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) {
                $module->shouldReceive('newShopCustomer')->andReturnUsing(
                    function ($got) {
                        $user = $got[0]['relation'];
                        // Passed firstname as the role in test data
                        $role = $user['contact_person']['firstname'];
                        // Assertion to make sure invalid roles never calls ShopModule::newShopCustomer
                        $this->assertNotContains($role, GOCustomer::INVALID_ROLES, $role.' should not be returned');

                        $this->assertContains($role, GOCustomer::VALID_ROLES, $role.' should be returned');
                    }
                );
            }
        );

        foreach ($users as $user) {
            $role = $user[0];
            $userId = wp_insert_user([
                'first_name' => $role,
                'nickname' => $user[1],
                'user_login' => $user[2],
                'role' => $role,
                'user_pass' => $user[3],
            ]);

            $createdUser = get_user_by('id', $userId);
            // Assertion to check if user is created in database
            // may it be valid or invalid consumer role
            $this->assertNotFalse($createdUser);
            $this->assertEquals($role, $createdUser->roles[0]);
        }
    }

    public function testCustomerExportTest()
    {
        // Create customers
        $customer = $this->createCustomer();
        $user = new WP_User($customer->get_id());

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRow = $this->getMappedDataRow($spreadSheet, 2);

        $this->assertEquals(
            strtolower('wp-'.$customer->get_username().'-'.$customer->get_id()),
            $mappedRow['shortname'],
            "Customer's shortname does not matches"
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $mappedRow['language_iso2'],
            "Customer's country does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_company(),
            $mappedRow['business_data.name'],
            "Customer's company name does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_country() ?? CustomerFileExport::FALLBACK_COUNTRY_ISO2,
            $mappedRow['business_data.country_iso2'],
            "Customer's company country does not matches"
        );

        $this->assertEquals(
            $customer->get_first_name(),
            $mappedRow['contact_person.firstname'],
            "Customer's contact first name does not matches"
        );

        $this->assertEquals(
            $customer->get_last_name(),
            $mappedRow['contact_person.familyname'],
            "Customer's contact family name does not matches"
        );

        $this->assertEquals(
            $customer->get_email(),
            $mappedRow['contact_set.email'],
            "Customer's contact email does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_phone(),
            $mappedRow['contact_set.phone'],
            "Customer's contact phone does not matches"
        );

        $this->assertEquals(
            $user->user_url,
            $mappedRow['contact_set.www'],
            "Customer's contact website does not matches"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue(true),
            $mappedRow['contact_set.allow_general_communication'],
            "Customer's general communication does not matches"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue(true),
            $mappedRow['contact_set.allow_offer_communication'],
            "Customer's offer communication does not matches"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue(true),
            $mappedRow['contact_set.allow_special_communication'],
            "Customer's special communication does not matches"
        );

        /*
         * Assert billing address
         */

        $this->assertEquals(
            CustomerFileExport::getFormattedBillingFullName($customer),
            $mappedRow['address_billing.name'],
            "Customer's billing name does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_state(),
            $mappedRow['address_billing.state'],
            "Customer's billing state does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_city(),
            $mappedRow['address_billing.city'],
            "Customer's billing city does not matches"
        );

        $this->assertEquals(
            $customer->get_billing_postcode(),
            $mappedRow['address_billing.zipcode'],
            "Customer's billing zipcode does not matches"
        );

        $this->assertEquals(
            CustomerFileExport::getFormattedBillingStreet($customer),
            $mappedRow['address_billing.street'],
            "Customer's billing street does not matches"
        );

        /*
         * Assert shipping address
         */

        $this->assertEquals(
            CustomerFileExport::getFormattedShippingFullName($customer),
            $mappedRow['contact_address.name'],
            "Customer's shipping name does not matches"
        );

        $this->assertEquals(
            $customer->get_shipping_state(),
            $mappedRow['contact_address.state'],
            "Customer's shipping state does not matches"
        );

        $this->assertEquals(
            $customer->get_shipping_city(),
            $mappedRow['contact_address.city'],
            "Customer's shipping city does not matches"
        );

        $this->assertEquals(
            $customer->get_shipping_postcode(),
            $mappedRow['contact_address.zipcode'],
            "Customer's shipping zipcode does not matches"
        );

        $this->assertEquals(
            CustomerFileExport::getFormattedshippingStreet($customer),
            $mappedRow['contact_address.street'],
            "Customer's shipping street does not matches"
        );
    }
}
