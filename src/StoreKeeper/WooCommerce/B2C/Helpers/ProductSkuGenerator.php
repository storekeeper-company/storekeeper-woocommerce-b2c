<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class ProductSkuGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    protected $ids = [];
    protected $failed_ids = [];

    public function __construct(array $ids)
    {
        $ids = array_map('intval', $ids);
        $this->ids = array_unique($ids);
        $this->logger = new NullLogger();
    }

    public function getFailedIds(): array
    {
        return $this->failed_ids;
    }

    public function generateFromTitle()
    {
        $this->logger->debug('Generating skus from titles', [
            'ids' => $this->ids,
        ]);
        $total = count($this->ids);
        foreach ($this->ids as $no => $id) {
            $product = wc_get_product($id);
            WordpressExceptionThrower::throwExceptionOnWpError($product, true);
            $context = [
                'product_id' => $id,
                'product_total' => $total,
                'product_no' => $no,
            ];
            if (empty($product)) {
                $this->logger->warning(__('Product not found', I18N::DOMAIN), $context);
                $this->failed_ids[] = $id;
            } elseif ($product instanceof \WC_Product) {
                $context['product'] = $product;
                $this->logger->debug('Product was found', $context);
                $current_sku = $product->get_sku('edit');
                if (empty($current_sku)) {
                    $this->logger->info(__('Product has empty sku', I18N::DOMAIN), $context);
                    try {
                        $title = $product->get_title();
                        if ($product instanceof \WC_Product_Variation) {
                            $parentProduct = new \WC_Product($product->get_parent_id());
                            $parentSku = $parentProduct->get_sku();
                            if (!empty($parentSku)) {
                                $new_sku = $parentSku.'-'.sanitize_title($product->get_attribute_summary());
                            } else {
                                $new_sku = sanitize_title($title.' '.$product->get_attribute_summary());
                            }
                        } else {
                            $new_sku = sanitize_title($title);
                        }
                        if (empty($new_sku)) {
                            throw new \Exception('Generating from title resulted in empty sku');
                        }
                        $product->set_sku($new_sku);
                        $product->save();

                        $this->logger->info(__('Sku was set for product', I18N::DOMAIN), $context + [
                            'sku' => $new_sku,
                        ]);
                    } catch (\Throwable $e) {
                        $this->failed_ids[] = $id;
                        $this->logger->error(__('Failed to set sku', I18N::DOMAIN), $context + [
                            'sku' => $new_sku,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // resave it, (fixes random plugins saving it only the post meta instead of wordpress table)
                    $product->set_sku($current_sku);
                    $product->save();

                    $this->logger->warning(__('Product already has sku set', I18N::DOMAIN), $context + [
                        'sku' => $current_sku,
                    ]);
                }
            } else {
                $this->failed_ids[] = $id;
                $this->logger->error('Product is not an instance of \WC_Product ', $context + [
                    'class' => get_class($product),
                ]);
            }
        }
    }
}
