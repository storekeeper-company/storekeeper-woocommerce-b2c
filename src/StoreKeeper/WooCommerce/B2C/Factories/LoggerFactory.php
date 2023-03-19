<?php

namespace StoreKeeper\WooCommerce\B2C\Factories;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class LoggerFactory
{
    const LOG_DIRECTORY = 'sk-log';

    /**
     * @param string $suffix
     *
     * @return string
     */
    public static function getWpLogDirectory($suffix = ''): ?string
    {
        $tmpBaseDir = Core::getTmpBaseDir();
        if (is_null($tmpBaseDir)) {
            return null;
        }
        $log_dir = $tmpBaseDir.DIRECTORY_SEPARATOR.self::LOG_DIRECTORY;
        if (!empty($suffix)) {
            $log_dir .= DIRECTORY_SEPARATOR.$suffix;
        }
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }

        return $log_dir;
    }

    public static function getLogLevel(): int
    {
        $level = Core::isDebug() ? Logger::DEBUG : Logger::WARNING;

        $custom = $_ENV['STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL'] ?? null;

        if (empty($custom) && defined('STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL')) {
            $custom = STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL ?? null;
        }
        if (!empty($custom)) {
            $levels = Logger::getLevels();
            if (array_key_exists($custom, $levels)) {
                $level = $levels[$custom];
            } else {
                throw new \Exception("Unknown log level '$custom' set in \$_ENV['STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL'], should be one of ".implode(', ', $levels));
            }
        }

        return $level;
    }

    /**
     * @param $type
     *
     * @throws \Exception
     */
    public static function create($type): Logger
    {
        $wpLogDirectory = self::getWpLogDirectory();
        if (is_null($wpLogDirectory)) {
            $streamHandler = new NullHandler(); // fallback when no directory found
        } else {
            $logLocation = $wpLogDirectory."/$type.log";
            $streamHandler = new StreamHandler(
                $logLocation,
                self::getLogLevel()
            );
        }

        return new Logger($type, [$streamHandler]);
    }

    /**
     * @param $type
     *
     * @return Logger
     *
     * @throws \Exception
     */
    public static function createConsole($type)
    {
        $logger = new Logger($type);
        $logger->pushHandler(new StreamHandler('php://stdout', self::getLogLevel()));

        return $logger;
    }

    public static function createWpAdminPrinter(?WpAdminFormatter $formatter = null, ?int $level = null)
    {
        $logger = new Logger('storekeeper');
        $handler = new WpAdminHandler($level ?? self::getLogLevel());
        if (empty($formatter)) {
            $formatter = new WpAdminFormatter();
        }
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Create an error task in the task queue.
     * This task cannot be executed, it is only for reporting the errors.
     *
     * @param string     $key
     * @param \Exception $exception
     *
     * @throws WordpressException
     */
    public static function createErrorTask($key, $exception, $custom_metadata = [])
    {
        $full_metadata = self::generateMetadata($key, $exception, $custom_metadata);

        TaskHandler::scheduleTask(
            TaskHandler::REPORT_ERROR,
            null,
            $full_metadata,
            true,
            TaskHandler::STATUS_FAILED
        );
    }

    /**
     * @param $custom_metadata
     */
    public static function generateMetadata(string $key, \Throwable $exception, $custom_metadata): array
    {
        $metadata = [
            'error-key' => $key,
            'exception-message' => $exception->getMessage(),
            'exception-code' => $exception->getCode(),
            'exception-trace' => $exception->getTraceAsString(),
            'exception-location' => $exception->getFile().':'.$exception->getLine(),
            'exception-class' => get_class($exception),
        ];

        $full_metadata = array_merge($metadata, $custom_metadata);

        return $full_metadata;
    }
}
