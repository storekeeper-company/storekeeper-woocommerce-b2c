<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Models;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskModelTest extends AbstractModelTest
{
    public function testBaseModel()
    {
        $this->assertBaseModel(TaskModel::class);
    }

    public function testCreateTask()
    {
        $createData = $this->getNewObjectData();
        $id = TaskModel::newTask(
            $createData['title'],
            $createData['type'],
            $createData['type_group'],
            (int) $createData['storekeeper_id'],
            $createData['meta_data'],
            $createData['status'],
            (int) $createData['times_ran']
        );

        $getData = TaskModel::get($id);
        $this->assertModelData(
            $createData,
            $getData,
            'Task model did not create a new task correctly'
        );
    }

    public function testGetName()
    {
        $type = TaskHandler::PRODUCT_IMPORT;
        $storekeeper_id = 1337;
        $expected = $type.'::'.$storekeeper_id;
        $actual = TaskModel::getName($type, $storekeeper_id);
        $this->assertEquals($expected, $actual, 'Task model did not created a correct name');
    }

    public function testModelMetaData()
    {
        $metadata = [
            'foo' => 'bar',
            'times' => 10,
            'user' => [
                'firstname' => 'Hello',
                'lastname' => 'World',
            ],
        ];

        $id = TaskModel::create(
            $this->getNewObjectData(
                [
                    'meta_data' => $metadata,
                ]
            )
        );

        $this->assertEquals(
            TaskModel::getMetaDataKey($id, 'foo'),
            $metadata['foo'],
            'Task model did not return the correct meta data'
        );

        $this->assertEquals(
            TaskModel::getMetaDataKey($id, 'times'),
            $metadata['times'],
            'Task model did not return the correct meta data'
        );

        $this->assertDeepArray(
            TaskModel::getMetaDataKey($id, 'user'),
            $metadata['user'],
            'Task model did not return the correct meta data'
        );
    }

    public function testPurgeOldModels()
    {
        TaskModel::create(
            $this->getNewObjectData(
                [
                    'date_created' => date('Y-m-d H:i:s', strtotime('-31 days')),
                    'status' => TaskHandler::STATUS_SUCCESS,
                ]
            )
        );
        TaskModel::create(
            $this->getNewObjectData(
                [
                    'date_created' => date('Y-m-d H:i:s'),
                    'status' => TaskHandler::STATUS_SUCCESS,
                ]
            )
        );

        $this->assertEquals(
            2,
            TaskModel::count(),
            'Task models where not created properly'
        );

        TaskModel::purge();

        $this->assertEquals(
            1,
            TaskModel::count(),
            'Task model was not purged'
        );
    }

    public function testPurgeAccessModels()
    {
        for ($i = 0; $i < 2000; ++$i) {
            TaskModel::create(
                $this->getNewObjectData(
                    [
                        'title' => "Task Nr #$i",
                        'status' => TaskHandler::STATUS_SUCCESS,
                    ]
                )
            );
        }

        $this->assertEquals(
            2000,
            TaskModel::count(),
            'Not all tasks where created'
        );

        TaskModel::purge();

        $this->assertEquals(
            1000,
            TaskModel::count(),
            'Not all tasks where purged'
        );
    }

    public function getNewObjectData(array $overwrite = []): array
    {
        return $overwrite + [
                'title' => 'Some epic task',
                'status' => TaskHandler::STATUS_NEW,
                'times_ran' => 0,
                'name' => TaskHandler::PRODUCT_IMPORT.'::'. 1234,
                'type' => TaskHandler::PRODUCT_IMPORT,
                'type_group' => TaskHandler::PRODUCT_TYPE_GROUP,
                'storekeeper_id' => 1234,
                'meta_data' => [
                    'foo' => 'bar',
                    'user' => [
                        'firstname' => 'Hello',
                        'lastname' => 'World',
                    ],
                ],
                'error_output' => null,
                'date_created' => null,
                'date_updated' => null,
            ];
    }

    public function updateExistingObjectData(array $data): array
    {
        return [
                'error_output' => 'AAHHH HELP!',
                'times_ran' => 69,
                'meta_data' => [
                    'hello' => 'world',
                    'user' => [
                        'firstname' => 'Foo',
                        'lastname' => 'Bar',
                    ],
                ],
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

        $assertField('title');
        $assertField('status');
        $assertField('times_ran');
        $assertField('name');
        $assertField('type');
        $assertField('storekeeper_id');
        $assertField('error_output');

        $this->assertDeepArray(
            $expected['meta_data'],
            $actual['meta_data'],
            "[$ModelClass] Model data field 'meta_data' did not match"
        );
    }
}
