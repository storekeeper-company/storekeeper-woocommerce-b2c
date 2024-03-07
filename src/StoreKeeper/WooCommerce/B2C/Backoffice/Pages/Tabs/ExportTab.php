<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use Monolog\Logger;
use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\FileExport\ExportEndpoint;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\ProductHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\ProductSkuGenerator;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class ExportTab extends AbstractTab
{
    use FormElementTrait;

    public const ACTION_GENERATE_SKU_FROM_TITLE = 'generate-sku-from-title';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);
        $this->addAction(self::ACTION_GENERATE_SKU_FROM_TITLE, [$this, 'generateSkuFromTitleAction']);
    }

    public function register(): void
    {
        parent::register();
        $this->doRegisterScripts();
    }

    private function doRegisterScripts(): void
    {
        wp_enqueue_script('exportScript', plugin_dir_url(__FILE__).'../../static/backoffice.pages.tabs.export.js');
        wp_enqueue_script('exportSweetalertScript', plugin_dir_url(__FILE__).'../../static/vendors/sweetalert2/sweetalert.min.js');
        // So we can pass values to javascript file
        wp_localize_script('exportScript', 'exportSettings',
            [
                'url' => rest_url(EndpointLoader::getFullNamespace().'/'.ExportEndpoint::ROUTE),
                'translations' => [
                    'Your file has been generated' => esc_html__('Your file has been generated', I18N::DOMAIN),
                    'Your download will start in a few seconds. If not, you can download the file manually using the link below' => esc_html__('Your download will start in a few seconds. If not, you can download the file manually using the link below', I18N::DOMAIN),
                    'Please wait and keep the page and popup window open while we are preparing your export' => esc_html__('Please wait and keep the page and popup window open while we are preparing your export', I18N::DOMAIN),
                    'Preparing export' => esc_html__('Preparing export', I18N::DOMAIN),
                    'Stop exporting' => esc_html__('Stop exporting', I18N::DOMAIN),
                    'Size' => esc_html__('Size', I18N::DOMAIN),
                    'Export failed' => esc_html__('Export failed', I18N::DOMAIN),
                    'Something went wrong during export or server timed out. You can try manual export via command line, do you want to read the guide?' => esc_html__('Something went wrong during export or server timed out. You can try manual export via command line, do you want to read the guide?', I18N::DOMAIN),
                    'No, thanks' => esc_html__('No, thanks', I18N::DOMAIN),
                    'Yes, please' => esc_html__('Yes, please', I18N::DOMAIN),
                ],
            ]);
    }

    protected function generateSkuFromTitleAction(): void
    {
        $ids = ProductHelper::getProductsIdsWithoutSku();

        if (!empty($ids)) {
            $this->renderRestOtherTab = false; // so we can show the results better
            $logger = LoggerFactory::createWpAdminPrinter(new SkuLogFormatter(), Logger::INFO);
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

    private function renderExport(): void
    {
        $this->renderFormStart();

        $this->renderRequestHiddenInputs();

        $this->renderInfo();
        $this->renderSelectedAttributes();
        $this->renderHelp();
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
                'button export-button',
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

            if (FileExportTypeHelper::TAG === $type) {
                $input .= '<br><div class="mt-1">'.$this->getFormCheckbox(FileExportTypeHelper::TAG.'-skip-empty-tags').' '.__(
                    'Skip empty tags (tags with no products attached)',
                    I18N::DOMAIN
                ).'</div>';
            }

            if (FileExportTypeHelper::PRODUCT === $type) {
                $input .= '<br><div class="mt-1">'.$this->getFormCheckbox($type.'-all-products').' '.__(
                    'Include all not active products',
                    I18N::DOMAIN
                ).'</div>';
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
                'button export-button',
                'type',
                FileExportTypeHelper::ALL
            ).'<br><div class="mt-1">'.$this->getFormCheckbox(FileExportTypeHelper::ALL.'-all-products').' '.__(
                'Include all not active products',
                I18N::DOMAIN
            ).'</div><div>'.$this->getFormCheckbox(FileExportTypeHelper::ALL.'-skip-empty-tags').' '.__(
                'Skip empty tags (tags with no products attached)',
                I18N::DOMAIN
            ).'</div>'
        );

        $this->renderFormEnd();
    }

    private function renderHelp(): void
    {
        $documentationText = esc_html__('See documentation', I18N::DOMAIN);

        $guides = [
            esc_html__('Check if `wp-cli` is installed in the website\'s server.', I18N::DOMAIN).
            " <a target='_blank' href='".BackofficeCore::DOCS_WPCLI_LINK."'>{$documentationText}</a>",
            esc_html__('Open command line and navigate to website directory', I18N::DOMAIN).': <code>cd '.ABSPATH.'</code>',
            sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-all</code>', esc_html__('full package', I18N::DOMAIN)),
            [
                'parent' => esc_html__('or alternatively, you can export per file.', I18N::DOMAIN),
                'children' => [
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-customer</code>', esc_html__('customers', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-tag</code>', esc_html__('tags', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-category</code>', esc_html__('categories', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-attribute</code>', esc_html__('attributes', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-attribute-option</code>', esc_html__('attribute options', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-product-blueprint</code>', esc_html__('product blueprints', I18N::DOMAIN)),
                    sprintf(esc_html__('Run %s to export %s.', I18N::DOMAIN), '<code>wp sk file-export-product</code>', esc_html__('products', I18N::DOMAIN)),
                ],
            ],
            esc_html__('Each run will return the path of the exported file.', I18N::DOMAIN),
        ];

        $guidesHtml = static::generateOrderedListHtml($guides);

        echo $this->getFormLink('javascript:;', esc_html__('Manual export from command line using wp-cli'), 'toggle-help');

        echo "<div class='help-section' style='display:none;'>";
        $this->renderFormGroup(
            esc_html__('Manual export guide', I18N::DOMAIN),
            $guidesHtml
        );
        echo '</div>';
    }

    private function getSelectedAttributes(): array
    {
        $selectedAttributes = [];
        foreach (ExportSettingsTab::FEATURED_ATTRIBUTES_ALIASES as $alias) {
            $name = FeaturedAttributeExportOptions::getAttributeExportOptionConstant($alias);
            $label = FeaturedAttributes::getAliasName($alias);
            $value = FeaturedAttributeExportOptions::get($name);
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
