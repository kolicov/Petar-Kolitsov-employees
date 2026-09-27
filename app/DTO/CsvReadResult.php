<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Valid records read from a CSV file, plus warnings about the skipped rows.
 */
final readonly class CsvReadResult
{
    /**
     * @param  list<EmployeeRecord>  $records
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $records,
        public array $warnings,
        public int $skippedRows = 0,
    ) {}
}
