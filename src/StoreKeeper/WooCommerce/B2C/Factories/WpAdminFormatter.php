<?php

namespace StoreKeeper\WooCommerce\B2C\Factories;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;

class WpAdminFormatter extends NormalizerFormatter
{
    protected $logLevels = [
        Logger::DEBUG => '#CCCCCC',
        Logger::INFO => '#72aee6',
        Logger::NOTICE => '#107484',
        Logger::WARNING => '#dba617',
        Logger::ERROR => '#d63638',
        Logger::CRITICAL => '#4c0f10',
        Logger::ALERT => '#4c0f10',
        Logger::EMERGENCY => '#4c0f10',
    ];

    final public function format(array $record): string
    {
        $output = $this->getOutputHtml($record);

        return wp_kses($output, HtmlEscape::ALLOWED_ALL_SAFE);
    }

    protected function applyColor($level, string $output): string
    {
        $color = $this->getColor($level);
        $output = "<div style=\"color:$color\">$output</div>";

        return $output;
    }

    protected function getOutputHtml(array $record): string
    {
        $output = esc_html($record['level_name']).' '.esc_html($record['message']);
        if ($record['context']) {
            $output .= ' '.esc_html(json_encode($record['context']));
        }
        $output = $this->applyColor($record['level'], $output);

        return $output;
    }

    protected function getColor($level): string
    {
        $color = $this->logLevels[$level];

        return $color;
    }
}
