<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Days two employees spent together on one project (empId1 < empId2).
 */
final readonly class ProjectOverlap
{
    public function __construct(
        public int $empId1,
        public int $empId2,
        public int $projectId,
        public int $days,
    ) {}
}
