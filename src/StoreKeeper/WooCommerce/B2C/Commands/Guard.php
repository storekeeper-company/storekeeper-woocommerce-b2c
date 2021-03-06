<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

class Guard
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
        $tmp = sys_get_temp_dir();
        if (!is_writable($tmp)) {
            throw new \Exception("$tmp is not writable");
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

    public function lock()
    {
        $this->fp = fopen($this->file, 'w+');
        if (flock($this->fp, LOCK_EX | LOCK_NB)) {
            fwrite($this->fp, getmypid());

            return true;
        }

        return false;
    }

    public function __destruct()
    {
        if ($this->fp) {
            flock($this->fp, LOCK_UN);
            fclose($this->fp);
        }
    }
}
