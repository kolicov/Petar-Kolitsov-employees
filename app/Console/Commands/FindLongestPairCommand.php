<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTO\PairResult;
use App\DTO\ProjectOverlap;
use App\Exceptions\CsvImportException;
use App\Services\CsvEmployeeReader;
use App\Services\EmployeePairFinder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Prints the winning pair as "EmpID1, EmpID2, TotalDaysTogether".
 *
 * Only the result (and the optional --details table) goes to stdout;
 * warnings and errors go to stderr, so the output can be piped safely.
 */
final class FindLongestPairCommand extends Command
{
    protected $signature = 'employees:longest-pair
        {file : Path to the CSV file (EmpID, ProjectID, DateFrom, DateTo)}
        {--details : Also list every common project of the pair}';

    protected $description = 'Find the pair of employees who worked together on common projects the longest';

    public function handle(CsvEmployeeReader $reader, EmployeePairFinder $finder): int
    {
        try {
            $csv = $reader->read((string) $this->argument('file'));
        } catch (CsvImportException $e) {
            $this->printWarnings($e->warnings, $e->skippedRows);
            $this->toStderr(sprintf('<error>Error: %s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $this->printWarnings($csv->warnings, $csv->skippedRows);

        if (($dateOrderNote = $csv->dateOrder?->summary()) !== null) {
            $this->toStderr(sprintf('<comment>%s</comment>', $dateOrderNote));
        }

        $pair = $finder->find($csv->records);

        if ($pair === null) {
            $this->line('No pair of employees worked together on a common project.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d, %d, %d', $pair->empId1, $pair->empId2, $pair->totalDays));

        if ($pair->otherPairsWithSameTotal > 0) {
            $this->toStderr(sprintf(
                '<comment>Note: %d other pair(s) also have %d days; the pair with the lowest IDs is shown.</comment>',
                $pair->otherPairsWithSameTotal,
                $pair->totalDays,
            ));
        }

        if ($this->option('details')) {
            $this->printDetails($pair);
        }

        return self::SUCCESS;
    }

    private function printDetails(PairResult $pair): void
    {
        $rows = array_map(
            fn (ProjectOverlap $overlap): array => [$overlap->empId1, $overlap->empId2, $overlap->projectId, $overlap->days],
            $pair->projects,
        );

        $this->newLine();
        $this->table(
            ['Employee ID #1', 'Employee ID #2', 'Project ID', 'Days worked'],
            [...$rows, new TableSeparator, ['Total', '', '', $pair->totalDays]],
        );
    }

    /**
     * @param  list<string>  $warnings
     */
    private function printWarnings(array $warnings, int $skippedRows): void
    {
        if ($warnings === []) {
            return;
        }

        $this->toStderr(sprintf('<comment>Skipped %d invalid %s:</comment>', $skippedRows, $skippedRows === 1 ? 'row' : 'rows'));

        foreach ($warnings as $warning) {
            $this->toStderr(sprintf('<comment>  - %s</comment>', $warning));
        }
    }

    private function toStderr(string $line): void
    {
        $this->getOutput()->getErrorStyle()->writeln($line);
    }
}
