<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

class TriggerVariationSaveActionTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('parent_id')) {
            $debug = array_key_exists('debug', $task_options) ? $task_options['debug'] : false;
            $this->setDebug($debug);

            $parent_id = $this->getTaskMeta('parent_id');
            $parentProduct = new \WC_Product_Variable($parent_id);

            foreach ($parentProduct->get_visible_children() as $index => $visible_child_id) {
                do_action('woocommerce_save_product_variation', $visible_child_id, $index);
                $this->debug("Triggered action for $visible_child_id");
            }
        }
    }
}
