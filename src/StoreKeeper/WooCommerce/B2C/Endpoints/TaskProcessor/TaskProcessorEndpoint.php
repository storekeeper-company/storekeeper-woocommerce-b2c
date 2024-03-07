<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor;

use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\InvalidRunnerException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;

class TaskProcessorEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'process-tasks';
    public const TASK_LIMIT = 100;

    /**
     * @throws WpRestException
     * @throws InvalidRunnerException
     */
    public function handle()
    {
        $response = null;
        CommandRunner::withCronCheck(CronRegistrar::RUNNER_CRONTAB_API, function () {
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [], [
                'limit' => self::TASK_LIMIT,
            ]);
        }, function ($throwable) {
            $this->logger->error($throwable->getMessage());
            throw new WpRestException($throwable->getMessage(), 401, $throwable);
        }, function ($invalidRunnerException) use (&$response) {
            $response['error'] = $invalidRunnerException->getMessage();
        });

        return $response;
    }

    final public function handleRequestSilently(\WP_REST_Request $request): ?\WP_REST_Response
    {
        $response = $this->handleRequest($request);
        if (500 === $response->get_status()) {
            return $response;
        }

        return null;
    }
}
