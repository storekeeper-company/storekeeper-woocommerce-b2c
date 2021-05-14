<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
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
        $index = isset($_REQUEST['table-index']) ? $_REQUEST['table-index'] : 0;
        $limit = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 20;

        $select = $model->getSelectHelper()
            ->cols(['*'])
            ->offset($index * $limit)
            ->orderBy(['date_created'])
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
        $limit = (int) ($_REQUEST['limit'] ?? 20);
        $currentIndex = (int) ($_REQUEST['table-index'] ?? 0);
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

    private function getPreviousNavigation($currentIndex): string
    {
        if ($currentIndex > 0) {
            $firstUrl = esc_attr(remove_query_arg('table-index'));
            $previousUrl = esc_attr(add_query_arg('table-index', $currentIndex - 1));

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
            $nextUrl = esc_attr(add_query_arg('table-index', $currentIndex + 1));
            $lastUrl = esc_attr(add_query_arg('table-index', $lastIndex));

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
}
