<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;

abstract class AbstractSyncIssue extends AbstractCommand
{
    /**
     * @throws BaseException
     */
    protected function writeToReportFile($fileName, $data)
    {
        $reportsDir = $this->getReportsDir();

        // Write to report file
        $reportFile = "$reportsDir/$fileName";
        if (!file_put_contents($reportFile, $data)) {
            throw new \Exception("Failed to write to file: $reportFile");
        }
    }

    /**
     * @return false|string
     */
    protected function readFromReportFile($fileName)
    {
        $reportsDir = $this->getReportsDir();
        $reportFile = "$reportsDir/$fileName";
        if (!file_exists($reportFile)) {
            throw new \RuntimeException("File does not exists: $reportFile");
        }

        // Getting and checking the content;
        $content = file_get_contents($reportFile);
        if (false === $content) {
            throw new \RuntimeException("Error reading file: $reportFile");
        }

        return $content;
    }

    protected function getReportsDir()
    {
        // Check if report dir exists, else create it
        $reportsDir = Core::getTmpBaseDir().DIRECTORY_SEPARATOR.'reports';
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0770, true);
        }

        return $reportsDir;
    }
}
