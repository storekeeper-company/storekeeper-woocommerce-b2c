<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;

abstract class AbstractFileExport implements IFileExport
{
    public static function getExportDir()
    {
        return WP_CONTENT_DIR.'/uploads/storekeeper-exports';
    }

    /**
     * @var string
     */
    protected $filePath;

    /**
     * Sets the export path.
     */
    private function setFilePath()
    {
        $export_dir = self::getExportDir();
        if (!file_exists($export_dir)) {
            if (!mkdir($export_dir, 0777, true)) {
                throw new \Exception('Failed to create export dir @ '.$export_dir);
            }
        }

        $filename = $this->getExportFilename();
        $this->filePath = $export_dir.'/'.$filename;
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
    public function __construct(?LoggerInterface $logger = null)
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
        $wpContentPath = WP_CONTENT_DIR;
        $relativePath = substr(dirname($this->filePath), strlen($wpContentPath));

        return content_url("$relativePath/$filename");
    }

    /**
     * Returns a relative file path for url usage.
     */
    public function getFilename(): string
    {
        return basename($this->filePath);
    }

    /**
     * Returns a relative file path for url usage.
     */
    public function getSize(): string
    {
        $bytes = filesize($this->filePath);

        if ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            $bytes .= ' bytes';
        } elseif (1 === $bytes) {
            $bytes .= ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    protected function reportUpdate(int $total, int $index, string $description)
    {
        $current = $index + 1;
        $percentage = $current / $total * 100;
        $nicePercentage = number_format($percentage, 2, '.', '');
        $this->logger->info("($nicePercentage%) $description");
    }

    private function getExportFilename(): string
    {
        $url = site_url('', 'http');
        if (!empty($url)) {
            $url = str_replace('http://', '', $url);
            $url = sanitize_title($url).'-';
        } else {
            $url = '';
        }

        return $url.$this->getType().'-'.time().'.'.$this->getFileType();
    }
}
