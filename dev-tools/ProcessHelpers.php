<?php

class ProcessHelpers
{
    const TASK_LOCK_FILE = 'processTasks.lock';
    const LAST_RUN_FILE = 'last.run';

    public static function isCli()
    {
        return 'cli' === php_sapi_name();
    }

    public static function updateLastRunTime()
    {
        return file_put_contents(self::getLastRunFileLocation(), date('Y-m-d H:i:s'));
    }

    /**
     * @return string
     */
    protected static function getLastRunFileLocation()
    {
        return __DIR__.'/'.self::LAST_RUN_FILE;
    }

    public static function echoLogs($log_content = 'Memory usage', $data = [])
    {
        echo '['.date('Y-m-d H:i:s').'] '.$log_content.' '.json_encode($data).PHP_EOL;
        echo sprintf(
                'Memory usage: %.2f MB, peak: %.2f MB.',
                (memory_get_usage() / 1024 / 1024),
                (memory_get_peak_usage() / 1024 / 1024)
            ).PHP_EOL.PHP_EOL;
    }
}
