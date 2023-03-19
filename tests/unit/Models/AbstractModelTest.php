<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModel;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

abstract class AbstractModelTest extends AbstractTest
{
    protected function assertBaseModel(string $ModelClass)
    {
        /** @var IModel $Model */
        $Model = new $ModelClass();

        $this->assertTrue(
            $Model::hasTable(),
            "[$ModelClass] Table not created"
        );

        $createData = $this->getNewObjectData();
        $id = $Model::create($createData);

        $this->assertEquals(
            1,
            $Model::count(),
            "[$ModelClass] Model was not created"
        );

        $getData = $Model::get($id);
        $this->assertModelData(
            $createData,
            $getData,
            "[$ModelClass] Model data did not match after inserting"
        );

        sleep(1); // Ensure the date_updated is different

        $updateData = $this->updateExistingObjectData($getData);
        $Model::update($id, $updateData);
        $this->assertModelData(
            $updateData + $getData,
            $Model::get($id),
            "[$ModelClass] Model data did not match after inserting"
        );

        $updatedData = $Model::get($id);
        $this->assertNotEquals(
            $getData['date_updated'],
            $updatedData['date_updated'],
            "[$ModelClass] Model data date_updated did not change"
        );

        $Model::delete($id);
        $this->assertNull(
            $Model::get($id),
            "[$ModelClass] Model data was not deleted"
        );
    }

    abstract public function getNewObjectData(array $overwrite = []): array;

    abstract public function updateExistingObjectData(array $data): array;

    abstract public function assertModelData(array $expected, array $actual, string $ModelClass): void;
}
