<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CsvReadResult;
use App\DTO\DateOrderDetection;
use App\DTO\EmployeeRecord;
use App\Enums\DateOrder;
use App\Enums\DateOrderEvidence;
use App\Enums\DateOrderReason;
use App\Exceptions\CsvImportException;
use App\Exceptions\InvalidDateException;
use App\Exceptions\InvalidRowException;

final readonly class CsvEmployeeReader
{
    private const array DELIMITERS = [',', ';', "\t"];

    private const int COLUMNS = 4;

    private const int MAX_WARNINGS = 500;

    /** Distinct date values remembered while reading one file; dates repeat a lot. */
    private const int MAX_CACHED_VALUES = 5000;

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    private const array UTF16_BOMS = ["\xFF\xFE" => 'UTF-16LE', "\xFE\xFF" => 'UTF-16BE'];

    public function __construct(
        private DateParser $dateParser,
    ) {}

    /**
     * @throws CsvImportException When the file is unreadable, empty or has no valid rows.
     */
    public function read(string $path): CsvReadResult
    {
        $handle = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;

        if ($handle === false) {
            throw new CsvImportException(sprintf("The file '%s' does not exist or cannot be read.", $path));
        }

        try {
            $this->convertUtf16ToUtf8($handle);

            return $this->readRows($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function readRows($handle): CsvReadResult
    {
        $delimiter = $this->detectDelimiter($handle);
        $dateOrder = $this->detectDateOrder($handle, $delimiter);
        $dateParser = $this->dateParser->withAmbiguousOrder($dateOrder->order);

        $records = [];
        $warnings = [];
        $skipped = 0;
        $dataRows = 0;
        $isFirstRow = true;

        foreach ($this->rows($handle, $delimiter) as $lineNumber => $row) {
            if ($isFirstRow) {
                $isFirstRow = false;

                if ($this->isHeader($row, $dateParser)) {
                    continue;
                }
            }

            $dataRows++;

            try {
                $records[] = $this->toRecord($row, $dateParser);
            } catch (InvalidRowException $e) {
                $skipped++;

                if (count($warnings) < self::MAX_WARNINGS) {
                    $warnings[] = sprintf('Line %d: %s', $lineNumber, $e->getMessage());
                }
            }
        }

        if ($skipped > self::MAX_WARNINGS) {
            $warnings[] = sprintf('...and %d more invalid rows.', $skipped - self::MAX_WARNINGS);
        }

        if ($dataRows === 0) {
            throw new CsvImportException('The file is empty or contains only a header row.');
        }

        if ($records === []) {
            throw new CsvImportException('The file contains no valid rows.', $warnings, $skipped);
        }

        return new CsvReadResult($records, $warnings, $skipped, $dateOrder);
    }

    /**
     * Non-blank rows, trimmed, keyed by their line number. A quoted value may
     * span several lines, so physical lines are counted.
     *
     * @param  resource  $handle
     * @return iterable<int, list<string>>
     */
    private function rows($handle, string $delimiter): iterable
    {
        rewind($handle);

        $nextLineNumber = 1;

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $lineNumber = $nextLineNumber;
            $nextLineNumber += 1 + substr_count(implode('', $row), "\n");
            $row = $this->clean($row, $lineNumber === 1);

            if ($row !== []) {
                yield $lineNumber => $row;
            }
        }
    }

    /**
     * A cheap first pass over the dates of all valid-looking rows: if the file
     * only contains dates that prove one day/month order (25/02/2013 or
     * 02/25/2013), its ambiguous dates are read that way; otherwise the
     * configured default is used.
     *
     * @param  resource  $handle
     */
    private function detectDateOrder($handle, string $delimiter): DateOrderDetection
    {
        $proofs = [];
        $ambiguousExample = null;
        $evidenceCache = [];

        foreach ($this->rows($handle, $delimiter) as $lineNumber => $row) {
            if (count($row) !== self::COLUMNS || ! $this->isId($row[0]) || ! $this->isId($row[1])) {
                continue;
            }

            foreach ([$row[2], $row[3]] as $value) {
                if (count($evidenceCache) >= self::MAX_CACHED_VALUES) {
                    $evidenceCache = [];
                }

                $evidence = $evidenceCache[$value] ??= $this->dateParser->dateOrderEvidence($value);

                if ($evidence === DateOrderEvidence::Ambiguous) {
                    $ambiguousExample ??= $value;
                } elseif ($evidence !== DateOrderEvidence::None) {
                    $proofs[$evidence->name] ??= [$lineNumber, $value];
                }
            }

            if ($ambiguousExample !== null && count($proofs) === 2) {
                break; // Conflicting evidence: reading further changes nothing.
            }
        }

        rewind($handle);

        $default = $this->dateParser->ambiguousOrder;

        if (count($proofs) !== 1) {
            $reason = $proofs === [] ? DateOrderReason::Default : DateOrderReason::Conflicting;

            return new DateOrderDetection($default, $reason, $ambiguousExample);
        }

        $order = isset($proofs[DateOrderEvidence::DayFirst->name]) ? DateOrder::DayFirst : DateOrder::MonthFirst;
        [$lineNumber, $value] = reset($proofs);

        return new DateOrderDetection($order, DateOrderReason::Detected, $ambiguousExample, $lineNumber, $value);
    }

    /**
     * @param  resource  $handle
     */
    private function convertUtf16ToUtf8($handle): void
    {
        $encoding = self::UTF16_BOMS[fread($handle, 2)] ?? null;
        rewind($handle);

        if ($encoding !== null) {
            stream_filter_append($handle, sprintf('convert.iconv.%s/UTF-8', $encoding), STREAM_FILTER_READ);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $line = '';
        while ($line === '' && ($next = fgets($handle)) !== false) {
            $line = trim($next);
        }
        rewind($handle);

        $line = preg_replace('/"[^"]*"/', '', $line);

        $counts = array_map(fn (string $delimiter): int => substr_count($line, $delimiter), self::DELIMITERS);

        return max($counts) > 0 ? self::DELIMITERS[array_search(max($counts), $counts, true)] : ',';
    }

    /**
     * @param  array<int, string|null>  $row
     * @return list<string>
     */
    private function clean(array $row, bool $isFirstRow): array
    {
        if ($isFirstRow && isset($row[0]) && str_starts_with($row[0], self::UTF8_BOM)) {
            $row[0] = substr($row[0], strlen(self::UTF8_BOM));
        }

        $row = array_map(fn (?string $value): string => trim((string) $value), $row);

        if (implode('', $row) === '') {
            return [];
        }

        while (count($row) > self::COLUMNS && end($row) === '') {
            array_pop($row);
        }

        return $row;
    }

    /**
     * @param  list<string>  $row
     *
     * @throws InvalidRowException
     */
    private function toRecord(array $row, DateParser $dateParser): EmployeeRecord
    {
        if (count($row) !== self::COLUMNS) {
            throw new InvalidRowException(sprintf('expected %d columns, found %d', self::COLUMNS, count($row)));
        }

        [$empId, $projectId, $dateFrom, $dateTo] = $row;

        if (! $this->isId($empId)) {
            throw new InvalidRowException(sprintf("invalid EmpID '%s'", $empId));
        }

        if (! $this->isId($projectId)) {
            throw new InvalidRowException(sprintf("invalid ProjectID '%s'", $projectId));
        }

        if ($dateParser->isNull($dateFrom)) {
            throw new InvalidRowException('DateFrom is missing (only DateTo may be NULL)');
        }

        try {
            $from = $dateParser->parse($dateFrom);
            $to = $dateParser->parseOrToday($dateTo);
        } catch (InvalidDateException $e) {
            throw new InvalidRowException($e->getMessage());
        }

        if ($from > $to) {
            throw new InvalidRowException(sprintf(
                'DateFrom %s is after DateTo %s',
                $from->toDateString(),
                $to->toDateString(),
            ));
        }

        return EmployeeRecord::fromDates((int) $empId, (int) $projectId, $from, $to);
    }

    /**
     * @param  list<string>  $row
     */
    private function isHeader(array $row, DateParser $dateParser): bool
    {
        [$empId, $projectId, $dateFrom] = array_pad($row, 3, '');

        return ! $this->isId($empId) && ! $this->isId($projectId) && ! $this->isDate($dateFrom, $dateParser);
    }

    private function isDate(string $value, DateParser $dateParser): bool
    {
        try {
            $dateParser->parse($value);

            return true;
        } catch (InvalidDateException) {
            return false;
        }
    }

    private function isId(string $value): bool
    {
        return preg_match('/^\d{1,18}$/', $value) === 1;
    }
}
