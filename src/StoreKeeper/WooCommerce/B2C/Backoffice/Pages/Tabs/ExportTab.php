<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\OverlayRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\ProductHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\ProductSkuGenerator;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use Throwable;

class ExportTab extends AbstractTab
{
    use FormElementTrait;

    const ACTION_EXPORT = 'export-action';
    const ACTION_GENERATE_SKU_FROM_TITLE = 'generate-sku-from-title';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::ACTION_EXPORT, [$this, 'exportAction']);
        $this->addAction(self::ACTION_GENERATE_SKU_FROM_TITLE, [$this, 'generateSkuFromTitleAction']);
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

    protected function generateSkuFromTitleAction()
    {
        $ids = ProductHelper::getProductsIdsWithoutSku();

        if (!empty($ids)) {
            $this->renderRestOtherTab = false; // so we can show the results better
            $logger = LoggerFactory::createWpAdminPrinter(new SkuLogFormatter());
            $generator = new ProductSkuGenerator($ids);
            $generator->setLogger($logger);
            $generator->generateFromTitle();

            $failed = $generator->getFailedIds();
            $backButton = $this->getFormLink(
                    remove_query_arg(['type', 'action', 'lang']),
                    __('Back to export', I18N::DOMAIN),
                    'button button-link'
                );
            if (empty($failed)) {
                echo '<div class="notice notice-success">';
                $title = esc_html__('All skus was generated successfully', I18N::DOMAIN);
                echo "<h4>$title</h4>";
            } else {
                echo '<div class="notice notice-warning">';
                $title = esc_html__('Failed to generate SKU for %s product(s)', I18N::DOMAIN);
                $title = sprintf($title, count($failed));
                echo "<h4>$title</h4>";
            }
            echo "<p>$backButton</p></div>";
        } else {
            wp_redirect(remove_query_arg(['type', 'action', 'lang']));
        }
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderExport();
    }

    private function renderExport()
    {
        $this->renderFormStart();

        $this->renderRequestHiddenInputs();

        $this->renderFormHiddenInput('action', self::ACTION_EXPORT);

        $this->renderInfo();
        $this->renderSelectedAttributes();
        $this->renderLanguageSelector();

        $exportTypes = [
            FileExportTypeHelper::CUSTOMER => __('Export customers', I18N::DOMAIN),
            FileExportTypeHelper::TAG => __('Export tags', I18N::DOMAIN),
            FileExportTypeHelper::CATEGORY => __('Export categories', I18N::DOMAIN),
            FileExportTypeHelper::ATTRIBUTE => __('Export attributes', I18N::DOMAIN),
            FileExportTypeHelper::ATTRIBUTE_OPTION => __('Export attribute options', I18N::DOMAIN),
            FileExportTypeHelper::PRODUCT_BLUEPRINT => __('Export product blueprints', I18N::DOMAIN),
            FileExportTypeHelper::PRODUCT => __('Export products', I18N::DOMAIN),
        ];
        $connected = StoreKeeperOptions::isConnected();
        foreach ($exportTypes as $type => $label) {
            $input = $this->getFormButton(
                __('Download export (csv)', I18N::DOMAIN),
                'button',
                'type',
                $type
            );
            if ($connected) {
                $input .= ' '.$this->getFormLink(
                    $this->getImportExportCenterUrl($type),
                    __('Go to backoffice import form', I18N::DOMAIN),
                    'button button-link',
                    '_blank'
                );
            }
            $this->renderFormGroup($label, $input);
        }

        $missing_sku = ProductHelper::getAmountOfProductsWithoutSku();
        $missing_var_sku = ProductHelper::getAmountOfProductVariationsWithoutSku();
        if ($missing_sku > 0 || $missing_var_sku > 0) {
            $this->renderProductsWithoutSku($missing_sku, $missing_var_sku);
        }
        echo '<hr/>';
        $this->renderFormGroup(
            __('Export full package'),
            $this->getFormButton(
                __('Download export (zip)', I18N::DOMAIN),
                'button',
                'type',
                FileExportTypeHelper::ALL
            )
        );

        $this->renderFormEnd();
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
        $url = esc_url($url);
        echo <<<HTML
<script>
    (function() {
        const link = document.createElement('a');
        link.setAttribute('target', '_blank');
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

    private function getSelectedAttributes(): array
    {
        $selectedAttributes = [];
        foreach (ExportSettingsTab::FEATURED_ATTRIBUTES_ALIASES as $alias) {
            $name = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
            $label = FeaturedAttributeOptions::getAliasName($alias);
            $value = FeaturedAttributeOptions::get($name);
            if (!is_null($value) && ExportSettingsTab::NOT_MAPPED_VALUE !== $value) {
                $selectedAttributes[$label] = $value;
            }
        }

        return $selectedAttributes;
    }

    private function renderInfo()
    {
        echo '<div class="notice notice-info">';
        $title = esc_html__('With the One Time Export you can export all the data from your WooCommerce webshop to your StoreKeeper BackOffice. After completing this export you should import the files into your StoreKeeper BackOffice.', I18N::DOMAIN);
        echo "<h4>$title</h4>";
        $import_export = esc_html__('Import & Export Center', I18N::DOMAIN);
        if (StoreKeeperOptions::isConnected()) {
            $import_export = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_attr($this->getImportExportCenterUrl()),
                $import_export
            );
        }
        echo '<p>';
        printf(
            esc_html__('You should generate all the export files and then go to the "%1$s" of this account.', I18N::DOMAIN),
            $import_export
        );
        echo '</p>';
        echo '<p>';
        echo esc_html__('The correct order of importing is the same as export below, so first customers,tags,categories ect.', I18N::DOMAIN);
        echo '</p>';
        echo '<p>';
        echo esc_html__('After you complete the full "One Time Export" procedure, be aware that from this moment on the management of your webshop goes through the StoreKeeper BackOffice.', I18N::DOMAIN);
        echo '</p>';
        echo '</div>';
    }

    private function renderProductsWithoutSku(int $count, int $var_count)
    {
        echo '<div class="notice notice-warning">';
        $title = '';
        if ($count) {
            $title .= sprintf(
                esc_html__('There are %s product(s) without sku.', I18N::DOMAIN),
                $count
            );
        }
        if ($var_count) {
            $title .= ' '.sprintf(
                esc_html__('There are %s variations(s) without sku.', I18N::DOMAIN),
                $var_count
            );
        }
        echo "<h4>$title</h4>";
        $expl = esc_html__('They will not be exported, because they cannot be matched back by sku, which will make duplicates when imported back. If the configurable product does not have sku, it\'s variations won\'t be exported as well.', I18N::DOMAIN);
        echo "<p>$expl</p>";

        $input = $this->getFormButton(
            __('Generate all missing sku from title', I18N::DOMAIN),
            'button',
            'action',
            self::ACTION_GENERATE_SKU_FROM_TITLE
        );
        echo "<p>$input</p>";
        echo '</div>';
    }

    private function renderSelectedAttributes()
    {
        $selectedAttributes = $this->getSelectedAttributes();
        $link = esc_html__('Click here to configure them', I18N::DOMAIN);
        $url = esc_url(admin_url('admin.php?page=storekeeper-tools&tab='.ExportSettingsTab::SLUG));

        if (empty($selectedAttributes)) {
            $message = esc_html__('Warning: You didn\'t set the settings yet for mapping fields, are you really sure?', I18N::DOMAIN);

            echo <<<HTML
                    <div class="notice notice-error">
                        <h4>$message</h4>
                        <a href="$url">$link</a><br /><br />
                    </div>
            HTML;
        }
    }

    private function getImportExportCenterUrl(?string $type = null): string
    {
        $url = StoreKeeperOptions::getBackofficeUrl().'#import-export/create/import';

        $exportTypes = [
            FileExportTypeHelper::CUSTOMER => 'customer',
            FileExportTypeHelper::TAG => 'productLabels',
            FileExportTypeHelper::CATEGORY => 'productCategory',
            FileExportTypeHelper::ATTRIBUTE => 'attribute',
            FileExportTypeHelper::ATTRIBUTE_OPTION => 'attributeOption',
            FileExportTypeHelper::PRODUCT_BLUEPRINT => 'productKind',
            FileExportTypeHelper::PRODUCT => 'product',
        ];

        if (array_key_exists($type, $exportTypes)) {
            return $url.'/'.$exportTypes[$type];
        }

        return $url;
    }

    private function renderLanguageSelector(): void
    {
        $siteLanguageIso2 = Language::getSiteLanguageIso2();
        $options = [
            'nl' => __('Dutch'),
            'en' => __('English'),
            'de' => __('German'),
        ];

        if (!array_key_exists($siteLanguageIso2, $options)) {
            $options[$siteLanguageIso2] = sprintf(
                    __('Site language (%s)', I18N::DOMAIN),
                    $siteLanguageIso2
                );
        }
        $this->renderFormGroup(
            __('Export language', I18N::DOMAIN),
            $this->getFormSelect(
                'lang',
                $options,
                esc_html($siteLanguageIso2)
            )
        );
    }
}
