<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;

abstract class AbstractLogsTab extends AbstractTab
{
    protected $items = [];

    protected $count = 0;

    protected function getStylePaths(): array
    {
        return [
            plugin_dir_url(__FILE__).'/../../../static/logs.tab.css',
        ];
    }

    protected function fetchData(string $modelClass, array $whereClauses = [], array $whereValues = []): array
    {
        global $wpdb;

        /** @var AbstractModel $model */
        $model = new $modelClass();
        $index = $this->getRequestTableIndex();
        $limit = $this->getRequestLimit();
        $sort = $this->getRequestSort();

        $select = $model->getSelectHelper()
            ->cols(['*'])
            ->offset($index * $limit)
            ->orderBy(['date_created '.strtoupper($sort)])
            ->limit($limit);

        foreach ($whereClauses as $whereClause) {
            $select->where($whereClause);
        }

        if (count($whereValues) > 0) {
            $select->bindValues($whereValues);
        }

        return $wpdb->get_results($model::prepareQuery($select), ARRAY_A);
    }

    protected function renderTable(array $columns)
    {
        $table = new TableRenderer();
        $table->setData($this->items);
        foreach ($columns as $column) {
            $table->addColumn(
                $column['title'],
                $column['key'],
                $column['bodyFunction'] ?? null,
                $column['headerFunction'] ?? null
            );
        }
        $table->render();
    }

    protected function renderPagination()
    {
        $count = $this->count;
        $limit = $this->getRequestLimit();
        $currentIndex = $this->getRequestTableIndex();
        $lastIndex = 0;
        if ($count > $limit) {
            $lastIndex = ceil($count / $limit) - 1;
        }

        $previousNavigation = $this->getPreviousNavigation($currentIndex);
        $nextNavigation = $this->getNextNavigation($currentIndex, $lastIndex);

        $results = esc_html__('results', I18N::DOMAIN);

        $pageInfo = esc_html(
            sprintf(
                __('%s of %s', I18N::DOMAIN),
                $currentIndex + 1,
                $lastIndex + 1
            )
        );
        echo <<<HTML
        <div class="storekeeper-pagination">
            <span>{$this->count} $results</span>
            $previousNavigation
            <span>$pageInfo</span>
            $nextNavigation
        </div>
        HTML;
    }

    public function renderDateCreated()
    {
        $sort = $this->getRequestSort();
        // Caret up character
        $caret = '&#9650;';
        remove_query_arg('sort');
        $url = add_query_arg('sort', 'asc');
        if ('asc' === $sort) {
            $url = add_query_arg('sort', 'desc');
            // Caret down character
            $caret = '&#9660;';
        }
        $url = esc_url($url);
        $caret = esc_html($caret);
        $dateString = esc_html__('Date', I18N::DOMAIN);
        echo <<<HTML
            $dateString <a href="$url">$caret</a>
            HTML;
    }

    public function renderDate($date)
    {
        if (!empty($date)) {
            $datetime = DatabaseConnection::formatFromDatabaseDateIfNotEmpty($date);
            $date = DateTimeHelper::formatForDisplay($datetime);
            echo esc_html($date);
        } else {
            echo '-';
        }
    }

    private function getPreviousNavigation($currentIndex): string
    {
        if ($currentIndex > 0) {
            $firstUrl = esc_url(remove_query_arg('table-index'));
            $previousUrl = esc_url(add_query_arg('table-index', $currentIndex - 1));

            return <<<HTML
                        <a href="$firstUrl" class="button">«</a>
                        <a href="$previousUrl" class="button">‹</a>
                    HTML;
        } else {
            return <<<HTML
                        <button class="button" disabled>«</button>
                        <button class="button" disabled>‹</button>
                    HTML;
        }
    }

    private function getNextNavigation($currentIndex, $lastIndex): string
    {
        if ($currentIndex < $lastIndex) {
            $nextUrl = esc_url(add_query_arg('table-index', $currentIndex + 1));
            $lastUrl = esc_url(add_query_arg('table-index', $lastIndex));

            return <<<HTML
                        <a href="$nextUrl" class="button">›</a>
                        <a href="$lastUrl" class="button">»</a>
                    HTML;
        } else {
            return <<<HTML
                        <button class="button" disabled>›</button>
                        <button class="button" disabled>»</button>
                    HTML;
        }
    }

    protected function getRequestTableIndex(): int
    {
        if (isset($_REQUEST['table-index'])) {
            return (int) $_REQUEST['table-index'];
        }

        return 0;
    }

    protected function getRequestLimit(): int
    {
        if (isset($_REQUEST['limit'])) {
            return (int) $_REQUEST['limit'];
        }

        return 20;
    }

    protected function getRequestSort(): string
    {
        if (isset($_REQUEST['sort']) && 'asc' === strtolower($_REQUEST['sort'])) {
            return 'asc';
        }

        return 'desc';
    }
}
