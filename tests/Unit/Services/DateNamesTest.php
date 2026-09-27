<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DateNames;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DateNamesTest extends TestCase
{
    #[TestWith(['bg', 'ноември'])]
    #[TestWith(['bg', 'ное'])]
    #[TestWith(['ru', 'ноября'])]
    #[TestWith(['ru', 'ноябрь'])]
    #[TestWith(['pl', 'listopada'])]
    #[TestWith(['pl', 'listopad'])]
    #[TestWith(['es', 'noviembre'])]
    #[TestWith(['es', 'nov'])]
    #[TestWith(['de', 'november'])]
    #[TestWith(['fr', 'novembre'])]
    #[TestWith(['en', 'nov'])]
    public function testMonthNamesIncludeFullShortAndGenitiveForms(string $locale, string $word): void
    {
        $this->assertSame(11, (new DateNames([$locale]))->month($word));
    }

    public function testNamesAreLowercaseWithoutTheTrailingDot(): void
    {
        $names = new DateNames(['fr']);

        $this->assertSame(1, $names->month('janv'));
        $this->assertSame('janv', DateNames::normalise(' Janv. '));
    }

    public function testAWordMeaningDifferentMonthsInTwoLanguagesIsDropped(): void
    {
        // "listopad" is November in Polish but October in Croatian.
        $this->assertSame(11, (new DateNames(['pl']))->month('listopad'));
        $this->assertSame(10, (new DateNames(['hr']))->month('listopad'));
        $this->assertNull((new DateNames(['pl', 'hr']))->month('listopad'));
        $this->assertNull((new DateNames(['pl', 'hr']))->month('listopada'));
    }

    public function testTheSameMonthInTwoLanguagesIsKept(): void
    {
        $this->assertSame(5, (new DateNames(['de', 'fr', 'pt', 'ro']))->month('mai'));
    }

    public function testAWordThatIsAMonthIsNotAWeekday(): void
    {
        // "mar." is Tuesday in Spanish and French, but "Mar" is March in English.
        $names = new DateNames(['en', 'es', 'fr']);

        $this->assertSame(3, $names->month('mar'));
        $this->assertNull($names->weekday('mar'));
    }

    #[TestWith(['en', 'friday'])]
    #[TestWith(['en', 'fri'])]
    #[TestWith(['bg', 'петък'])]
    #[TestWith(['ru', 'пятница'])]
    #[TestWith(['es', 'viernes'])]
    #[TestWith(['pt', 'sexta-feira'])]
    public function testWeekdayNames(string $locale, string $word): void
    {
        $this->assertSame(5, (new DateNames([$locale]))->weekday($word));
    }

    public function testUnknownWordsAreNotNames(): void
    {
        $names = new DateNames(['en', 'bg']);

        $this->assertNull($names->month('foo'));
        $this->assertNull($names->weekday('foo'));
    }
}
