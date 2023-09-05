<?php

namespace StoreKeeper\WooCommerce\B2C\Database;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockTimeoutException;
use StoreKeeper\WooCommerce\B2C\Interfaces\LockInterface;

class MySqlLock implements LockInterface
{
    public static $database;

    /**
     * @var string
     */
    protected $lock;

    /**
     * @var int
     */
    private $timeout;
    private $isLocked = false;

    public function __construct(string $lock, int $timeout = 15)
    {
        if (\strlen($lock) > 64) {
            $lock = hash('sha256', $lock);
            $lock = 'sha256_'.substr($lock, 0, -8);
        }

        $this->lock = $lock;
        $this->timeout = $timeout;
    }

    public function getDatabase(): DatabaseConnection
    {
        if (is_null(self::$database)) {
            self::$database = new DatabaseConnection();
        }

        return self::$database;
    }

    /**
     * @throws LockTimeoutException
     * @throws LockException
     */
    public function lock(): bool
    {
        if ($this->isLocked) {
            return true; // already locked
        }

        $database = $this->getDatabase()->getConnection();
        $statement = $database->prepare('SELECT GET_LOCK(?,?)');
        $statement->bind_param('si', $this->lock, $this->timeout);
        $statement->execute();

        $result = $statement->get_result();
        $row = $result->fetch_row();
        if (1 === $row[0]) {
            /*
             * Returns 1 if the lock was obtained successfully.
             */
            $this->isLocked = true;

            return true;
        }

        if (null === $row[0]) {
            /*
             *  NULL if an error occurred (such as running out of memory or the thread was killed with mysqladmin kill).
             */
            throw new LockException('An error occurred while acquiring the lock');
        }

        throw new LockTimeoutException("Lock time ($this->timeout) exceeded");
    }

    public function unlock(): void
    {
        if ($this->isLocked) {
            $database = $this->getDatabase()->getConnection();
            $statement = $database->prepare('DO RELEASE_LOCK(?)');
            $statement->bind_param('s', $this->lock);
            $statement->execute();

            $this->isLocked = false;
        }
    }

    public function __destruct()
    {
        if ($this->isLocked) {
            $this->unlock();
        }
    }
}
