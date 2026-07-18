<?php

namespace Tests\Unit\Support;

use App\Support\PortugueseNationalHolidays;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortugueseNationalHolidaysTest extends TestCase
{
    public function test_fixed_and_movable_holidays_for_2026(): void
    {
        $dates = PortugueseNationalHolidays::datesForYear(2026);

        $this->assertContains('2026-01-01', $dates);
        $this->assertContains('2026-04-25', $dates);
        $this->assertContains('2026-05-01', $dates);
        $this->assertContains('2026-06-10', $dates);
        $this->assertContains('2026-08-15', $dates);
        $this->assertContains('2026-10-05', $dates);
        $this->assertContains('2026-11-01', $dates);
        $this->assertContains('2026-12-01', $dates);
        $this->assertContains('2026-12-08', $dates);
        $this->assertContains('2026-12-25', $dates);
        // Páscoa 2026-04-05 → Sexta-Feira Santa / Corpo de Deus
        $this->assertContains('2026-04-03', $dates);
        $this->assertContains('2026-06-04', $dates);
        $this->assertCount(12, $dates);
    }

    #[DataProvider('easterSundays')]
    public function test_easter_sunday(int $year, string $expected): void
    {
        $this->assertSame($expected, PortugueseNationalHolidays::easterSunday($year)->toDateString());
    }

    public function test_is_holiday(): void
    {
        $this->assertTrue(PortugueseNationalHolidays::isHoliday('2026-12-25'));
        $this->assertTrue(PortugueseNationalHolidays::isHoliday('2026-04-03'));
        $this->assertFalse(PortugueseNationalHolidays::isHoliday('2026-04-04'));
        $this->assertFalse(PortugueseNationalHolidays::isHoliday('2026-04-05'));
    }

    public function test_name_for_holiday(): void
    {
        $this->assertSame('Natal', PortugueseNationalHolidays::nameFor('2026-12-25'));
        $this->assertSame('Sexta-Feira Santa', PortugueseNationalHolidays::nameFor('2026-04-03'));
        $this->assertNull(PortugueseNationalHolidays::nameFor('2026-04-04'));
    }

    /** @return list<array{0: int, 1: string}> */
    public static function easterSundays(): array
    {
        return [
            [2024, '2024-03-31'],
            [2025, '2025-04-20'],
            [2026, '2026-04-05'],
        ];
    }
}
