<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;

class ExportSettingsTab extends AbstractTab
{
    use FormElementTrait;

    const SLUG = 'export-settings';
    const SAVE_OPTIONS_ACTION = 'save-options-action';

    const ALIAS_BRAND = 'brand';
    const ALIAS_BARCODE = 'barcode';
    const ALIAS_PRINTABLE_SHORTNAME = 'printable_shortname';
    const ALIAS_NEEDS_WEIGHT_ON_KASSA = 'needs_weight_on_kassa';
    const ALIAS_NEEDS_DESCRIPTION_ON_KASSA = 'needs_description_on_kassa';
    const ALIAS_DURATION_IN_SECONDS = 'duration_in_seconds';

    const ALIAS_MINIMAL_ORDER_QTY = 'minimal_order_qty';
    const ALIAS_IN_PACKAGE_QTY = 'in_package_qty';
    const ALIAS_IN_BOX_QTY = 'in_box_qty';
    const ALIAS_IN_OUTER_QTY = 'in_outer_qty';
    const ALIAS_UNIT_WEIGHT_IN_G = 'unit_weight_in_g';

    const FEATURED_ATTRIBUTES_ALIASES = [
        self::ALIAS_BRAND,
        self::ALIAS_BARCODE,
        self::ALIAS_PRINTABLE_SHORTNAME,
        self::ALIAS_NEEDS_WEIGHT_ON_KASSA,
        self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA,
        self::ALIAS_DURATION_IN_SECONDS,
        self::ALIAS_MINIMAL_ORDER_QTY,
        self::ALIAS_IN_PACKAGE_QTY,
        self::ALIAS_IN_BOX_QTY,
        self::ALIAS_IN_OUTER_QTY,
        self::ALIAS_UNIT_WEIGHT_IN_G,
    ];

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::SAVE_OPTIONS_ACTION, [$this, 'saveOptionsAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderSettings();
    }

    public const NOT_MAPPED_VALUE = '-';

    private function renderSettings()
    {
        $this->renderFormStart('post');

        $this->renderInfo();

        $this->renderFormHeader(__('Featured attributes', I18N::DOMAIN));

        $this->renderRequestHiddenInputs();

        $this->renderFormHiddenInput('action', self::SAVE_OPTIONS_ACTION);

        $options = $this->getAttributes();
        foreach (self::FEATURED_ATTRIBUTES_ALIASES as $alias) {
            $name = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
            $label = FeaturedAttributeOptions::getAliasName($alias);
            $value = FeaturedAttributeOptions::get($name, self::NOT_MAPPED_VALUE);
            $this->renderFormGroup(
                $label,
                $this->getFormSelect($name, $options, $value)
            );
        }

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function renderInfo()
    {
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('First set the export mappings below to make sure the export maps are as needed. Please pay attention to this, since there is NO do-overs on "one time import". After completing this you can to the "One Time Export" ', I18N::DOMAIN);

        echo '</p></div>';
    }

    private function getAttributes()
    {
        $notMapped = __('Not mapped', I18N::DOMAIN);
        $options = [];
        $options[self::NOT_MAPPED_VALUE] = "------ $notMapped ------";

        foreach (AttributeExport::getAllAttributes() as $attribute) {
            $options[$attribute['name']] = $attribute['label'];
        }

        return $options;
    }

    public function saveOptionsAction()
    {
        if (count($_POST) > 0) {
            $data = [];
            foreach (FeaturedAttributeOptions::FEATURED_ATTRIBUTES_ALIASES as $alias) {
                $constant = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
                $data[$constant] = sanitize_key($_POST[$constant]);
            }

            $duplicates = $this->getDuplicateData($data);
            if (count($duplicates) > 0) {
                $message = __('Attributes can only be used once', I18N::DOMAIN);
                AdminNotices::showError($message);
            } else {
                foreach ($data as $key => $value) {
                    FeaturedAttributeOptions::set($key, $value);
                }
            }
        }
    }

    private function getDuplicateData(array $data)
    {
        $uniques = [];
        $duplicates = [];

        foreach ($data as $key => $value) {
            if (self::NOT_MAPPED_VALUE !== $value && in_array($value, $uniques)) {
                $duplicates[] = $value;
            } else {
                $uniques[] = $value;
            }
        }

        return $duplicates;
    }
}
