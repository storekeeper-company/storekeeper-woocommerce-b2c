<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Exceptions\TableOperationSqlException;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class PaymentModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_pay_orders_payments';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => false,
            'order_id' => true,
            'payment_id' => true,
            'amount' => false,
            'is_synced' => true,
            'trx' => false,
            'is_paid' => false,
        ];
    }

    public static function prepareInsertData(array $data): array
    {
        return self::prepareData($data, true);
    }

    public static function isAllPaymentInSync(int $order_id): bool
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id'])
            ->where('order_id = :order_id')
            ->where('NOT is_synced')
            ->bindValue('order_id', $order_id);

        $query = static::prepareQuery($select);

        $notSynchedIds = $wpdb->get_col($query);

        return empty($notSynchedIds);
    }

    public static function orderHasPayment(int $order_id): bool
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id'])
            ->where('order_id = :order_id')
            ->limit(1)
            ->bindValue('order_id', $order_id);

        $query = static::prepareQuery($select);
        $ids = $wpdb->get_col($query);

        return !empty($ids);
    }

    public static function findOrderPaymentsNotInSync(int $order_id): array
    {
        return self::findBy(
            ['order_id = :order_id', 'NOT is_synced'],
            ['order_id' => $order_id],
            'payment_id', 'ASC'
        );
    }

    public static function findOrderPayments(int $order_id): array
    {
        return self::findBy(
            ['order_id = :order_id'],
            ['order_id' => $order_id],
            'payment_id', 'ASC'
        );
    }

    /**
     * @return bool if it was needed to mark it
     *
     * @throws TableOperationSqlException
     */
    public static function markPaymentAsSynced(array $payment): bool
    {
        if (!$payment['is_synced']) {
            PaymentModel::update(
                $payment['id'],
                ['is_synced' => true]
            );

            return true;
        }

        return false;
    }

    public static function markIdAsSynced(int $id): void
    {
        self::update(
            $id,
            ['is_synced' => true]
        );
    }

    public static function markPaymentIdAsPaid(int $payment_id): void
    {
        global $wpdb;
        $update = static::getUpdateHelper()
            ->cols(['is_paid' => true])
            ->where('payment_id = :payment_id')
            ->bindValue('payment_id', $payment_id);

        $query = static::prepareQuery($update);

        $affectedRows = $wpdb->query($query);

        if (false === $affectedRows) {
            throw new TableOperationSqlException($wpdb->last_error, static::getTableName(), __FUNCTION__, $query);
        }

        static::ensureAffectedRows($affectedRows, true);
    }

    public static function getPaymentIdByTrx(string $trx): ?int
    {
        $select = static::getSelectHelper()
            ->cols(['payment_id'])
            ->where('trx = :trx')
            ->bindValue('trx', $trx);

        $query = static::prepareQuery($select);

        global $wpdb;
        $result = $wpdb->get_var($query);
        if (null === $result) {
            throw new TableOperationSqlException($wpdb->last_error, static::getTableName(), __FUNCTION__, $query);
        }

        return $result;
    }

    public static function addPayment(int $order_id, int $payment_id, string $amount, bool $is_paid, ?string $trx = null)
    {
        return PaymentModel::create(
            [
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'amount' => $amount,
                'is_synced' => false,
                'is_paid' => $is_paid,
                'trx' => $trx,
            ]
        );
    }
}
