<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\I18N;

class SkuLogFormatter extends \StoreKeeper\WooCommerce\B2C\Factories\WpAdminFormatter
{
    protected $last_product_id = null;

    protected function getOutputHtml(array $record): string
    {
        $output = '';
        $context = $record['context'] ?? [];
        $product_id = &$context['product_id'];
        if (!empty($product_id)) {
            $product = &$context['product'];
            if ($product instanceof \WC_Product) {
                $id = (int) $product->get_id();
                if ($this->isNextProduct($id)) {
                    if ($product instanceof \WC_Product_Variation) {
                        $parent_title = esc_html($product->get_parent_data()['title']);
                        $url = $this->getProductPageUrl($product->get_parent_id());
                        $title = esc_html($product->get_attribute_summary());
                        $output .=
                            '<h3>'
                            .sprintf(
                                esc_html__('Setting sku for product variation "%s" of product: '),
                                $title
                            )
                            ."<a href=\"$url\" target=\"_blank\">$parent_title</a>"
                            .'</h3>';
                    } else {
                        $title = esc_html($product->get_title());
                        $url = $this->getProductPageUrl($id);
                        $output .=
                            '<h3>'
                            .esc_html__('Setting sku for product: ', I18N::DOMAIN)
                            ."<a href=\"$url\" target=\"_blank\">$title</a>"
                            .'</h3>';
                    }
                    $this->setCurrentProduct($id);
                }
            } elseif ($this->isNextProduct($product_id)) {
                $this->setCurrentProduct($product_id);
                $output .=
                    '<h3>'
                    .esc_html__('Product with id=', I18N::DOMAIN)
                    .$product_id
                    .'</h3>';
            }

            $messages = [];
            $messages[] = esc_html($record['message']);
            if ($record['context']) {
                if (!empty($record['context']['sku'])) {
                    $messages[] .= esc_html__('Sku:').' '.esc_html($record['context']['sku']);
                }
                if (!empty($record['context']['error'])) {
                    $messages[] .= esc_html__('Error:').' '.esc_html($record['context']['error']);
                }
            }
            $output .= $this->applyColor($record['level'], implode('. ', $messages).'.');
        }

        return $output;
    }

    protected function isNextProduct(int $id): bool
    {
        return is_null($this->last_product_id) || $this->last_product_id !== $id;
    }

    protected function setCurrentProduct(int $id): void
    {
        $this->last_product_id = $id;
    }

    protected function getProductPageUrl(int $url_id)
    {
        $url = esc_attr(admin_url('post.php?action=edit&post='.$url_id));

        return $url;
    }
}
