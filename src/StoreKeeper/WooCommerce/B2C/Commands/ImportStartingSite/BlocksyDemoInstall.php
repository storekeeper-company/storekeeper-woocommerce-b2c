<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ImportStartingSite;

use Blocksy\DemoInstall;

class BlocksyDemoInstall extends DemoInstall
{
    protected array $themefiles;
    protected array $themes = [];
    protected const THEME_GET_KEY = ['content' ,'options','pages_ids_options' ,'widgets'];

    public function __construct(DemoInstall $oldDemo, array $themefiles)
    {
        $this->themefiles = $themefiles;

        foreach ($this->ajax_actions as $action) {
            remove_action(
                'wp_ajax_' . $action,
                [ $oldDemo, $action  ]
            );
        }
        parent::__construct();


    }

    public function fetch_single_demo($args = [])
    {
        $this->loadAllThemes();

        $args = wp_parse_args(
            $args,
            [
                'demo' => $args['demo'],
                'builder' => '',
                'field' => ''
            ]
        );

        $fullname = $args['demo'] . ':' . $args['builder'] ;
        foreach ($this->themes as $theme){
            if( $theme['fullname'] === $fullname){
                return $theme;
            }
        }

        return parent::fetch_single_demo($args);
    }

    public function fetch_all_demos()
    {
        $demos = []; // only show our themes
        $this->loadAllThemes();

        foreach ($this->themes as $theme){
            $demos[] = $theme;
        }
        return $demos;
    }

    protected function loadAllThemes(): void
    {
        foreach ($this->themefiles as $themefile){
            $this->loadThemeFile($themefile);
        }
    }
    protected function loadThemeFile($themefile): void
    {
        if (empty($this->themes[$themefile])) {
            $body = $this->getFullTheme($themefile);
            $body['fullname'] = $body['name'] . ':' . $body['builder'] ;
            $body = array_diff_key(
                $body,
                array_flip(self::THEME_GET_KEY)
            );
            $this->themes[$themefile] = $body;
        }
    }

    protected function getFullTheme($themefile): array
    {
        // todo load from url
        if (!is_readable($themefile)) {
            throw new \Exception("File $themefile is not readable");
        }
        $body = file_get_contents($themefile);
        $body = json_decode($body, true);
        if (!$body) {
            throw new \Exception("File $themefile does not contain valid json");
        }
        return $body;
    }


}
