<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One validated CSV row: an employee's period of work on a project.
 */
final readonly class EmployeeRecord
{
    public function __construct(
        public int $empId,
        public int $projectId,
        public DateTimeImmutable $dateFrom,
        public DateTimeImmutable $dateTo,
    ) {
        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException('DateFrom must not be after DateTo.');
        }
    }
}
