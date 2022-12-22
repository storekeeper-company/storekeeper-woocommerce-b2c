<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;

class MysqlLock
{
    /**
     * @var string
     */
    protected $lock;
    /**
     * @var int
     */
    private $timeout;


    private $is_locked = false;

    static $db;

    public function __construct(string $lock, int $timeout = 15)
    {
        if (\strlen($lock) > 64) {
            throw new \InvalidArgumentException('The maximum length of the lock name is 64 characters.');
        }

        $this->lock = $lock;
        $this->timeout = $timeout;
    }

    /**
     * @return mixed
     */
    public function getDb()
    {
        if( is_null(self::$db)){
            self::$db = new DatabaseConnection();
        }
        return self::$db;
    }
    public function lock(): void
    {
        if( $this->is_locked ){
            return; // already locked
        }
        $db = $this->getDb()->getConnection();
        $statement = $db->prepare('SELECT GET_LOCK(?,?)');
        $statement->bind_param('si',$this->lock,$this->timeout);
        $statement->execute();

        $result = $statement->get_result();
        $row = $result->fetch_row();
        if ($row[0] == 1) {
            /*
             * Returns 1 if the lock was obtained successfully.
             */
            $this->is_locked = true;
            return;
        }

        if ($row[0] === null) {
            /*
             *  NULL if an error occurred (such as running out of memory or the thread was killed with mysqladmin kill).
             */
            throw new \Exception('An error occurred while acquiring the lock');
        }

        throw new  \Exception("Lock time ($this->timeout) exceeded");
    }

    public function unlock(): void
    {
        $db = $this->getDb()->getConnection();
        $statement = $db->prepare('DO RELEASE_LOCK(?)');
        $statement->bind_param('s',$this->lock);
        $statement->execute();
        $this->is_locked = false;
    }

    function __destruct()
    {
        if( $this->is_locked ){
            $this->unlock();
        }
    }
}
