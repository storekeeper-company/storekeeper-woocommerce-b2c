<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck\OrderChecker;
use StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck\ProductChecker;
use StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck\TaskChecker;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class SyncIssueCheck extends AbstractSyncIssue
{
    const REPORT_FILE = 'syncIssueCheck.json';

    public static function getShortDescription(): string
    {
        return __('Checks if there are any issues with the sync.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('This command checks if there are any issues with the sync. When it returns nothing it means there is nothing wrong. Otherwise, there is an issue.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    /**
     * @return mixed|void
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        //setup
        $this->setupApi();

        //setup vars
        $report_text_output = '';
        $report_data = [];

        //run checkers
        $ProductChecker = new ProductChecker($this->api);
        $TaskChecker = new TaskChecker();
        $OrderChecker = new OrderChecker($this->api);

        //perform reporting
        if (
            !$ProductChecker->isSuccess() ||
            !$TaskChecker->isSuccess() ||
            !$OrderChecker->isSuccess()) {
            //merge report text output and data
            $report_text_output .= $ProductChecker->getReportTextOutput();
            $report_data += $ProductChecker->getReportData();

            $report_text_output .= $TaskChecker->getReportTextOutput();
            $report_data += $TaskChecker->getReportData();

            $report_text_output .= $OrderChecker->getReportTextOutput();
            $report_data += $OrderChecker->getReportData();

            //echo & write
            $this->echoReport($report_text_output);
            $this->writeReport($report_data);
        }
    }

    private function echoReport(string $report_text_output)
    {
        // Getting the process owner: https://stackoverflow.com/a/16448131/6475074
        $hostname = gethostname();
        if (function_exists('posix_geteuid')) {
            $processUser = posix_geteuid();
        } else {
            $processUser = 'UNKNOWN';
        }
        $user = $processUser['name'];
        $website = get_site_url();
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);

        //Echo default output + potential extra report output
        $report_text_output = esc_html($report_text_output);
        echo "=== Description ===
hostname: $hostname
user: $user
website: $website
api: $api
".$report_text_output;

        $this->logger->debug('Echo\'d report');
    }

    private function writeReport(array $report_data)
    {
        $this->logger->debug('Writing report');

        $this->writeToReportFile(
            self::REPORT_FILE,
            json_encode($report_data)
        );

        $this->logger->debug('Wrote report');
    }
}
