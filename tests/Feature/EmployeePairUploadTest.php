<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\UploadCsvRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class EmployeePairUploadTest extends TestCase
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

    public function testTheHomePageShowsTheUploadForm(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Employee pairs')
            ->assertSee('EmpID, ProjectID, DateFrom, DateTo')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('accept=".csv,.txt', false)
            ->assertDontSee('Days worked');
    }

    public function testAValidCsvShowsThePairAndEveryCommonProject(): void
    {
        $response = $this->upload(implode("\n", [
            'EmpID, ProjectID, DateFrom, DateTo',
            '143, 12, 2013-11-01, 2014-01-05',
            '218, 12, 2013-12-01, 2014-03-31',
            '143, 10, 2012-06-01, 2012-12-31',
            '218, 10, 2012-05-16, NULL',
            '301, 10, 2012-01-01, 2012-01-31',
        ]));

        $response->assertOk()
            ->assertSee('Result for “employees.csv”', false)
            ->assertSeeInOrder(['Employees', '143', 'and', '218', 'worked together for', '250', 'days in total.'])
            ->assertSeeInOrder(['Employee ID #1', 'Employee ID #2', 'Project ID', 'Days worked'])
            ->assertSeeInOrder(['143', '218', '10', '214', '143', '218', '12', '36', 'Total', '250']);

        $pair = $response->viewData('pair');
        $this->assertSame([143, 218, 250], [$pair->empId1, $pair->empId2, $pair->totalDays]);
        $this->assertCount(2, $pair->projects);
        $this->assertSame(250, array_sum(array_column($pair->projects, 'days')));
    }

    public function testTheFormStaysVisibleAfterResults(): void
    {
        $this->upload("1,10,2020-01-01,2020-01-10\n2,10,2020-01-05,2020-01-20\n")
            ->assertOk()
            ->assertSee('name="file"', false)
            ->assertSee('Find pair');
    }

    public function testInvalidRowsAreListedAsWarnings(): void
    {
        $this->upload("1,10,2020-01-01,2020-01-10\n2,10,2020-01-05,2020-01-20\nabc,10,2020-01-01,2020-01-02\n")
            ->assertOk()
            ->assertSee('1 row skipped because of invalid data')
            ->assertSee("Line 3: invalid EmpID 'abc'")
            ->assertSee('worked together for');
    }

    public function testTheSkippedRowCountIsExactWhenWarningsAreCapped(): void
    {
        $this->upload("1,10,2020-01-01,2020-01-10\n2,10,2020-01-05,2020-01-20\n".str_repeat("x,10,2020-01-01,NULL\n", 600))
            ->assertOk()
            ->assertSee('600 rows skipped because of invalid data')
            ->assertSee('...and 100 more invalid rows.');
    }

    public function testNoOverlapShowsAFriendlyMessage(): void
    {
        $this->upload("1,10,2020-01-01,2020-01-10\n2,10,2020-02-01,2020-02-10\n")
            ->assertOk()
            ->assertSee('No pair of employees worked together on a common project')
            ->assertDontSee('Days worked');
    }

    public function testATieIsMentioned(): void
    {
        $this->upload("3,10,2020-01-01,2020-01-10\n4,10,2020-01-01,2020-01-10\n1,20,2020-01-01,2020-01-10\n2,20,2020-01-01,2020-01-10\n")
            ->assertOk()
            ->assertSeeInOrder(['Employees', '1', 'and', '2'])
            ->assertSeeInOrder(['1 other pair', 'also worked together for 10 days', 'lowest employee IDs']);
    }

    public function testTheDefaultDateOrderIsMentionedWhenTheFileGivesNoEvidence(): void
    {
        $this->upload("1,10,01/02/2014,03/04/2014\n2,10,02/02/2014,03/04/2014\n")
            ->assertOk()
            ->assertSee('Ambiguous dates like 01/02/2014 were read as day-first (the default; the file gives no evidence).');
    }

    public function testUploadingWithoutAFileFailsValidation(): void
    {
        $this->from('/')->post('/')
            ->assertRedirect('/')
            ->assertSessionHasErrors(['file' => 'Please choose a CSV file to upload.']);
    }

    public function testAWrongFileTypeFailsValidation(): void
    {
        $this->from('/')->post('/', ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertRedirect('/')
            ->assertSessionHasErrors('file');
    }

    public function testAnEmptyFileFailsValidation(): void
    {
        $this->from('/')->post('/', ['file' => UploadedFile::fake()->createWithContent('empty.csv', '')])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['file' => 'The selected file is empty.']);
    }

    public function testATooLargeFileFailsValidation(): void
    {
        $file = UploadedFile::fake()->create('huge.csv', UploadCsvRequest::MAX_KILOBYTES + 1, 'text/csv');

        $this->from('/')->post('/', ['file' => $file])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['file' => 'The file may not be larger than 20 MB.']);
    }

    public function testValidationErrorsAreShownOnThePage(): void
    {
        $this->followingRedirects()->from('/')->post('/')
            ->assertOk()
            ->assertSee('Please choose a CSV file to upload.');
    }

    public function testAFileWithOnlyAHeaderShowsAnError(): void
    {
        $this->upload("EmpID,ProjectID,DateFrom,DateTo\n")
            ->assertUnprocessable()
            ->assertSee('The file is empty or contains only a header row.');
    }

    public function testAFileWithoutValidRowsShowsTheErrorAndWarnings(): void
    {
        $this->upload("1,10,not a date,NULL\n2,10\n")
            ->assertUnprocessable()
            ->assertSee('The file contains no valid rows.')
            ->assertSee("Line 1: invalid date 'not a date'")
            ->assertSee('Line 2: expected 4 columns, found 2');
    }

    public function testARequestAboveThePhpPostLimitRedirectsWithAMessage(): void
    {
        $this->get('/?too_large=1')
            ->assertOk()
            ->assertSee('The file is too large.');
    }

    private function upload(string $contents, string $name = 'employees.csv'): TestResponse
    {
        return $this->post('/', ['file' => UploadedFile::fake()->createWithContent($name, $contents)]);
    }
}
