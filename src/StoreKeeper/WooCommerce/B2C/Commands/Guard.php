<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Interfaces\LockInterface;

class Guard implements LockInterface
{
    /**
     * @var
     */
    private $fp;
    /**
     * @var string
     */
    private $file;
    /**
     * @var string
     */
    private $type;

    /**
     * Guard constructor.
     *
     * @throws \Exception
     */
    public function __construct(string $class)
    {
        $tmp = Core::getTmpBaseDir();

        if (!is_writable($tmp)) {
            $possibleTmpDirectories = Core::getPossibleTmpDirs();
            throw new \Exception(implode(', ', $possibleTmpDirectories).' are not writable. At least one of it needs to be writable.');
        }
        if (function_exists('posix_geteuid')) {
            $processUser = posix_geteuid();
        } else {
            $processUser = sha1(__FILE__); // fallback to have it working per installation
        }
        $this->type = preg_replace('/\\\\+/', '_', $class);
        $file = "$tmp/user.$processUser.$this->type.lock";
        $this->file = $file;
    }

    /**
     * @throws LockActiveException
     */
    public function lock(): bool
    {
        $this->fp = fopen($this->file, 'w+');
        if (flock($this->fp, LOCK_EX | LOCK_NB)) {
            fwrite($this->fp, __CLASS__);

            return true;
        }

        throw new LockActiveException("There is a running instance of this lock ({$this->type}");
    }

    public function unlock()
    {
        flock($this->fp, LOCK_UN);
        fclose($this->fp);
    }

    public function __destruct()
    {
        if ($this->fp) {
            $this->unlock();
        }
    }
}
