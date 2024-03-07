<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeName;

class AttributeModel extends AbstractModel implements IModelPurge
{
    public const FK_ATTRIBUTE_ID = 'attribute_id_fk';
    public const TABLE_NAME = 'storekeeper_attributes';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'attribute_id' => false,
            'common_name' => true,
            'storekeeper_id' => false,
            'storekeeper_alias' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public static function setAttributeTaxonomy(\stdClass $taxonomy, int $storekeper_id,
        ?string $storekeper_alias = null)
    {
        $select = self::getSelectHelper()
            ->cols(['*'])
            ->where('attribute_id = :attribute_id')
            ->bindValue('attribute_id', $taxonomy->attribute_id);

        global $wpdb;
        $existingRow = $wpdb->get_row(self::prepareQuery($select), ARRAY_A);
        $updates = [
            'attribute_id' => $taxonomy->attribute_id,
            'common_name' => CommonAttributeName::getSystemName($taxonomy->attribute_name),
            'storekeeper_id' => $storekeper_id,
        ];
        if (!empty($storekeper_alias)) {
            $updates['storekeeper_alias'] = $storekeper_alias;
        }
        self::upsert($updates, $existingRow);
    }

    public static function setAttributeStoreKeeperId(
        int $attribute_id, int $storekeper_id,
        ?string $storekeper_alias = null
    ) {
        $attributes = wc_get_attribute_taxonomies();

        $key = 'id:'.$attribute_id;
        if (!array_key_exists($key, $attributes)) {
            throw new \Exception("WcAttribute with id=$attribute_id not found");
        }

        self::setAttributeTaxonomy($attributes[$key], $storekeper_id, $storekeper_alias);
    }

    public static function getAttributeStoreKeeperId(int $attribute_id): ?int
    {
        $select = self::getSelectHelper()
            ->cols(['storekeeper_id'])
            ->where('attribute_id = :attribute_id')
            ->bindValue('attribute_id', $attribute_id);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function getIdAttributeId(int $attribute_id): ?int
    {
        $select = self::getSelectHelper()
            ->cols(['id'])
            ->where('attribute_id = :attribute_id')
            ->bindValue('attribute_id', $attribute_id);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function getStoreKeeperAliasByCommonName(string $common_name): ?string
    {
        $select = self::getSelectHelper()
            ->cols(['storekeeper_alias'])
            ->where('common_name = :common_name')
            ->bindValue('common_name', $common_name);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function getAttributeByStoreKeeperId(int $storekeeper_id): ?\stdClass
    {
        $select = self::getSelectHelper()
            ->cols(['attribute_id'])
            ->where('storekeeper_id = :storekeeper_id')
            ->bindValue('storekeeper_id', $storekeeper_id);

        global $wpdb;
        $attribute_id = $wpdb->get_var(self::prepareQuery($select));
        if (!empty($attribute_id)) {
            return wc_get_attribute($attribute_id);
        }

        return null;
    }

    public static function getAttributeByStoreKeeperAlias(string $storekeeper_alias): ?\stdClass
    {
        $select = self::getSelectHelper()
            ->cols(['attribute_id'])
            ->where('storekeeper_alias = :storekeeper_alias')
            ->bindValue('storekeeper_alias', $storekeeper_alias);

        global $wpdb;
        $attribute_id = $wpdb->get_var(self::prepareQuery($select));
        if (!empty($attribute_id)) {
            return wc_get_attribute($attribute_id);
        }

        return null;
    }

    public static function getAttributeIds(): array
    {
        $select = self::getSelectHelper()
            ->cols(['attribute_id']);

        global $wpdb;

        return $wpdb->get_col(self::prepareQuery($select));
    }

    public static function purge(): int
    {
        $delete = self::getDeleteHelper();
        global $wpdb;

        return $wpdb->query(self::prepareQuery($delete));
    }
}
