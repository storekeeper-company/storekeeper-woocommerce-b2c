<?php

namespace StoreKeeper\WooCommerce\B2C\Singletons;

use Aura\SqlQuery\QueryFactory;
use Aura\SqlQuery\QueryInterface;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;

/**
 * This singleton uses the following package: https://github.com/auraphp/Aura.SqlQuery/tree/2.x
 * Class QueryFactorySingleton.
 */
class QueryFactorySingleton extends QueryFactory
{
    private static $instance;

    public static function getInstance(): self
    {
        if (null === static::$instance) {
            static::$instance = new self('mysql');
        }

        return static::$instance;
    }

    public function prepare(QueryInterface $query): string
    {
        return $this->compileQuery($query->getStatement(), $query->getBindValues());
    }

    private function compileQuery(string $statement, array $bindValues)
    {
        global $wpdb;

        $formattedStatement = preg_replace('/:\w+/m', '%s', $statement);
        $requiredBindings = $this->getRequiredBindings($statement, $bindValues);

        // Check if there are any value to bind to
        if (count($bindValues) > 0) {
            // put the bindValues in the correct order for wpdb->prepare to work correctly.
            $data = [];
            foreach ($requiredBindings as $requiredBinding) {
                $data[] = $bindValues[$requiredBinding];
            }

            // We create a new WPDB object so we can use the prepare function, even when the glocal $wpdb object is not there.
            return $wpdb->prepare($formattedStatement, $data);
        }

        return $formattedStatement;
    }

    protected function getRequiredBindings(string $statement, array $bindValues)
    {
        preg_match_all('/:(\w+)/m', $statement, $matches, PREG_PATTERN_ORDER);

        if (2 !== count($matches) || count(array_unique($matches[1])) !== count($bindValues)) {
            throw new \Exception('The statement does not match the bind values');
        }

        return $matches[1];
    }

    public function prepareWithDatabaseConnection(
        DatabaseConnection $connection,
        string $statement,
        array $bindValues
    ): string {
        $formattedStatement = preg_replace('/:\w+/m', '%s', $statement);
        $requiredBindings = $this->getRequiredBindings($statement, $bindValues);

        // Check if there are any value to bind to
        if (count($bindValues) > 0) {
            // put the bindValues in the correct order for wpdb->prepare to work correctly.
            $data = [];
            foreach ($requiredBindings as $requiredBinding) {
                $value = $bindValues[$requiredBinding];
                $escaped = $connection->escapeSQLString($value);
                $data[] = "'$escaped'";
            }

            return call_user_func_array('sprintf', array_merge([$formattedStatement], $data));
        }

        return $formattedStatement;
    }
}
