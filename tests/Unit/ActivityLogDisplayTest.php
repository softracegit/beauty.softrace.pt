<?php

namespace Tests\Unit;

use App\Support\ActivityLogDisplay;
use Carbon\Carbon;
use Tests\TestCase;

class ActivityLogDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'UTC',
            'booking.business_timezone' => 'Europe/Lisbon',
        ]);
    }

    public function test_formats_same_day_calendar_time_without_timezone_shift(): void
    {
        $formatted = ActivityLogDisplay::formatChange(
            'end_at',
            '2026-09-15T10:10:00.000000Z',
            '2026-09-15T10:30:00.000000Z',
        );

        $this->assertSame('15/09/2026 10:10 → 10:30', $formatted);
    }

    public function test_formats_log_timestamp_in_business_timezone(): void
    {
        $formatted = ActivityLogDisplay::formatLogTimestamp(
            Carbon::parse('2026-06-18 22:45:25', 'UTC'),
        );

        $this->assertSame('18/06/2026 23:45:25', $formatted);
    }

    public function test_formats_status_labels(): void
    {
        $formatted = ActivityLogDisplay::formatChange('status', 'confirmado', 'completo');

        $this->assertSame('Confirmado → Pago', $formatted);
    }

    public function test_attribute_labels_are_portuguese(): void
    {
        $this->assertSame('Fim', ActivityLogDisplay::attributeLabel('end_at'));
        $this->assertSame('Início', ActivityLogDisplay::attributeLabel('start_at'));
    }
}
