<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * The pair of employees who worked together the longest (empId1 < empId2).
 */
final readonly class PairResult
{
    /**
     * @param  list<ProjectOverlap>  $projects
     */
    public function __construct(
        public int $empId1,
        public int $empId2,
        public int $totalDays,
        public array $projects,
        public int $otherPairsWithSameTotal = 0,
    ) {}
}
