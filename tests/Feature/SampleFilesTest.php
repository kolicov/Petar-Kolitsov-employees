<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\EmployeeRecord;
use App\Services\CsvEmployeeReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * The files in samples/ with their hand-calculated results (see README.md).
 */
final class SampleFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow('2024-06-15');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[TestWith(['sample.csv'])]
    #[TestWith(['mixed-date-formats.csv'])]
    public function testSampleFilesFindTheSameWinningPair(string $file): void
    {
        $this->artisan('employees:longest-pair', ['file' => base_path(sprintf('samples/%s', $file))])
            ->expectsOutput('143, 218, 372')
            ->assertExitCode(Command::SUCCESS);

        $pair = $this->upload($file)->assertOk()->viewData('pair');

        $this->assertSame([143, 218, 372], [$pair->empId1, $pair->empId2, $pair->totalDays]);
        $this->assertSame(
            [[10, 214], [12, 36], [15, 122]],
            array_map(fn ($p): array => [$p->projectId, $p->days], $pair->projects),
        );
    }

    public function testMixedDateFormatsParseToTheSameRecordsAsTheIsoSample(): void
    {
        $reader = $this->app->make(CsvEmployeeReader::class);
        $asRows = fn (array $records): array => array_map(fn (EmployeeRecord $r): string => sprintf(
            '%d,%d,%s,%s', $r->empId, $r->projectId, $r->dateFrom->format('Y-m-d'), $r->dateTo->format('Y-m-d'),
        ), $records);

        $iso = $asRows($reader->read(base_path('samples/sample.csv'))->records);
        $mixed = $reader->read(base_path('samples/mixed-date-formats.csv'));

        $this->assertSame([], $mixed->warnings);
        $this->assertSame([...$iso, '501,30,2020-03-01,2024-06-15', '502,31,2021-06-01,2024-06-15'], $asRows($mixed->records));
    }

    public function testTheFileWithErrorsSkipsInvalidRows(): void
    {
        $warnings = [
            "Line 4: invalid EmpID 'abc'",
            'Line 5: expected 4 columns, found 3',
            "Line 7: invalid date 'not-a-date'",
            "Line 8: invalid date '2020-02-30'",
            'Line 9: DateFrom 2020-05-01 is after DateTo 2020-04-01',
            "Line 10: invalid ProjectID 'x12'",
            'Line 11: DateFrom is missing (only DateTo may be NULL)',
        ];

        $command = $this->artisan('employees:longest-pair', ['file' => base_path('samples/with-errors.csv')])
            ->expectsOutput('1, 2, 17');
        foreach ($warnings as $warning) {
            $command->expectsOutputToContain($warning);
        }
        $command->assertExitCode(Command::SUCCESS);

        $response = $this->upload('with-errors.csv')->assertOk();
        $this->assertSame($warnings, $response->viewData('warnings'));
        $this->assertSame(17, $response->viewData('pair')->totalDays);
    }

    public function testTheNoOverlapFileHasNoPair(): void
    {
        $this->artisan('employees:longest-pair', ['file' => base_path('samples/no-overlap.csv')])
            ->expectsOutput('No pair of employees worked together on a common project.')
            ->assertExitCode(Command::SUCCESS);

        $this->upload('no-overlap.csv')
            ->assertOk()
            ->assertSee('No pair of employees worked together on a common project');
    }

    private function upload(string $file): TestResponse
    {
        $path = base_path(sprintf('samples/%s', $file));

        return $this->post('/', ['file' => new UploadedFile($path, $file, 'text/csv', null, true)]);
    }
}
