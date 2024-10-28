<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class CustomerImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;
    /**
     * This value is used to limite the import of attributes.
     *
     * @var int
     */
    private $storekeeper_id = 0;

    /**
     * AttributeImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id']);
        parent::__construct($settings);
    }

    public static function getMainLanguage()
    {
        return Language::getSiteLanguageIso2();
    }

    protected function getModule()
    {
        return 'ShopModule';
    }

    protected function getFunction()
    {
        return 'listCustomerRelations';
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'is_options__=',
                'val' => '1',
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $f;
    }

    protected function processItem(Dot $dotObject, array $options = []): ?int
    {
        print_r($dotObject);
        $title = $this->getTranslationIfRequired($dotObject, 'label');

    }

    protected function getImportEntityName(): string
    {
        return __('product attributes', I18N::DOMAIN);
    }
}
