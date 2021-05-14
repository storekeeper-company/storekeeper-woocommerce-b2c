<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck;

interface SyncIssueCheckerInterface
{
    public function getReportTextOutput(): string;

    public function getReportData(): array;

    public function isSuccess(): bool;
}
