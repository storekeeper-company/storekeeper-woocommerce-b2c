<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskLogsTab extends AbstractLogsTab
{
    public const DO_SINGLE_ACTION = 'do-single-action';
    public const DO_MULTIPLE_ACTIONS = 'do-multiple-actions';
    public const RETRY_ACTION = 'retry';
    public const MARK_ACTION = 'mark';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(
            self::DO_SINGLE_ACTION,
            [$this, 'singleRowAction']
        );
        $this->addAction(
            self::DO_MULTIPLE_ACTIONS,
            [$this, 'multipleRowsAction']
        );
    }

    public function singleRowAction()
    {
        if (array_key_exists('selected', $_GET)) {
            $this->doRowAction(
                [
                    (int) $_GET['selected'],
                ],
                $this->getRowAction(),
            );
        }
        $this->clearArgs();
    }

    public function multipleRowsAction()
    {
        if (array_key_exists('selected', $_POST) && array_key_exists('rowAction', $_POST)) {
            $rowAction = sanitize_key($_POST['rowAction']);
            $selected = $this->sanitizeIntArray($_POST['selected']);
            $this->doRowAction($selected, $rowAction);
        }
        $this->clearArgs();
    }

    private function doRowAction(array $taskIds, string $rowAction)
    {
        global $wpdb;

        $status = null;
        switch ($rowAction) {
            case self::RETRY_ACTION:
                $status = TaskHandler::STATUS_NEW;
                break;
            case self::MARK_ACTION:
                $status = TaskHandler::STATUS_SUCCESS;
                break;
        }

        if (!is_null($status)) {
            $taskIds = $this->sanitizeIntArray($taskIds);
            $in = "'".implode("','", $taskIds)."'";
            $update = TaskModel::getUpdateHelper()
                ->cols(['status' => $status])
                ->where("id IN ($in)");

            $query = TaskModel::prepareQuery($update);

            $affectedRows = $wpdb->query($query);

            TaskModel::ensureAffectedRows($affectedRows);
        }
    }

    public function render(): void
    {
        list($whereClauses, $whereValues) = $this->getTaskWhereClauses();
        $this->items = $this->fetchData(TaskModel::class, $whereClauses, $whereValues);
        $this->count = TaskModel::count($whereClauses, $whereValues);

        $this->renderTaskFilter();

        $url = $this->getActionUrl(self::DO_MULTIPLE_ACTIONS);
        $url = esc_url($url);
        echo "<form action='$url' method='post'>";

        $this->renderTaskMassAction();

        $this->renderPagination();

        $this->renderTable(
            [
                [
                    'title' => 'massAction',
                    'key' => 'massAction',
                    'headerFunction' => [$this, 'renderSelectAll'],
                    'bodyFunction' => [$this, 'renderSelectTask'],
                ],
                [
                    'title' => __('ID', I18N::DOMAIN),
                    'key' => 'id',
                ],
                [
                    'title' => __('Message', I18N::DOMAIN),
                    'key' => 'title',
                    'bodyFunction' => [$this, 'renderMessage'],
                ],
                [
                    'title' => __('Date', I18N::DOMAIN),
                    'key' => TaskModel::FIELD_DATE_CREATED,
                    'headerFunction' => [$this, 'renderDateCreated'],
                    'bodyFunction' => [$this, 'renderDate'],
                ],
                [
                    'title' => __('Log type', I18N::DOMAIN),
                    'key' => 'type',
                ],
                [
                    'title' => __('Status', I18N::DOMAIN),
                    'key' => 'status',
                    'bodyFunction' => [$this, 'renderTaskStatus'],
                ],
                [
                    'title' => __('Times ran', I18N::DOMAIN),
                    'key' => 'times_ran',
                ],
                [
                    'title' => __('Action', I18N::DOMAIN),
                    'key' => 'action',
                    'bodyFunction' => [$this, 'renderTaskActions'],
                ],
            ]
        );

        echo '</form>';

        $this->renderPagination();
    }

    public function renderSelectAll()
    {
        echo <<<HTML
        <input type="checkbox" id="select-all">
        <script>
            (function () {
                const all = document.getElementById('select-all');
                all.onclick = function () {
                    document.querySelectorAll('[data-select]')
                        .forEach(element => {element.checked = this.checked});
                }
            })()
        </script>
        HTML;
    }

    public function renderSelectTask($value, $task): void
    {
        $id = esc_attr($task['id']);
        echo <<<HTML
        <input type="checkbox" value="$id" name="selected[]" data-select>
        HTML;
    }

    public function renderTaskActions($value, $task): void
    {
        $id = $task['id'];
        $status = $task['status'];

        echo '<div class="storekeeper-status">';
        switch ($status) {
            case TaskHandler::STATUS_SUCCESS:
                echo <<<HTML
                    <span class="storekeeper-status-success"></span>
                    HTML;
                break;
            case TaskHandler::STATUS_PROCESSING:
                echo <<<HTML
                    <span class="storekeeper-status-warning"></span>
                    HTML;
                break;
            case TaskHandler::STATUS_FAILED:
                $retry = esc_html__('Retry', I18N::DOMAIN);
                $mark = esc_html__('Mark as success', I18N::DOMAIN);

                $retryUrl = add_query_arg([
                    'selected' => $id,
                    'rowAction' => self::RETRY_ACTION,
                ],
                    $this->getActionUrl(self::DO_SINGLE_ACTION)
                );
                $retryUrl = esc_url($retryUrl);

                $markUrl = add_query_arg([
                    'selected' => $id,
                    'rowAction' => self::MARK_ACTION,
                ],
                    $this->getActionUrl(self::DO_SINGLE_ACTION)
                );
                $markUrl = esc_url($markUrl);

                echo <<<HTML
                    <span class="storekeeper-status-danger" style="margin-top:8px"></span>
                    <div class="button-group">
                        <a class="button" href="$retryUrl">$retry</a>
                        <a href="$markUrl" class="button button-primary">$mark</a>
                    </div>
                    HTML;
                break;
            default:
                echo <<<HTML
                    <span class="storekeeper-status-secondary"></span>
                    HTML;
                break;
        }

        echo '</div>';
    }

    public function renderMessage(string $value, array $task)
    {
        echo $value;

        if (TaskHandler::STATUS_FAILED === $task['status']) {
            if ($errorOutput = unserialize($task['meta_data'])) {
                $exceptionMessageMaxLength = 80;
                $exceptionMessage = esc_html($errorOutput['exception-message']);
                if (strlen($exceptionMessage) > $exceptionMessageMaxLength) {
                    $exceptionMessage = trim(substr($exceptionMessage, 0, $exceptionMessageMaxLength - 3)).'...';
                }
                echo <<<HTML
                </br>
                <small style="color:darkred">$exceptionMessage</small>
HTML;
            }
        }
    }

    public function renderTaskStatus($value, $task)
    {
        if (TaskHandler::STATUS_FAILED === $task['status']) {
            echo '<a class="dialog-logs" href="javascript:;" data-id="'.esc_attr($task['id']).'">'.TaskHandler::getStatusLabel($task['status']).'</a>';
            if ($errorOutput = unserialize($task['meta_data'])) {
                $trace = $errorOutput['exception-trace'];
                $replace_pairs = [
                    rtrim(WP_PLUGIN_DIR, '/').'/' => '[WP-PLUGINS]/',
                    rtrim(ABSPATH, '/').'/' => '[WP]/',
                ];
                $trace = strtr($trace, $replace_pairs);
                $location = strtr($errorOutput['exception-location'] ?? '', $replace_pairs);

                echo '<div id="error-message-'.esc_attr($task['id']).'" style="display: none">';
                echo '<h3><strong style="color:darkred">'.esc_html($errorOutput['exception-class']).': '.esc_html($errorOutput['exception-message']).'</strong></h3>';
                if (isset($errorOutput['error-key'])) {
                    echo esc_html__('Error key', I18N::DOMAIN).': '.esc_html($errorOutput['error-key']).'<br>';
                }
                if (isset($errorOutput['exception-reference'])) {
                    echo esc_html__('Error reference', I18N::DOMAIN).': '.esc_html($errorOutput['exception-reference']).':<br>';
                }
                if (isset($errorOutput['plugin-version'])) {
                    echo esc_html__('StoreKeeper plugin version', I18N::DOMAIN).': '.esc_html($errorOutput['plugin-version']);

                    if (version_compare($errorOutput['plugin-version'], STOREKEEPER_WOOCOMMERCE_B2C_VERSION, '<')) {
                        echo ' <small style="color:darkred">('.esc_html__('this task was run with older version', I18N::DOMAIN).')</small>';
                    }
                    echo '<br>';
                }

                echo '<pre>'.esc_html($location).'</pre>';

                if (isset($errorOutput['exception-difference'])) {
                    echo esc_html__('Extra metadata', I18N::DOMAIN).':<br>';
                    $extrasDifferences = $errorOutput['exception-difference'];
                    $shopExtras = $extrasDifferences['shop-extras'] ?? [];
                    $backofficeExtras = $extrasDifferences['backoffice-extras'] ?? [];
                    if (count($shopExtras) > 0 || count($backofficeExtras) > 0) {
                        echo '<strong>'.esc_html__('Shop', I18N::DOMAIN).'</strong><br>';
                        $this->generateExtrasTable($shopExtras);

                        echo '<br><strong>'.esc_html__('Backoffice', I18N::DOMAIN).'</strong><br>';
                        $this->generateExtrasTable($backofficeExtras);
                    }
                    echo '<br>';
                }

                echo ''.esc_html__('Stack Trace', I18N::DOMAIN).':<br>';
                echo '<div>';
                foreach ($replace_pairs as $from => $to) {
                    echo esc_html($to.' => '.$from).'<br/>';
                }
                echo '</div>';
                echo '<pre>'.esc_html($trace).'</pre>';
                echo '</div>';
            }
        } else {
            echo TaskHandler::getStatusLabel($task['status']);
        }
    }

    private function getTaskWhereClauses(): array
    {
        $whereClauses = [];
        $whereValues = [];

        $status = $this->getRequestStatus();
        if (in_array($status, TaskHandler::STATUSES)) {
            $whereClauses[] = 'status = :status';
            $whereValues['status'] = $status;
        }

        $taskType = $this->getRequestTaskType();
        if (in_array($taskType, TaskHandler::TYPE_GROUPS)) {
            $whereClauses[] = 'type_group = :type_group';
            $whereValues['type_group'] = $taskType;
        }

        $searchString = $this->getSearchString();
        if ($searchString) {
            if (is_numeric($searchString)) {
                $whereClauses[] = '(id = :id OR title LIKE :title)';
                $whereValues['id'] = $searchString;
            } else {
                $whereClauses[] = 'title LIKE :title';
            }
            $whereValues['title'] = "%$searchString%";
        }

        return [$whereClauses, $whereValues];
    }

    private function renderTaskFilter()
    {
        $taskTypeSelect = $this->generateTaskTypeSelect();
        $taskStatusSelect = $this->generateTaskStatusSelect();
        $fuzzySearchInput = $this->generateFuzzySearchInput();

        $hiddenTypeHtml = $this->getHiddenInputs(['task-type']);
        $hiddenStatusHtml = $this->getHiddenInputs(['task-status']);

        $filter = esc_html__('Apply filter', I18N::DOMAIN);
        echo <<<HTML
        <form class="actions" style="display: flex; align-items: start">
            $hiddenTypeHtml
            $hiddenStatusHtml
            $taskTypeSelect
            $taskStatusSelect
            <button type="submit" class="button">$filter</button>
            
            $fuzzySearchInput
        </form>
        HTML;
    }

    private function generateFuzzySearchInput(): string
    {
        $currentSearchString = $this->getSearchString();
        $searchPlaceholderText = esc_html__('Search', I18N::DOMAIN);
        $searchHelperText = esc_html__('Enter message or back reference/post/task ID', I18N::DOMAIN);
        $goText = esc_html__('Go', I18N::DOMAIN);

        return <<<HTML
                    <div class="search-box">
                        <input type="text" name="search" id="search" class="postform" value="$currentSearchString" placeholder="$searchPlaceholderText..."/>
                        <button type="submit" class="button">$goText</button>
                        </br>                     
                        
                        <small style="margin-left:2px; color: #767676;">$searchHelperText</small>
                    </div>
               HTML;
    }

    private function generateTaskTypeSelect(): string
    {
        $currentType = $this->getRequestTaskType();
        $optionLabel = esc_html__('Select log type', I18N::DOMAIN);
        $optionHtml = "<option value=''>$optionLabel</option>";
        foreach (TaskHandler::TYPE_GROUPS as $type) {
            $selected = $currentType === $type ? 'selected' : '';
            $label = esc_html(TaskHandler::getTypeGroupTitle($type));
            $type = esc_attr($type);
            $optionHtml .= "<option value='$type' $selected>$label</option>";
        }

        return <<<HTML
                    <select name="task-type" id="task-type" class="postform">$optionHtml</select>
               HTML;
    }

    private function generateTaskStatusSelect(): string
    {
        $currentStatus = $this->getRequestStatus();
        $optionLabel = __('Select log status', I18N::DOMAIN);
        $optionHtml = "<option value=''>$optionLabel</option>";
        foreach (TaskHandler::STATUSES as $status) {
            $selected = $currentStatus === $status ? 'selected' : '';
            $label = esc_html(TaskHandler::getStatusLabel($status));
            $status = esc_attr($status);
            $optionHtml .= "<option value='$status' $selected>$label</option>";
        }

        return <<<HTML
                    <select name="task-status" id="task-status" class="postform">$optionHtml</select>
               HTML;
    }

    private function renderTaskMassAction()
    {
        $currentAction = $this->getRowAction();
        $optionLabel = esc_html__('Select action', I18N::DOMAIN);
        $optionHtml = "<option value=''>$optionLabel</option>";
        $rowActions = [
            self::RETRY_ACTION => esc_html__('Retry', I18N::DOMAIN),
            self::MARK_ACTION => esc_html__('Mark as success', I18N::DOMAIN),
        ];
        foreach ($rowActions as $value => $label) {
            $selected = $currentAction === $value ? 'selected' : '';
            $optionHtml .= "<option value='$value' $selected>$label</option>";
        }
        $apply = esc_html__('Apply', I18N::DOMAIN);
        echo <<<HTML
            <select name="rowAction" id="row-action" class="storekeeper-apply">$optionHtml</select>
            <button type="submit" class="button storekeeper-apply">$apply</button>
        HTML;
    }

    private function getHiddenInputs(array $exclude = []): string
    {
        $html = '';

        $queries = ['page', 'tab', 'limit', 'table-index', 'task-type', 'task-status'];
        foreach ($queries as $query) {
            if (in_array($query, $exclude)) {
                continue;
            }

            if (isset($_REQUEST[$query])) {
                $value = sanitize_key($_REQUEST[$query]);
                $query = esc_attr($query);
                $html .= "<input type='hidden' name='$query' value='$value' />";
            }
        }

        return $html;
    }

    private function sanitizeIntArray(array $taskIds): array
    {
        $taskIds = array_map('intval', $taskIds);

        return $taskIds;
    }

    private function getRequestStatus(): string
    {
        if (isset($_REQUEST['task-status'])) {
            return sanitize_key($_REQUEST['task-status']);
        }

        return '';
    }

    private function getRequestTaskType(): string
    {
        if (isset($_REQUEST['task-type'])) {
            return sanitize_key($_REQUEST['task-type']);
        }

        return '';
    }

    private function getSearchString(): string
    {
        if (isset($_REQUEST['search'])) {
            return sanitize_text_field($_REQUEST['search']);
        }

        return '';
    }

    private function getRowAction(): ?string
    {
        if (isset($_REQUEST['rowAction'])) {
            return sanitize_key($_REQUEST['rowAction']);
        }

        return '';
    }

    protected function clearArgs(): void
    {
        wp_redirect(remove_query_arg(['selected', 'action', 'rowAction']));
    }

    private function generateExtrasTable(array $extras): void
    {
        if (count($extras) > 0) {
            $tableHeaders = [];
            foreach ($extras as $extra) {
                foreach ($extra as $key => $extraData) {
                    if (!in_array($key, $tableHeaders, true)) {
                        $tableHeaders[] = $key;
                    }
                }
            }

            echo '<table class="table table-bordered"><thead><tr>';

            foreach ($tableHeaders as $tableHeader) {
                echo '<th>'.esc_html($tableHeader).'</th>';
            }
            echo '</tr><tbody>';

            $rowIds = array_column($extras, OrderExport::EXTRA_ROW_ID_KEY);
            array_multisort(
                $rowIds,
                SORT_ASC,
                $extras
            );

            foreach ($extras as $extra) {
                echo '<tr>';
                foreach ($tableHeaders as $tableHeader) {
                    if (isset($extra[$tableHeader])) {
                        echo '<td>'.esc_html($extra[$tableHeader]).'</td>';
                    } else {
                        echo '<td>-</td>';
                    }
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<i>'.__('No extra metadata found.', I18N::DOMAIN).'</i><br>';
        }
    }
}
