<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CsvReadResult;
use App\DTO\EmployeeRecord;
use App\Exceptions\CsvImportException;
use App\Exceptions\InvalidDateException;
use App\Exceptions\InvalidRowException;

final readonly class CsvEmployeeReader
{
    private const array DELIMITERS = [',', ';', "\t"];

    private const int COLUMNS = 4;

    private const int MAX_WARNINGS = 500;

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

        $records = [];
        $warnings = [];
        $skipped = 0;
        $lineNumber = 0;
        $nextLineNumber = 1;
        $dataRows = 0;
        $isFirstRow = true;

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $lineNumber = $nextLineNumber;
            $nextLineNumber += 1 + substr_count(implode('', $row), "\n");
            $row = $this->clean($row, $isFirstRow);

            if ($row === []) {
                continue;
            }

            if ($isFirstRow) {
                $isFirstRow = false;

                if (! $this->isId($row[0])) {
                    continue;
                }
            }

            $dataRows++;

            try {
                $records[] = $this->toRecord($row);
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

        return new CsvReadResult($records, $warnings, $skipped);
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
    private function toRecord(array $row): EmployeeRecord
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

        if ($this->dateParser->isNull($dateFrom)) {
            throw new InvalidRowException('DateFrom is missing (only DateTo may be NULL)');
        }

        try {
            $from = $this->dateParser->parse($dateFrom);
            $to = $this->dateParser->parseOrToday($dateTo);
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

        return new EmployeeRecord((int) $empId, (int) $projectId, $from, $to);
    }

    private function isId(string $value): bool
    {
        return preg_match('/^\d{1,18}$/', $value) === 1;
    }
}
