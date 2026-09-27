<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\EmployeeRecord;
use App\DTO\PairResult;
use App\DTO\ProjectOverlap;
use App\Services\EmployeePairFinder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmployeePairFinderTest extends TestCase
{
    public function testItCountsTheOverlapOfTwoEmployees(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-31'],
            [2, 10, '2020-01-15', '2020-02-15'],
        );

        $this->assertPair(1, 2, 17, $result); // Jan 15..31
    }

    public function testItReturnsNullWhenNobodyOverlaps(): void
    {
        $this->assertNull($this->find(
            [1, 10, '2020-01-01', '2020-01-31'],
            [2, 10, '2020-02-01', '2020-02-28'],
            [3, 20, '2020-01-01', '2020-12-31'],
        ));
    }

    public function testItReturnsNullWithoutRecords(): void
    {
        $this->assertNull((new EmployeePairFinder)->find([]));
    }

    public function testTouchingRangesCountAsOneDay(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-10'],
            [2, 10, '2020-01-10', '2020-01-20'],
        );

        $this->assertTrue(EmployeePairFinder::COUNT_DAYS_INCLUSIVE);
        $this->assertPair(1, 2, 1, $result);
    }

    public function testARangeFullyInsideAnother(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-12-31'],
            [2, 10, '2020-03-01', '2020-03-31'],
        );

        $this->assertPair(1, 2, 31, $result);
    }

    public function testAnOpenEndDateCountsUntilToday(): void
    {
        // The reader turns DateTo = NULL into today; here "today" is 2020-01-31.
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-31'],
            [2, 10, '2020-01-22', '2020-01-31'],
        );

        $this->assertPair(1, 2, 10, $result);
    }

    public function testDaysAreSummedAcrossProjects(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-10'],
            [2, 10, '2020-01-01', '2020-01-10'],
            [1, 20, '2021-05-01', '2021-05-05'],
            [2, 20, '2021-05-03', '2021-05-31'],
        );

        $this->assertPair(1, 2, 13, $result);
        $this->assertEquals([
            new ProjectOverlap(1, 2, 10, 10),
            new ProjectOverlap(1, 2, 20, 3),
        ], $result->projects);
    }

    public function testALongerTotalBeatsASingleLongerProject(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-20'],  // 1 & 2: 20 days on one project
            [2, 10, '2020-01-01', '2020-01-20'],
            [3, 20, '2020-01-01', '2020-01-15'],  // 3 & 4: 15 + 15 = 30 days
            [4, 20, '2020-01-01', '2020-01-15'],
            [3, 30, '2020-02-01', '2020-02-15'],
            [4, 30, '2020-02-01', '2020-02-15'],
        );

        $this->assertPair(3, 4, 30, $result);
    }

    public function testRepeatedPeriodsOfOneEmployeeAreNotDoubleCounted(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-31'],
            [2, 10, '2020-01-01', '2020-01-20'],
            [2, 10, '2020-01-10', '2020-01-31'],  // overlaps the previous period of employee 2
            [2, 10, '2020-01-05', '2020-01-06'],  // fully inside it
            [2, 10, '2020-01-01', '2020-01-31'],  // exact duplicate
        );

        $this->assertPair(1, 2, 31, $result);
    }

    public function testAnEmployeeWhoLeftAndCameBackCountsBothPeriods(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-12-31'],
            [2, 10, '2020-01-01', '2020-01-10'],
            [2, 10, '2020-06-01', '2020-06-10'],
        );

        $this->assertPair(1, 2, 20, $result);
    }

    public function testTheRightPairIsChosenAmongManyEmployeesOnAProject(): void
    {
        $result = $this->find(
            [1, 10, '2020-01-01', '2020-01-10'],
            [2, 10, '2020-01-05', '2020-03-31'],
            [3, 10, '2020-02-01', '2020-04-30'],
            [4, 10, '2020-01-01', '2020-01-02'],
        );

        // 1&2: 6, 1&4: 2, 2&3: 60 (Feb 1..Mar 31), 2&4: 0, 3&4: 0
        $this->assertPair(2, 3, 60, $result);
    }

    public function testAnEmployeeIsNeverPairedWithThemselves(): void
    {
        $this->assertNull($this->find(
            [1, 10, '2020-01-01', '2020-01-31'],
            [1, 10, '2020-01-15', '2020-02-15'],
        ));
    }

    public function testTiesGoToTheLowestIds(): void
    {
        $records = [
            [300, 10, '2020-01-01', '2020-01-10'],
            [400, 10, '2020-01-01', '2020-01-10'],
            [99, 20, '2020-01-01', '2020-01-10'],
            [500, 20, '2020-01-01', '2020-01-10'],
            [99, 30, '2020-01-01', '2020-01-10'],
            [200, 30, '2020-01-01', '2020-01-10'],
        ];

        $result = $this->find(...$records);
        $reversed = $this->find(...array_reverse($records));

        $this->assertPair(99, 200, 10, $result);
        $this->assertSame(2, $result->otherPairsWithSameTotal);
        $this->assertEquals($result, $reversed, 'the input order must not matter');
    }

    public function testTheSmallerIdIsAlwaysFirst(): void
    {
        $result = $this->find(
            [218, 10, '2020-01-01', '2020-01-10'],
            [143, 10, '2020-01-01', '2020-01-10'],
        );

        $this->assertPair(143, 218, 10, $result);
        $this->assertSame(143, $result->projects[0]->empId1);
        $this->assertSame(218, $result->projects[0]->empId2);
    }

    public function testProjectRowsAddUpToTheTotal(): void
    {
        $result = $this->find(
            [1, 30, '2019-06-01', '2019-06-30'],
            [2, 30, '2019-06-10', '2019-07-15'],
            [1, 10, '2020-01-01', '2020-03-01'],
            [2, 10, '2020-02-01', '2020-02-29'],
            [1, 20, '2018-01-01', '2018-01-01'],
            [2, 20, '2018-01-01', '2018-01-01'],
            [3, 10, '2020-02-01', '2020-02-02'],
        );

        $this->assertPair(1, 2, 51, $result); // 29 (leap Feb) + 1 + 21
        $this->assertSame([10, 20, 30], array_map(fn (ProjectOverlap $p): int => $p->projectId, $result->projects));
        $this->assertSame($result->totalDays, array_sum(array_map(fn (ProjectOverlap $p): int => $p->days, $result->projects)));
    }

    public function testTheTimeOfDayIsIgnored(): void
    {
        $result = (new EmployeePairFinder)->find([
            new EmployeeRecord(1, 10, new DateTimeImmutable('2020-01-01 23:00'), new DateTimeImmutable('2020-01-02 01:00')),
            new EmployeeRecord(2, 10, new DateTimeImmutable('2020-01-02 22:00'), new DateTimeImmutable('2020-01-05 00:00')),
        ]);

        $this->assertPair(1, 2, 1, $result);
    }

    /**
     * @param  array{int, int, string, string}  ...$rows  [empId, projectId, dateFrom, dateTo]
     */
    private function find(array ...$rows): ?PairResult
    {
        $records = array_map(
            fn (array $row): EmployeeRecord => new EmployeeRecord(
                $row[0],
                $row[1],
                new DateTimeImmutable($row[2]),
                new DateTimeImmutable($row[3]),
            ),
            $rows,
        );

        return (new EmployeePairFinder)->find($records);
    }

    private function assertPair(int $empId1, int $empId2, int $totalDays, ?PairResult $result): void
    {
        $this->assertNotNull($result, 'expected a pair, got none');
        $this->assertSame(
            [$empId1, $empId2, $totalDays],
            [$result->empId1, $result->empId2, $result->totalDays],
        );
    }
}
