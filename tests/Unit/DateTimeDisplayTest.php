<?php

namespace Tests\Unit;

use App\Support\DateTimeDisplay;
use Carbon\Carbon;
use Tests\TestCase;

class DateTimeDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'UTC',
            'booking.business_timezone' => 'Europe/Lisbon',
        ]);
    }

    public function test_marcacao_converts_utc_instant_to_business_timezone(): void
    {
        $formatted = DateTimeDisplay::marcacao(
            Carbon::parse('2026-06-18 09:00:00', 'UTC'),
            null,
            'H:i',
        );

        $this->assertSame('10:00', $formatted);
    }

    public function test_format_instant_converts_log_timestamps(): void
    {
        $formatted = DateTimeDisplay::formatInstant(
            Carbon::parse('2026-06-18 22:45:25', 'UTC'),
            null,
            'd/m/Y H:i:s',
        );

        $this->assertSame('18/06/2026 23:45:25', $formatted);
    }

    public function test_marcacao_wall_clock_shows_literal_time_without_shift(): void
    {
        $formatted = DateTimeDisplay::marcacaoWallClock(
            Carbon::parse('2026-09-15T10:10:00.000000Z'),
            'd/m/Y H:i',
        );

        $this->assertSame('15/09/2026 10:10', $formatted);
    }
}
