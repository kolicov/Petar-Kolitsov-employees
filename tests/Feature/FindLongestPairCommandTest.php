<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Tests\TestCase;

final class FindLongestPairCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2024-06-15');
        $this->path = tempnam(sys_get_temp_dir(), 'csv');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function testItPrintsThePairInTheRequiredFormat(): void
    {
        $this->csv(
            'EmpID, ProjectID, DateFrom, DateTo',
            '218, 12, 2013-12-01, 2014-03-31',
            '143, 12, 2013-11-01, 2014-01-05',
            '143, 10, 2012-06-01, 2012-12-31',
            '218, 10, 2012-05-16, NULL',
        );

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutput('143, 218, 250')
            ->doesntExpectOutputToContain('Project ID')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testDetailsListsEveryCommonProject(): void
    {
        $this->csv(
            '143, 12, 2013-11-01, 2014-01-05',
            '218, 12, 2013-12-01, 2014-03-31',
            '143, 10, 2012-06-01, 2012-12-31',
            '218, 10, 2012-05-16, NULL',
        );

        $this->artisan('employees:longest-pair', ['file' => $this->path, '--details' => true])
            ->expectsOutput('143, 218, 250')
            ->expectsTable(
                ['Employee ID #1', 'Employee ID #2', 'Project ID', 'Days worked'],
                [
                    [143, 218, 10, 214],
                    [143, 218, 12, 36],
                    new TableSeparator,
                    ['Total', '', '', 250],
                ],
            )
            ->assertExitCode(Command::SUCCESS);
    }

    public function testInvalidRowsAreReportedWithoutBreakingTheResult(): void
    {
        $this->csv(
            '1, 10, 2020-01-01, 2020-01-31',
            '2, 10, 2020-01-15, 2020-02-15',
            'abc, 10, 2020-01-01, 2020-01-31',
        );

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutput('1, 2, 17')
            ->expectsOutputToContain("Line 3: invalid EmpID 'abc'")
            ->assertExitCode(Command::SUCCESS);
    }

    public function testNoOverlapPrintsAMessage(): void
    {
        $this->csv(
            '1, 10, 2020-01-01, 2020-01-31',
            '2, 10, 2020-02-01, 2020-02-28',
        );

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutput('No pair of employees worked together on a common project.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testTheDateOrderNoteIsPrinted(): void
    {
        $this->csv(
            '1, 10, 01/02/2014, 03/04/2014',
            '2, 10, 02/02/2014, 03/04/2014',
        );

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutput('Ambiguous dates like 01/02/2014 were read as day-first (the default; the file gives no evidence).')
            ->expectsOutput('1, 2, 61')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testAMissingFileFails(): void
    {
        $this->artisan('employees:longest-pair', ['file' => '/nonexistent/file.csv'])
            ->expectsOutputToContain('does not exist or cannot be read')
            ->assertExitCode(Command::FAILURE);
    }

    public function testAnEmptyFileFails(): void
    {
        $this->csv();

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutputToContain('The file is empty')
            ->assertExitCode(Command::FAILURE);
    }

    public function testAFileWithoutValidRowsFails(): void
    {
        $this->csv('1, 10, yesterday, NULL');

        $this->artisan('employees:longest-pair', ['file' => $this->path])
            ->expectsOutputToContain("Line 1: invalid date 'yesterday'")
            ->expectsOutputToContain('The file contains no valid rows.')
            ->assertExitCode(Command::FAILURE);
    }

    private function csv(string ...$lines): void
    {
        file_put_contents($this->path, implode("\n", $lines));
    }
}
