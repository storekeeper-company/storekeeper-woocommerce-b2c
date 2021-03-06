<?php

namespace StoreKeeper\WooCommerce\B2C\Database;

use Aura\SqlQuery\QueryInterface;
use Exception;
use mysqli;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Singletons\QueryFactorySingleton;

class DatabaseConnection
{
    /**
     * @var mysqli
     */
    protected $connection;

    /**
     * Get the mysqli connection.
     */
    public function getConnection(): mysqli
    {
        if (null == $this->connection) {
            // Set up the connection to the database if it does not exist already
            $this->connection = $this->createConnection();
        }

        return $this->connection;
    }

    /**
     * Sets up the right connection to the database.
     *
     * @throws Exception
     */
    private function createConnection(): mysqli
    {
        if (Core::isTest()) {
            // for tests we need to reuse the current db
            // because it has already existing transaction
            global $wpdb;
            if (!empty($wpdb) && $wpdb->dbh instanceof mysqli) {
                return $wpdb->dbh;
            } else {
                throw new Exception('Failed to copy database connection from $wpdb');
            }
        } else {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if (!$connection) {
                throw new BaseException('No DB connection made');
            }

            return $connection;
        }
    }

    /**
     * Query sql in the connected database.
     *
     * @param $sql
     *
     * @return \mysqli_result|bool
     *
     * @throws Exception
     */
    public function querySql(string $sql)
    {
        // Use the query function to query the database
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        if (!$result) {
            throw new Exception("Query exited with error \"{$connection->error}\".");
        }

        return $result;
    }

    public function escapeSQLString(string $sql): string
    {
        return $this->getConnection()->escape_string($sql);
    }

    public function getRow(string $sql)
    {
        $result = $this->querySql($sql);
        if (is_bool($result)) {
            return $result;
        }

        return $result->fetch_row();
    }

    public function getArray(string $sql)
    {
        $result = $this->querySql($sql);
        if (is_bool($result)) {
            return $result;
        }

        return (array) $result->fetch_object();
    }

    public function getResults($sql)
    {
        $result = $this->querySql($sql);
        if (is_bool($result)) {
            return $result;
        }

        return $result->fetch_all();
    }

    public function prepare(QueryInterface $query)
    {
        $queryInstance = QueryFactorySingleton::getInstance();

        return $queryInstance->prepareWithDatabaseConnection(
            $this,
            $query->getStatement(),
            $query->getBindValues()
        );
    }
}
