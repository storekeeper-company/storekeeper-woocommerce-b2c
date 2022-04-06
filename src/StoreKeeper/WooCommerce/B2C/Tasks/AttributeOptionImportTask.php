<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\AttributeOptionImport;

class AttributeOptionImportTask extends AbstractTask
{
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $exceptions = [];
            $tag = new AttributeOptionImport(
                [
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $exceptions = array_merge($exceptions, $tag->run());

            $this->throwExceptionArray($exceptions);
        }

        return true;
    }
}
