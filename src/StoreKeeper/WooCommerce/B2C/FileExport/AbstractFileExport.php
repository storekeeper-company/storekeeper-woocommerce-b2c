<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;

abstract class AbstractFileExport implements IFileExport
{
    const EXPORT_DIR = ABSPATH.'wp-content/uploads/storekeeper-exports';

    /**
     * @var string
     */
    protected $filePath;

    /**
     * Sets the export path.
     */
    private function setFilePath()
    {
        if (!file_exists(self::EXPORT_DIR)) {
            if (!mkdir(self::EXPORT_DIR, 0777, true)) {
                throw new Exception('Failed to create export dir @ '.self::EXPORT_DIR);
            }
        }

        $filename = $this->getType().'-'.time().'.'.$this->getFileType();
        $this->filePath = self::EXPORT_DIR.'/'.$filename;
    }

    protected function getFileType()
    {
        return 'zip';
    }

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * AbstractFileExport constructor.
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->setFilePath();
    }

    /**
     * Maps an simple array where the $getKeyCallable returns the key,
     * and the value of the array is the value of the map.
     */
    protected function keyValueMapArray(array $array, callable $getKeyCallable): array
    {
        $map = [];

        foreach ($array as $item) {
            $key = $getKeyCallable($item);
            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * Returns a relative file path for url usage.
     */
    public function getDownloadUrl(): string
    {
        $filename = basename($this->filePath);
        $wpContentPath = ABSPATH.'/wp-content';
        $relativePath = substr(dirname($this->filePath), strlen($wpContentPath));

        return content_url("$relativePath/$filename");
    }

    protected function reportUpdate(int $total, int $index, string $description)
    {
        $current = $index + 1;
        $percentage = $current / $total * 100;
        $nicePercentage = number_format($percentage, 2, '.', '');
        $this->logger->info("($nicePercentage%) $description");
    }
}
