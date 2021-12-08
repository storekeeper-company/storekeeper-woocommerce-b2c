<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\I18N;

abstract class AbstractPage extends AbstractPageLike
{
    protected $slug = '';

    final public function getSlug(): string
    {
        return "storekeeper-$this->slug";
    }

    final public function getActionName(): string
    {
        return "load-storekeeper-page-$this->slug";
    }

    public $title = '';

    /** @var AbstractTab[] */
    private $tabs = [];

    private function getCurrentTab(): ?AbstractTab
    {
        $slug = $this->getRequestTabSlug();
        $tab = array_key_exists($slug, $this->tabs) ?
            $this->tabs[$slug] : current($this->tabs);

        if ($tab) {
            return $tab;
        }

        return null;
    }

    /** @return AbstractTab[] Returns the tags required for this page */
    abstract protected function getTabs(): array;

    public function __construct(string $title, string $slug)
    {
        $this->slug = $slug;
        $this->title = $title;
        foreach ($this->getTabs() as $tab) {
            $this->tabs[$tab->slug] = $tab;
        }
    }

    protected function getStylePaths(): array
    {
        return array_merge(
            [
                plugin_dir_url(__FILE__).'/../../static/default.page.css',
            ]
        );
    }

    public function initialize()
    {
        $this->checkExtensions();
        $this->triggerAction();
        $this->register();
        $this->render();
    }

    protected function checkExtensions(): void
    {
        $extensions = get_loaded_extensions();
        $missingExtensions = [];
        foreach (static::REQUIRED_PHP_EXTENSION as $wantedExtension) {
            if (!in_array($wantedExtension, $extensions)) {
                $missingExtensions[] = sprintf(__('PHP %s extension', I18N::DOMAIN), $wantedExtension);
            }
        }

        if (!empty($missingExtensions)) {
            AdminNotices::showError(
                sprintf(
                    esc_html__('The following required extensions are missing from your server: %s'),
                    implode(', ', $missingExtensions),
                ),
                esc_html__('Contact your server provider to enable these extensions for the StoreKeeper synchronization plugin to function properly.')
            );
        }
    }

    private function triggerAction()
    {
        do_action($this->getActionName());
    }

    final public function register(): void
    {
        parent::register();

        if ($tab = $this->getCurrentTab()) {
            $tab->register();
        }
    }

    public function render(): void
    {
        $page = $this->getRequestPage();
        echo "<div class='storekeeper-page storekeeper-page-$page'>";

        $this->renderTitle();

        $this->renderTabs();

        if ($tab = $this->getCurrentTab()) {
            $tab->executeCurrentAction();

            if ($tab->isRenderRestOtherTab()) {
                $this->renderTab();
            }
        } else {
            $this->renderTab();
        }

        echo '</div>';
    }

    private function renderTitle(): void
    {
        $title = __('StoreKeeper Sync Plugin', I18N::DOMAIN);
        if ($this->title) {
            $title .= ' - '.$this->title;
        }
        $title = esc_html($title);
        echo <<<HTML
<h1>$title</h1>
HTML;
    }

    private function renderTabs()
    {
        global $pagenow;
        $tabHtml = '';

        if (count($this->tabs) > 1) {
            $currentSlug = $this->getRequestTabSlug();
            foreach ($this->tabs as $slug => $tab) {
                $url = add_query_arg('page', $this->getRequestPage(), $pagenow);
                if ('' !== $slug) {
                    $url = add_query_arg('tab', $slug, $url);
                }

                $className = $currentSlug === $slug ? 'nav-tab-active' : '';
                $title = esc_html($tab->title);
                $tabHtml .= "<a href='".esc_url($url)."' class='nav-tab ".esc_attr($className)."'>$title</a>&nbsp;";
            }
        }
        echo <<<HTML
<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
    $tabHtml
</nav>
HTML;
    }

    private function renderTab(): void
    {
        try {
            if ($tab = $this->getCurrentTab()) {
                $slug = esc_html($tab->slug);
                echo "<div class='storekeeper-tab storekeeper-tab-$slug'>";

                $tab->render();

                echo '</div>';
            } else {
                $text = esc_html__('No tabs set for this page', I18N::DOMAIN);
                echo "<h1 style='text-align: center'>$text</h1>";
            }
        } catch (\Throwable $e) {
            AdminNotices::showException(
                $e,
                __('Failed to render the tab')
            );
        }
    }

    private function getRequestTabSlug(): string
    {
        $slug = sanitize_key($_REQUEST['tab'] ?? '');

        return $slug;
    }

    private function getRequestPage(): string
    {
        $page = sanitize_key($_REQUEST['page'] ?? '');

        return $page;
    }
}
