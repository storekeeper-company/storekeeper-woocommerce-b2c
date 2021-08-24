<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskRateCalculator
{
    public const MINUTE_METRICS = 60;
    private $startDateTime;
    private $endDateTime;

    public function __construct(?string $now = null)
    {
        $this->endDateTime = $now;
        if (is_null($now)) {
            $this->endDateTime = date('Y-m-d H:i:s');
        }
        $this->startDateTime = date('Y-m-d H:i:s', strtotime("{$this->endDateTime} -".self::MINUTE_METRICS.' minutes'));
    }

    public function countIncoming(): int
    {
        return TaskModel::countTasksByCreatedDateTimeRange($this->startDateTime, $this->endDateTime);
    }

    public function calculateProcessed(): float
    {
        $taskCount = TaskModel::countTasksByCreatedDateTimeRange($this->startDateTime, $this->endDateTime);

        $taskDuration = $this->getTaskDurationSumInMinutes();

        if (empty($taskDuration)) {
            return 0;
        }

        $rate = self::MINUTE_METRICS * $taskCount / $taskDuration;

        return round($rate, 1);
    }

    private function getTaskDurationSumInMinutes(): float
    {
        $duration = TaskModel::getExecutionDurationSumByCreatedDateTimeRange($this->startDateTime, $this->endDateTime);
        if (is_null($duration)) {
            return 0;
        }

        // Seconds / 60 = Minutes
        return $duration / 60;
    }
}
