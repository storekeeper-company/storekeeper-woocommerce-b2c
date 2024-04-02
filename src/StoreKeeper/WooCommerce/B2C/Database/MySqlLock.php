<?php

namespace StoreKeeper\WooCommerce\B2C\Database;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockTimeoutException;
use StoreKeeper\WooCommerce\B2C\Interfaces\LockInterface;

class MySqlLock implements LockInterface
{
    public const HASH = 'sha256';
    public static $database;

    protected string $lock;
    protected string $hashedLock;
    private int $timeout;
    private bool $isLocked = false;

    public function __construct(string $lock, int $timeout = 15)
    {
        $this->lock = $lock;
        // lock is per db server, add file path to make sure we don't block another instances
        $this->lock .= '|'.__FILE__;
        $this->hashedLock = substr(''.self::HASH.'_'.hash(self::HASH, $this->lock), 0, 64);
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
        $statement->bind_param('si', $this->hashedLock, $this->timeout);
        $statement->execute();

        $result = $statement->get_result();
        if (false === $result) {
            throw new \Exception("Failed to get lock: ($statement->error) $statement->error");
        }
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
            throw new LockException("An error occurred while acquiring the lock ($this->lock, $this->hashedLock)");
        }

        throw new LockTimeoutException("Lock time ($this->timeout) exceeded ($this->lock, $this->hashedLock)");
    }

    public function unlock(): void
    {
        if ($this->isLocked) {
            $database = $this->getDatabase()->getConnection();
            $statement = $database->prepare('DO RELEASE_LOCK(?)');
            $statement->bind_param('s', $this->hashedLock);
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
