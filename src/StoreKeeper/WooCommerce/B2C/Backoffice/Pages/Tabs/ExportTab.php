<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\OverlayRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use Throwable;

class ExportTab extends AbstractTab
{
    use FormElementTrait;

    const EXPORT_ACTION = 'export-action';
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

        $this->addAction(self::EXPORT_ACTION, [$this, 'exportAction']);
        $this->addAction(self::SAVE_OPTIONS_ACTION, [$this, 'saveOptionsAction']);
    }

    protected function exportAction()
    {
        if (array_key_exists('type', $_REQUEST) && array_key_exists('lang', $_REQUEST)) {
            $this->handleExport(
                sanitize_key($_REQUEST['type']),
                sanitize_key($_REQUEST['lang'])
            );
        }
        wp_redirect(remove_query_arg(['type', 'action', 'lang']));
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderExport();

        $this->renderSettings();
    }

    private function renderExport()
    {
        echo $this->getFormStart();

        echo $this->getFormHeader(__('Export', I18N::DOMAIN));

        echo $this->getRequestHiddenInputs();

        echo $this->getFormHiddenInput('action', self::EXPORT_ACTION);

        echo $this->getFormGroup(
            __('Overwrite language (iso2)', I18N::DOMAIN),
            $this->getFormInput(
                'lang',
                __('Overwrite language (iso2)', I18N::DOMAIN),
                Language::getSiteLanguageIso2()
            )
        );

        $exportTypes = [
            FileExportTypeHelper::ALL => __('Export all'),
            FileExportTypeHelper::CUSTOMER => __('Export customers', I18N::DOMAIN),
            FileExportTypeHelper::TAG => __('Export tags', I18N::DOMAIN),
            FileExportTypeHelper::CATEGORY => __('Export categories', I18N::DOMAIN),
            FileExportTypeHelper::ATTRIBUTE => __('Export attributes', I18N::DOMAIN),
            FileExportTypeHelper::ATTRIBUTE_OPTION => __('Export attribute options', I18N::DOMAIN),
            FileExportTypeHelper::PRODUCT_BLUEPRINT => __('Export product blueprints', I18N::DOMAIN),
            FileExportTypeHelper::PRODUCT => __('Export products', I18N::DOMAIN),
        ];

        foreach ($exportTypes as $type => $label) {
            echo $this->getFormGroup(
                $label,
                $this->getFormButton(
                    __('Export', I18N::DOMAIN),
                    'button',
                    'type',
                    $type
                )
            );
        }

        echo $this->getFormEnd();
    }

    const NOT_MAPPED_VALUE = '-';

    private function renderSettings()
    {
        echo $this->getFormStart('post');

        echo $this->getFormHeader(__('Featured attribute export settings', I18N::DOMAIN));

        echo $this->getRequestHiddenInputs();

        echo $this->getFormHiddenInput('action', self::SAVE_OPTIONS_ACTION);

        $options = $this->getAttributeOptions();
        foreach (self::FEATURED_ATTRIBUTES_ALIASES as $alias) {
            $name = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
            $label = FeaturedAttributeOptions::getAliasName($alias);
            $value = FeaturedAttributeOptions::get($name, self::NOT_MAPPED_VALUE);
            echo $this->getFormGroup(
                $label,
                $this->getFormSelect($name, $options, $value)
            );
        }

        echo $this->getFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        echo $this->getFormEnd();
    }

    private function getAttributeOptions()
    {
        $notMapped = __('Not mapped', I18N::DOMAIN);
        $options = [];
        $options[self::NOT_MAPPED_VALUE] = "------ $notMapped ------";

        foreach (Attributes::getAllAttributes() as $attribute) {
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

    private function handleExport(string $type, string $lang)
    {
        $typeName = FileExportTypeHelper::getTypePluralName($type);
        $title = sprintf(
            __('Please wait and keep the page open while we are exporting your %s.', I18N::DOMAIN),
            strtolower($typeName)
        );
        $description = __('your export will be downloaded as soon the export finished.', I18N::DOMAIN);

        $overlay = new OverlayRenderer();
        $overlay->start($title, $description);

        try {
            $url = $this->runExport($type, $lang, $overlay);

            $this->initializeDownload($url);

            sleep(1); // wait for the download to start client sided

            $overlay->endWithRedirectBack();
        } catch (Throwable $throwable) {
            $overlay->renderError(
                $throwable->getMessage(),
                $throwable->getTraceAsString()
            );
            $overlay->end();
        } finally {
            $overlay->end();
        }
    }

    private function initializeDownload(string $url)
    {
        $basename = esc_js(basename($url));
        $url = esc_js($url);
        echo <<<HTML
<script>
    (function() {
        const link = document.createElement('a');
        link.setAttribute('href', '$url');
        link.setAttribute('download', '$basename');
        link.click();
    })();
</script>
HTML;
    }

    private function runExport($type, $lang, OverlayRenderer $overlay)
    {
        FileExportTypeHelper::ensureType($type);

        IniHelper::setIni(
            'max_execution_time',
            60 * 60 * 12, // Time in hours
            [$overlay, 'renderMessage']
        );
        IniHelper::setIni(
            'memory_limit',
            '512M',
            [$overlay, 'renderMessage']
        );

        $exportClass = FileExportTypeHelper::getClass($type);
        $export = new $exportClass();
        $export->runExport($lang);

        return $export->getDownloadUrl();
    }
}
