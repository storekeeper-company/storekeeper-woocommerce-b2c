<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Models;

use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhoolLogModelTest extends AbstractModelTest
{
    public function testBaseModel()
    {
        $this->assertBaseModel(WebhookLogModel::class);
    }

    public function testPurgeOldModels()
    {
        WebhookLogModel::create(
            $this->getNewObjectData(
                [
                    'date_created' => date('Y-m-d H:i:s', strtotime('-31 days')),
                ]
            )
        );
        WebhookLogModel::create(
            $this->getNewObjectData(
                [
                    'date_created' => date('Y-m-d H:i:s'),
                ]
            )
        );

        $this->assertEquals(
            2,
            WebhookLogModel::count(),
            'Webhook log models where not created properly'
        );

        WebhookLogModel::purge();

        $this->assertEquals(
            1,
            WebhookLogModel::count(),
            'Webhook log model was not purged'
        );
    }

    public function testPurgeAccessModels()
    {
        for ($i = 0; $i < 2000; ++$i) {
            WebhookLogModel::create(
                $this->getNewObjectData(
                    [
                        'action' => "action_$i",
                    ]
                )
            );
        }

        $this->assertEquals(
            2000,
            WebhookLogModel::count(),
            'Not all webhook log where created'
        );

        WebhookLogModel::purge();

        $this->assertEquals(
            1000,
            WebhookLogModel::count(),
            'Not all webhook log where purged'
        );
    }

    public function getNewObjectData(array $overwrite = []): array
    {
        return $overwrite + [
                'action' => 'events',
                'body' => json_encode(
                    [
                        'action' => 'events',
                    ]
                ),
                'headers' => json_encode(
                    [
                        'content_type' => ['application/json'],
                    ]
                ),
                'method' => 'POST',
                'response_code' => 200,
                'route' => '/storekeeper-woocommerce-b2c/v1/webhook',
            ];
    }

    public function updateExistingObjectData(array $data): array
    {
        return [
                'action' => 'sso',
                'body' => json_encode(
                    [
                        'action' => 'sso',
                    ]
                ),
                'headers' => json_encode(
                    [
                        'content_type' => ['application/pdf'],
                    ]
                ),
                'method' => 'GET',
                'response_code' => 404,
                'route' => '/storekeeper-woocommerce-b2c/v1/sso',
            ] + $data;
    }

    public function assertModelData(array $expected, array $actual, string $ModelClass): void
    {
        $assertField = function ($field) use ($expected, $actual, $ModelClass) {
            $this->assertEquals(
                $expected[$field],
                $actual[$field],
                "[$ModelClass] Model data field '$field' did not match"
            );
        };

        $assertField('action');
        $assertField('body');
        $assertField('headers');
        $assertField('method');
        $assertField('response_code');
        $assertField('route');
    }
}
