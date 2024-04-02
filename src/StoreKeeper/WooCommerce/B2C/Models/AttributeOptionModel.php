<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeOptionName;

class AttributeOptionModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_attribute_options';
    public const FK_STOREKEEPER_ATTRIBUTE_ID = 'storekeeper_attribute_id_fk';
    public const FK_TERM_ID = 'term_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'storekeeper_attribute_id' => false,
            'term_id' => true,
            'common_name' => true,
            'storekeeper_id' => true,
            'storekeeper_alias' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
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
