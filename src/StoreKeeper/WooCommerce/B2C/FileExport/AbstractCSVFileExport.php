<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use Psr\Log\LoggerInterface;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExportSpreadSheet;

abstract class AbstractCSVFileExport extends AbstractFileExport implements IFileExportSpreadSheet
{
    protected function getFileType()
    {
        return 'csv';
    }

    /**
     * @var false|resource
     */
    private $file;

    private function setFile()
    {
        $this->file = fopen($this->filePath, 'w');
        if (!$this->file) {
            throw new \Exception('Unable to open file: '.$this->filePath);
        }
    }

    private function setHeaders()
    {
        $paths = $this->getPaths();
        $this->writeFieldsToCsv(
            array_map(
                function ($path) {
                    return "path://$path";
                },
                array_keys($paths)
            )
        );
        $this->writeFieldsToCsv(array_values($paths));
    }

    /**
     * AbstractCSVFileExport constructor.
     *
     * @throws \Exception
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
        $this->setFile();
        $this->setHeaders();
    }

    /**
     * Writes $lineData to the SpreadSheet.
     */
    protected function writeLineData(array $lineData)
    {
        $this->validateLineData($lineData);

        $fields = [];
        foreach ($this->getPaths() as $path => $label) {
            $value = '';
            if (array_key_exists($path, $lineData)) {
                $value = $lineData[$path];
            }
            $fields[] = $value;
        }

        $this->writeFieldsToCsv($fields);
    }

    private function validateLineData(array $lineData)
    {
        $paths = $this->getPaths();
        $diff = array_diff_key($lineData, $paths);
        if (count($diff) > 0) {
            $keys = array_keys($diff);
            $keyString = join(', ', $keys);
            $values = array_values($diff);
            $valueString = join(', ', $values);
            throw new \Exception("There where left over keys: $keyString ($valueString)");
        }
    }

    private function writeFieldsToCsv(array $fields)
    {
        if (!fputcsv($this->file, $this->parseFields($fields))) {
            throw new \Exception('Unable to write to file '.$this->filePath);
        }
    }

    private function parseFields(array $fields): array
    {
        return array_map(
            function ($field) {
                return self::parseFieldValue($field);
            }, $fields
        );
    }

    public static function parseFieldValue($value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        }

        if (empty($value)) {
            $value = '';
        }

        return $value;
    }
}
