<?php

declare(strict_types=1);

namespace App\DTO;

use App\Support\DayNumber;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * One validated CSV row: an employee's period of work on a project.
 * Dates are kept as day numbers (see DayNumber) to keep large files cheap in memory.
 */
final readonly class EmployeeRecord
{
    public function __construct(
        public int $empId,
        public int $projectId,
        public int $fromDay,
        public int $toDay,
    ) {
        if ($fromDay > $toDay) {
            throw new InvalidArgumentException('DateFrom must not be after DateTo.');
        }
    }

    public static function fromDates(int $empId, int $projectId, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): self
    {
        return new self($empId, $projectId, DayNumber::fromDate($dateFrom), DayNumber::fromDate($dateTo));
    }

    public function dateFrom(): DateTimeImmutable
    {
        return DayNumber::toDate($this->fromDay);
    }

    public function dateTo(): DateTimeImmutable
    {
        return DayNumber::toDate($this->toDay);
    }
}
