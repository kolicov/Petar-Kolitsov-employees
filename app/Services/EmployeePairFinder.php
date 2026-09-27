<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\EmployeeRecord;
use App\DTO\PairResult;
use App\DTO\ProjectOverlap;

final class EmployeePairFinder
{
    public const bool COUNT_DAYS_INCLUSIVE = true;

    /**
     * @param  iterable<EmployeeRecord>  $records
     * @return PairResult|null Null when no two employees ever overlapped on a project.
     */
    public function find(iterable $records): ?PairResult
    {
        $recordsByProject = $this->groupByProject($records);

        $totals = [];
        foreach ($recordsByProject as $projectRecords) {
            foreach ($this->overlapsInProject($projectRecords) as [$empId1, $empId2, $days]) {
                $key = $empId1.':'.$empId2;
                $totals[$key] = ($totals[$key] ?? 0) + $days;
            }
        }

        $winner = null;
        $ties = 0;

        foreach ($totals as $key => $totalDays) {
            [$empId1, $empId2] = array_map(intval(...), explode(':', $key));
            $candidate = [$empId1, $empId2, $totalDays];

            if ($winner === null || $totalDays > $winner[2]) {
                [$winner, $ties] = [$candidate, 0];
            } elseif ($totalDays === $winner[2]) {
                $ties++;
                if ([$empId1, $empId2] < [$winner[0], $winner[1]]) {
                    $winner = $candidate;
                }
            }
        }

        unset($totals);

        if ($winner === null) {
            return null;
        }

        [$empId1, $empId2, $totalDays] = $winner;

        return new PairResult(
            $empId1,
            $empId2,
            $totalDays,
            $this->commonProjects($recordsByProject, $empId1, $empId2),
            $ties,
        );
    }

    /**
     * @param  iterable<EmployeeRecord>  $records
     * @return array<int, list<EmployeeRecord>>
     */
    private function groupByProject(iterable $records): array
    {
        $recordsByProject = [];

        foreach ($records as $record) {
            $recordsByProject[$record->projectId][] = $record;
        }

        ksort($recordsByProject);

        return $recordsByProject;
    }

    /**
     * @param  array<EmployeeRecord>  $records  Records of a single project.
     * @return iterable<array{int, int, int}> [empId1, empId2, days], only when days > 0.
     */
    private function overlapsInProject(array $records): iterable
    {
        $periods = [];
        foreach ($records as $record) {
            $periods[$record->empId][] = [$record->fromDay, $record->toDay];
        }

        ksort($periods);
        $periods = array_map($this->mergePeriods(...), $periods);
        $empIds = array_keys($periods);

        foreach ($empIds as $i => $empId1) {
            foreach (array_slice($empIds, $i + 1) as $empId2) {
                $days = $this->overlapDays($periods[$empId1], $periods[$empId2]);

                if ($days > 0) {
                    yield [$empId1, $empId2, $days];
                }
            }
        }
    }

    /**
     * @param  array<int, list<EmployeeRecord>>  $recordsByProject
     * @return list<ProjectOverlap>
     */
    private function commonProjects(array $recordsByProject, int $empId1, int $empId2): array
    {
        $projects = [];

        foreach ($recordsByProject as $projectId => $records) {
            $pairRecords = array_filter(
                $records,
                fn (EmployeeRecord $record): bool => $record->empId === $empId1 || $record->empId === $empId2,
            );

            foreach ($this->overlapsInProject($pairRecords) as [, , $days]) {
                $projects[] = new ProjectOverlap($empId1, $empId2, $projectId, $days);
            }
        }

        return $projects;
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
}
