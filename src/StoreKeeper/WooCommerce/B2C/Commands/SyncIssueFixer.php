<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpCliException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\FullProductImportWithSelectiveIds;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductDeactivateTask;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class SyncIssueFixer extends AbstractSyncIssue
{
    public static function getShortDescription(): string
    {
        return __('Fix if there are known issues with the sync.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        $examples = static::generateExamples([
            'wp sk sync-issue-fixer',
            'wp sk sync-issue-fixer --email=test@mail.com --password=testpassword',
            'wp sk sync-issue-fixer --email=test@mail.com [This is invalid]',
            'wp sk sync-issue-fixer --password=testpassword [This is invalid]',
        ]);

        return __('This command fixes any known issues with the sync possible. Not all issues can be fixed using this command.', I18N::DOMAIN).$examples;
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'email',
                'description' => __('Requires the --password parameters to be set. This field is required if you want to fix the "products active in backend not active in woocommerce" issues. It uses this one to authenticate and call ProductsModule::listConfigurableAssociatedProducts.', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'assoc',
                'name' => 'password',
                'description' => __('Requires the --email parameters to be set. This field is required if you want to fix the "products active in backend not active in woocommerce" issues. It uses this one to authenticate and call ProductsModule::listConfigurableAssociatedProducts.', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        try {
            $this->lock();
            // pre executeInWpCli check
            if (!StoreKeeperOptions::isConnected()) {
                throw new NotConnectedException('Backend is not connected to WooCommerce');
            }

            $this->checkArguments($arguments, $assoc_arguments);

            if (array_key_exists('email', $assoc_arguments) && array_key_exists('password', $assoc_arguments)) {
                $api = StoreKeeperApi::getApiByEmailAndPassword($assoc_arguments['email'], $assoc_arguments['password']);
            } else {
                $api = StoreKeeperApi::getApiByAuthName();
            }

            // To make sure even big products sync
            IniHelper::setIni(
                'memory_limit',
                '512M',
                [$this->logger, 'notice']
            );

            list(
                $failed_tasks,
                $missing_active_product_in_woocommerce,
                $products_that_need_deactivation
                ) = $this->readFromReport();

            if ($missing_active_product_in_woocommerce['amount'] > 0) {
                // Get all assigned products ids and get the parent/configurable product of those.
                $configurable_product_ids = $missing_active_product_in_woocommerce['configurable']['product_ids'];
                $assigned_product_ids = $missing_active_product_in_woocommerce['assign']['product_ids'];

                // Getting all products configurable_product_id's
                if (count($assigned_product_ids) > 0) {
                    if (!array_key_exists('email', $assoc_arguments) || !array_key_exists('password', $assoc_arguments)) {
                        throw new WpCliException('The --email and the --password parameter is required to resolve all tasks. Those need to be from an admin account because we run the function ProductsModule::listConfigurableAssociatedProducts');
                    }

                    $limit = 250;
                    foreach (array_chunk($assigned_product_ids, $limit) as $product_ids) {
                        $response = $api->getModule('ProductsModule')->listConfigurableAssociatedProducts(
                            0,
                            $limit,
                            null,
                            [
                                [
                                    'name' => 'product/id__in_list',
                                    'multi_val' => $product_ids,
                                ],
                            ]
                        );

                        foreach ($response['data'] as $item) {
                            $configurable_product_ids[] += $item['configurable_product_id'];
                        }
                    }
                    $configurable_product_ids = array_unique($configurable_product_ids);
                }

                // Get all simple product ids
                $simple_product_ids = $missing_active_product_in_woocommerce['simple']['product_ids'];

                // Import all simple and configurable products.
                foreach (array_merge($simple_product_ids, $configurable_product_ids) as $product_id) {
                    $product = new FullProductImportWithSelectiveIds(
                        [
                            'product_id' => $product_id,
                        ]
                    );
                    $product->setLogger($this->logger);
                    $product->run();

                    $this->logger->notice(
                        'Imported product',
                        [
                            'product_id' => $product_id,
                        ]
                    );
                }
            }

            if ($products_that_need_deactivation['amount'] > 0) {
                foreach ($products_that_need_deactivation['shop_product_ids'] as $shop_product_id) {
                    $this->logger->debug(
                        'Deactivating product with',
                        [
                            'shop_product_id' => $shop_product_id,
                        ]
                    );

                    $task = new ProductDeactivateTask();
                    $task->setLogger($this->logger);
                    $task->setTaskMeta(['storekeeper_id' => $shop_product_id]);
                    $task->run(
                        [
                            'storekeeper_id' => $shop_product_id,
                        ]
                    );

                    $this->logger->notice(
                        'Deactivating product with',
                        [
                            'shop_product_id' => $shop_product_id,
                        ]
                    );
                }
            }
        } catch (LockActiveException $exception) {
            $this->logger->notice('Cannot run. lock on.');
        }
    }

    private function readFromReport()
    {
        try {
            $content = $this->readFromReportFile(SyncIssueCheck::REPORT_FILE);
            $data = json_decode($content, true);

            return [
                $data['failed_tasks'],
                $data['missing_active_product_in_woocommerce'],
                $data['products_that_need_deactivation'],
            ];
        } catch (\RuntimeException $exception) {
            throw new WpCliException('Reports file does not exists, please run "wp sk sync-issue-check" first', 0, $exception);
        }
    }

    protected function checkArguments(array $arguments, array $assoc_arguments)
    {
        if (array_key_exists('email', $assoc_arguments) && !array_key_exists('password', $assoc_arguments)) {
            throw new WpCliException('When using the --email argument, the --password arguments is required');
        } elseif (!array_key_exists('email', $assoc_arguments) && array_key_exists('password', $assoc_arguments)) {
            throw new WpCliException('When using the --password argument, the --email arguments is required');
        }
    }
}
