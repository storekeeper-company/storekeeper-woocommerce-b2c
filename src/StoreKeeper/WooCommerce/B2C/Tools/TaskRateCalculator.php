<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskRateCalculator
{
    public const MINUTE_METRICS = 60;
    private $startDateTime;
    private $endDateTime;

    /**
     * @throws \Exception
     */
    public function __construct(?\DateTime $now = null)
    {
        $this->endDateTime = $now;
        if (is_null($now)) {
            $this->endDateTime = DateTimeHelper::currentDateTime();
        }
        $this->startDateTime = clone $this->endDateTime;
        $this->startDateTime->sub(new \DateInterval('PT'.self::MINUTE_METRICS.'M'));
    }

    public function countIncoming(): int
    {
        return TaskModel::countTasksByCreatedDateTimeRange($this->startDateTime, $this->endDateTime);
    }

    public function calculateProcessed(): float
    {
        $taskCount = TaskModel::countTasksByProcessedDateTimeRange($this->startDateTime, $this->endDateTime);

        $taskDuration = $this->getTaskDurationSumInMinutes();

        if (empty($taskDuration)) {
            return 0;
        }

        $rate = self::MINUTE_METRICS * $taskCount / $taskDuration;

        return round($rate, 1);
    }

    private function getTaskDurationSumInMinutes(): float
    {
        $duration = TaskModel::getExecutionDurationSumByProcessedDateTimeRange($this->startDateTime, $this->endDateTime);
        if (is_null($duration)) {
            return 0;
        }

        // Seconds / 60 = Minutes
        return $duration / 60;
    }
}
