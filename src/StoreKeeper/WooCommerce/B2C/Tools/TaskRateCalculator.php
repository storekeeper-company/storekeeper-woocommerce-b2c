<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskRateCalculator
{
    private $tasks;
    private $minuteMetrics;
    private $startDateTime;
    private $endDateTime;

    public function __construct(array $tasks, $now = null, $minuteMetrics = 60)
    {
        $this->endDateTime = $now;
        if (is_null($now)) {
            $this->endDateTime = date('Y-m-d H:i:s');
        }
        $this->minuteMetrics = $minuteMetrics;
        $this->startDateTime = date('Y-m-d H:i:s', strtotime("{$this->endDateTime} -{$minuteMetrics} minutes"));
        $this->tasks = $tasks;
    }

    public function calculateIncoming(): float
    {
        $oldestTaskDate = $this->getOldestTaskDateWithinRange();

        if (is_null($oldestTaskDate)) {
            return 0;
        }

        $timeDifferenceInMinutes = abs(strtotime($this->endDateTime) - strtotime($oldestTaskDate)) / $this->minuteMetrics;
        $taskCount = count($this->tasks);
        $rate = $this->minuteMetrics * $taskCount / $timeDifferenceInMinutes;

        return round($rate, 1);
    }

    public function calculateProcessed()
    {
        $taskCount = count($this->tasks);

        $taskDuration = $this->getTaskDurationSumInMinutes();

        if (empty($taskDuration)) {
            return 0;
        }

        $rate = $this->minuteMetrics * $taskCount / $taskDuration;

        return round($rate, 1);
    }

    private function getOldestTaskDateWithinRange()
    {
        $dates = TaskModel::getTasksByCreatedDateTimeRange($this->startDateTime, $this->endDateTime, 1, 'ASC');

        return $dates[0]['date_created'] ?? null;
    }

    private function getTaskDurationSumInMinutes()
    {
        $duration = TaskModel::getExecutionDurationSumByCreatedDateTimeRange($this->startDateTime, $this->endDateTime);
        if (empty($duration)) {
            return 0;
        }

        return $duration->duration_total / 60;
    }
}
