<?php

class Guard
{
    private $fp;
    private $file;

    public function __construct($type)
    {
        $tmp = sys_get_temp_dir();
        $processUser = posix_geteuid();
        $file = "$tmp/user.$processUser.$type.lock";
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
