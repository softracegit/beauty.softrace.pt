<?php

namespace Tests\Unit;

use App\Support\WeeklyScheduleWindow;
use PHPUnit\Framework\TestCase;

class WeeklyScheduleWindowTest extends TestCase
{
    public function test_agent_end_after_store_close_extends_window(): void
    {
        $store = [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '20:00'],
        ];
        $agent = [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '20:30'],
        ];

        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent, 'mon', $store);

        $this->assertSame([9 * 60, 20 * 60 + 30], $window);
    }

    public function test_agent_end_before_store_close_uses_intersection(): void
    {
        $store = [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '20:00'],
        ];
        $agent = [
            'mon' => ['enabled' => true, 'start' => '10:00', 'end' => '18:00'],
        ];

        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent, 'mon', $store);

        $this->assertSame([10 * 60, 18 * 60], $window);
    }

    public function test_agent_start_after_store_open_uses_later_start(): void
    {
        $store = [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '20:00'],
        ];
        $agent = [
            'mon' => ['enabled' => true, 'start' => '10:00', 'end' => '20:30'],
        ];

        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent, 'mon', $store);

        $this->assertSame([10 * 60, 20 * 60 + 30], $window);
    }
}
