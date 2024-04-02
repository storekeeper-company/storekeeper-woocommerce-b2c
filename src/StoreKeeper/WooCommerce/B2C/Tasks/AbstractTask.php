<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\TaskHandlerTrait;

abstract class AbstractTask
{
    use LoggerAwareTrait;
    use TaskHandlerTrait;

    protected $taskMeta = [];
    protected $id = 0;
    protected $task_id;

    protected $debug = false;

    public function setTaskMeta(array $meta)
    {
        $this->taskMeta = $meta;
    }

    public function getTaskMeta($key = null)
    {
        if (null === $key) {
            return $this->taskMeta;
        }
        if (key_exists($key, $this->taskMeta)) {
            return $this->taskMeta[$key];
        }

        return null;
    }

    public function taskMetaExists($key)
    {
        return key_exists($key, $this->taskMeta);
    }

    /**
     * Increases the times-ran meta value by 1
     * If the meta value does not exist on the post, it will add it.
     */
    public function increaseTimesRan()
    {
        $data = TaskModel::get($this->getTaskId());
        if ($data) {
            $data['times_ran'] = intval($data['times_ran']) + 1;
            TaskModel::update($data['id'], $data);
        }
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function hasId()
    {
        return 0 !== $this->id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTaskId()
    {
        return $this->task_id;
    }

    public function setTaskId($task_id)
    {
        $this->task_id = $task_id;
    }

    protected $mainLanguage = 'nl';

    protected $itemsPerFetch = 100;

    /**
     * @var ApiWrapper
     */
    protected $storekeeper_api;

    /**
     * AbstractImport constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->setLogger(new NullLogger());
        $this->mainLanguage = Language::getSiteLanguageIso2();
        $this->storekeeper_api = StoreKeeperApi::getApiByAuthName();
    }

    /**
     * @param $task_options array
     *
     * @return void returns true in the import was succeeded
     *
     * @throws \Exception
     */
    abstract public function run(array $task_options = []): void;

    protected function setDebug($debug = false)
    {
        $this->debug = $debug;
    }

    protected function debug($message = 'Memory usage', $data = [])
    {
        if ($this->debug) {
            echo esc_html('['.date('Y-m-d H:i:s').'] '.$message.' '.json_encode($data).PHP_EOL);
            echo sprintf(
                'Memory usage: %.2f MB, peak: %.2f MB.',
                memory_get_usage() / 1024 / 1024,
                memory_get_peak_usage() / 1024 / 1024
            ).PHP_EOL.PHP_EOL;
        }
    }

    /**
     * Query the metadata of the task from wordpress, then set it in this instance.
     *
     * @return array
     */
    public function loadTaskMeta()
    {
        $this->taskMeta = $this->queryTaskMeta();

        return $this->taskMeta;
    }

    /**
     * Query the task meta from wordpress.
     *
     * @return array
     */
    public function queryTaskMeta()
    {
        $data = TaskModel::get($this->getTaskId());

        return $data ? $data['meta_data'] : null;
    }

    /**
     * Saves the current metadata to wordpress.
     */
    protected function saveTaskMeta()
    {
        // Old meta = meta currently saved in woocommerce
        $old_meta = $this->queryTaskMeta();

        // New meta = meta currently present in this instance
        $new_meta = &$this->taskMeta;

        /* Calculate the difference between the current taskmeta saved in wordpress
           and the taskmeta in this instance */
        $meta_diff = array_diff($new_meta, $old_meta);

        // Update the different meta values in wordpress
        foreach ($meta_diff as $key => $value) {
            WordpressExceptionThrower::throwExceptionOnWpError(
                update_post_meta($this->getTaskId(), $key, $value),
                true
            );
        }
    }
}
