<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeOptionName;

class AttributeOptionModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_attribute_options';
    const TABLE_VERSION = '1.0.0';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'storekeeper_attribute_id' => false,
            'term_id' => true,
            'common_name' => true,
            'storekeeper_id' => true,
            'storekeeper_alias' => false,
            'date_created' => false,
            'date_updated' => false,
        ];
    }

    public static function createTable(): bool
    {
        $wp = self::getWpPrefix();
        self::checkTableEngineInnoDB("{$wp}terms");

        $name = self::getTableName();
        $tableQuery = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `storekeeper_attribute_id` BIGINT(20) UNSIGNED NULL,
        `term_id` BIGINT(20) UNSIGNED NULL UNIQUE,
        `common_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
        `storekeeper_id` BIGINT(20) NOT NULL UNIQUE,
        `storekeeper_alias` VARCHAR(1500) COLLATE utf8mb4_unicode_ci,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`),
        CONSTRAINT `{$name}_storekeeper_attribute_id_fk` 
            FOREIGN KEY (`storekeeper_attribute_id`) 
            REFERENCES  `{$wp}storekeeper_attributes` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `{$name}_term_id_fk` 
            FOREIGN KEY (`term_id`) 
            REFERENCES  `{$wp}terms` (`term_id`)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
    }

    public static function getTermIdByStorekeeperId(int $attribute_id, int $sk_attribute_option_id): ?int
    {
        $select = self::getSelectHelper('opt')
            ->cols(['opt.term_id'])
            ->join('left',
                AttributeModel::getTableName().' as attr',
                'attr.id = opt.storekeeper_attribute_id'
            )
            ->where(
                'attr.attribute_id = :attribute_id 
                AND opt.storekeeper_id = :storekeeper_id'
            )
            ->bindValue('attribute_id', $attribute_id)
            ->bindValue('storekeeper_id', $sk_attribute_option_id);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function getStorekeeperIdTermId(int $term_id): ?int
    {
        $select = self::getSelectHelper()
            ->cols(['storekeeper_id'])
            ->where(
                'term_id = :term_id'
            )
            ->bindValue('term_id', $term_id);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function termIdExists(int $term_id): bool
    {
        $select = self::getSelectHelper('opt')
            ->cols(['1'])
            ->where(
                'term_id = :term_id'
            )
            ->bindValue('term_id', $term_id);

        global $wpdb;

        return !empty($wpdb->get_var(self::prepareQuery($select)));
    }

    public static function getTermIdByStorekeeperAlias(int $attribute_id, string $sk_attribute_option_alias): ?int
    {
        $select = self::getSelectHelper('opt')
            ->cols(['opt.term_id'])
            ->join('left',
                AttributeModel::getTableName().' as attr',
                'attr.id = opt.storekeeper_attribute_id'
            )
            ->where(
                'attr.attribute_id = :attribute_id 
                AND opt.storekeeper_alias = :storekeeper_alias'
            )
            ->bindValue('attribute_id', $attribute_id)
            ->bindValue('storekeeper_alias', $sk_attribute_option_alias);

        global $wpdb;

        return $wpdb->get_var(self::prepareQuery($select));
    }

    public static function setAttributeOptionTerm(
        \WP_Term $term, int $attribute_id, int $storekeper_id, ?string $storekeper_alias = null)
    {
        $attribute = wc_get_attribute($attribute_id);
        $select = self::getSelectHelper()
            ->cols(['*'])
            ->where('term_id = :term_id')
            ->bindValue('term_id', $term->term_id);

        global $wpdb;
        $existingRow = $wpdb->get_row(self::prepareQuery($select), ARRAY_A);
        $updates = [
            'term_id' => $term->term_id,
            'common_name' => CommonAttributeOptionName::getName(
                $attribute->slug,
                $term->slug
            ),
            'storekeeper_id' => $storekeper_id,
        ];
        if (!empty($storekeper_alias)) {
            $updates['storekeeper_alias'] = $storekeper_alias;
        }
        if (empty($existingRow)) {
            $updates['storekeeper_attribute_id'] = AttributeModel::getIdAttributeId($attribute_id);
        }
        self::upsert($updates, $existingRow);
    }
}
