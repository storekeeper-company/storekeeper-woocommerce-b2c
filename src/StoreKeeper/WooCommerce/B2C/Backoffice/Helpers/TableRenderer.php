<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Helpers;

class TableRenderer
{
    private $id = '';
    private $columns = [];

    public function __construct(?string $id = '')
    {
        $this->id = sanitize_key($id);
    }

    public function addColumn(
        string $title,
        string $key,
        ?callable $bodyFunction = null,
        ?callable $headerFunction = null
    ) {
        $this->columns[] = [
            'key' => $key,
            'title' => $title,
            'headerFunction' => $headerFunction,
            'bodyFunction' => $bodyFunction,
        ];
    }

    private $data = [];

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function render(?array $data = null)
    {
        if (is_array($data)) {
            $this->setData($data);
        }

        $this->renderTableStart();

        $this->renderTableHeader();

        $this->renderTableBody();

        $this->renderTableEnd();
    }

    private function renderTableStart(): void
    {
        if (!empty($this->id)) {
            // id is sanitized in constructor
            echo "<div id=\"$this->id\"></div>";
        }
        echo '<table class="wp-list-table widefat storekeeper-table">';
    }

    private function renderTableHeader(): void
    {
        echo '<thead><tr>';

        foreach ($this->columns as $column) {
            $key = esc_attr($column['key']);
            echo "<th class='storekeeper-header storekeeper-body-$key'>";

            if (is_callable($column['headerFunction'])) {
                call_user_func($column['headerFunction']);
            } else {
                echo esc_html($column['title']) ?? '';
            }

            echo '</th>';
        }

        echo '</tr></thead>';
    }

    private function renderTableBody(): void
    {
        echo '<tbody>';

        foreach ($this->data as $item) {
            echo '<tr>';

            foreach ($this->columns as $column) {
                $key = $column['key'];
                $keyEscaped = esc_attr($key);
                echo "<td class='storekeeper-body storekeeper-body-$keyEscaped'>";

                if (isset($item["function::$key"]) && is_callable($item["function::$key"])) {
                    call_user_func($item["function::$key"], $item[$key] ?? null, $item);
                } else {
                    if (isset($column['bodyFunction']) && is_callable($column['bodyFunction'])) {
                        call_user_func($column['bodyFunction'], $item[$key] ?? null, $item);
                    } else {
                        echo esc_html($item[$key] ?? '');
                    }
                }

                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
    }

    private function renderTableEnd(): void
    {
        echo '</table>';
    }
}
