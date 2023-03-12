<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface IModel
{
    public static function getFieldsWithRequired(): array;

    public static function hasTable(): bool;

    public static function create(array $data): int;

    public static function read($id): ?array;

    public static function get($id): ?array;

    public static function update($id, array $data): void;

    public static function delete($id): void;

    public static function purge(): int;

    public static function count(): int;
}
