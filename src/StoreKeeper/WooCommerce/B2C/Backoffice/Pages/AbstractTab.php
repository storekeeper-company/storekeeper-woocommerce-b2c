<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

abstract class AbstractTab extends AbstractPageLike
{
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

    final private function executeAction(string $action)
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

    public function register(): void
    {
        parent::register();

        $this->executeCurrentAction();
    }

    private function executeCurrentAction(): void
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
}
