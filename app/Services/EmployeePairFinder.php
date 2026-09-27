<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\EmployeeRecord;
use App\DTO\PairResult;
use App\DTO\ProjectOverlap;
use DateTimeInterface;

final class EmployeePairFinder
{
    public const bool COUNT_DAYS_INCLUSIVE = true;

    private const int SECONDS_PER_DAY = 86_400;

    /**
     * @param  iterable<EmployeeRecord>  $records
     * @return PairResult|null Null when no two employees ever overlapped on a project.
     */
    public function find(iterable $records): ?PairResult
    {
        $winner = null;
        $ties = 0;

        foreach ($this->overlapDaysByPair($this->groupPeriods($records)) as $empId1 => $partners) {
            foreach ($partners as $empId2 => $projectDays) {
                $candidate = [$empId1, $empId2, array_sum($projectDays), $projectDays];

                if ($winner === null || $candidate[2] > $winner[2]) {
                    [$winner, $ties] = [$candidate, 0];
                } elseif ($candidate[2] === $winner[2]) {
                    $ties++;
                    if ([$empId1, $empId2] < [$winner[0], $winner[1]]) {
                        $winner = $candidate;
                    }
                }
            }
        }

        if ($winner === null) {
            return null;
        }

        [$empId1, $empId2, $totalDays, $projectDays] = $winner;
        ksort($projectDays);

        $projects = [];
        foreach ($projectDays as $projectId => $days) {
            $projects[] = new ProjectOverlap($empId1, $empId2, $projectId, $days);
        }

        return new PairResult($empId1, $empId2, $totalDays, $projects, $ties);
    }

    /**
     * @param  iterable<EmployeeRecord>  $records
     * @return array<int, array<int, list<array{int, int}>>>
     */
    private function groupPeriods(iterable $records): array
    {
        $periods = [];

        foreach ($records as $record) {
            $periods[$record->projectId][$record->empId][] = [
                $this->dayNumber($record->dateFrom),
                $this->dayNumber($record->dateTo),
            ];
        }

        foreach ($periods as &$employees) {
            foreach ($employees as &$employeePeriods) {
                $employeePeriods = $this->mergePeriods($employeePeriods);
            }
            unset($employeePeriods);
        }
        unset($employees);

        return $periods;
    }

    /**
     * @param  list<array{int, int}>  $periods
     * @return list<array{int, int}>
     */
    private function mergePeriods(array $periods): array
    {
        sort($periods);

        $gap = self::COUNT_DAYS_INCLUSIVE ? 1 : 0;
        $merged = [array_shift($periods)];

        foreach ($periods as [$start, $end]) {
            $last = &$merged[array_key_last($merged)];

            if ($start <= $last[1] + $gap) {
                $last[1] = max($last[1], $end);
            } else {
                $merged[] = [$start, $end];
            }

            unset($last);
        }

        return $merged;
    }

    /**
     * @param  array<int, array<int, list<array{int, int}>>>  $periods
     * @return array<int, array<int, array<int, int>>>
     */
    private function overlapDaysByPair(array $periods): array
    {
        $daysByPair = [];

        foreach ($periods as $projectId => $employees) {
            ksort($employees);
            $empIds = array_keys($employees);

            foreach ($empIds as $i => $empId1) {
                foreach (array_slice($empIds, $i + 1) as $empId2) {
                    $days = $this->overlapDays($employees[$empId1], $employees[$empId2]);

                    if ($days > 0) {
                        $daysByPair[$empId1][$empId2][$projectId] = $days;
                    }
                }
            }
        }

        return $daysByPair;
    }

    /**
     * @param  list<array{int, int}>  $periodsA
     * @param  list<array{int, int}>  $periodsB
     */
    private function overlapDays(array $periodsA, array $periodsB): int
    {
        $days = 0;

        foreach ($periodsA as [$startA, $endA]) {
            foreach ($periodsB as [$startB, $endB]) {
                $start = max($startA, $startB);
                $end = min($endA, $endB);

                if ($start <= $end) {
                    $days += $end - $start + (self::COUNT_DAYS_INCLUSIVE ? 1 : 0);
                }
            }
        }

        return $days;
    }

    private function dayNumber(DateTimeInterface $date): int
    {
        return intdiv(gmmktime(0, 0, 0, (int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y')), self::SECONDS_PER_DAY);
    }
}
