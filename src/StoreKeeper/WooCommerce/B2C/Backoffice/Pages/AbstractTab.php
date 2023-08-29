<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

abstract class AbstractTab extends AbstractPageLike
{
    /**
     * @var bool if the default render should be called after the action
     */
    protected $renderRestOtherTab = true;

    public $slug = '';

    public $title = '';

    public $actions = [];

    final protected function addAction(string $action, callable $function)
    {
        $this->actions[$action] = $function;
    }

    final public function getActionUrl(string $action)
    {
        return add_query_arg('action', $action);
    }

    private function executeAction(string $action)
    {
        if (array_key_exists($action, $this->actions)) {
            call_user_func_array($this->actions[$action], []);
        }
    }

    public function __construct(string $title, string $slug = '')
    {
        $this->slug = $slug;
        $this->title = $title;
    }

    public function executeCurrentAction(): void
    {
        $action = $this->getRequestAction();
        if (array_key_exists($action, $this->actions)) {
            $this->executeAction($action);
        }
    }

    private function getRequestAction()
    {
        $action = sanitize_key($_REQUEST['action'] ?? '');

        return $action;
    }

    public function isRenderRestOtherTab(): bool
    {
        return $this->renderRestOtherTab;
    }

    public static function generateOrderedListHtml(array $list)
    {
        $orderedListHtml = '';
        foreach ($list as $key => $item) {
            if (!is_array($item)) {
                $orderedListHtml .= '<p style="white-space: pre-line;">'.($key + 1).'. '.$item.'</p>';
            } else {
                $alphabet = range('a', 'z');
                $orderedListHtml .= '<p style="white-space: pre-line;">'.($key + 1).'. '.$item['parent'].'</p>';

                $subGuides = $item['children'];
                $alphabetCounter = 0;
                foreach ($subGuides as $subGuide) {
                    $orderedListHtml .= '<p style="white-space: pre-line; margin-left: 1.5rem;">'.$alphabet[$alphabetCounter].'. '.$subGuide.'</p>';
                    ++$alphabetCounter;
                }
            }
        }

        return $orderedListHtml;
    }
}
