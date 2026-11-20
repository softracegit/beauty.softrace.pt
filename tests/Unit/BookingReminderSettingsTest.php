<?php

namespace Tests\Unit;

use App\Services\BookingReminderSettings;
use Tests\TestCase;

class BookingReminderSettingsTest extends TestCase
{
    public function test_day_before_window_and_time_helpers(): void
    {
        $this->assertTrue(BookingReminderSettings::isWithinDayBeforeSendWindow([
            'day_before_send_start_hour' => 9,
            'day_before_send_end_hour' => 20,
        ], 9));
        $this->assertFalse(BookingReminderSettings::isWithinDayBeforeSendWindow([
            'day_before_send_start_hour' => 9,
            'day_before_send_end_hour' => 20,
        ], 20));
        $this->assertTrue(BookingReminderSettings::isWithinDayBeforeSendWindow([
            'day_before_send_start_hour' => 9,
            'day_before_send_end_hour' => 9,
        ], 3));

        $this->assertTrue(BookingReminderSettings::isWithinSameDaySendWindow([
            'same_day_send_start_minutes' => 8 * 60 + 30,
            'same_day_send_end_minutes' => 18 * 60,
        ], 12 * 60));
        $this->assertFalse(BookingReminderSettings::isWithinSameDaySendWindow([
            'same_day_send_start_minutes' => 8 * 60 + 30,
            'same_day_send_end_minutes' => 18 * 60,
        ], 8 * 60));

        $this->assertSame('08:30', BookingReminderSettings::minutesToTime(510));
        $this->assertSame(510, BookingReminderSettings::timeToMinutes('08:30'));
        $this->assertSame(2, BookingReminderSettings::minutesToHours(120));
        $this->assertSame(1.5, BookingReminderSettings::minutesToHours(90));
        $this->assertSame(90, BookingReminderSettings::hoursToMinutes(1.5));
    }
}
