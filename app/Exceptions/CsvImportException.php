<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The CSV file cannot be used at all (unreadable, empty or without valid rows).
 */
final class CsvImportException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        string $message,
        public readonly array $warnings = [],
        public readonly int $skippedRows = 0,
    ) {
        parent::__construct($message);
    }
}
