<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingAvailabilityMinLeadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, agent: Agent}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Booking Lead',
            'slug' => 'org-booking-lead',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Booking Lead',
            'slug' => 'loja-booking-lead',
            'timezone' => 'Europe/Lisbon',
            'weekly_schedule' => Store::defaultWeeklySchedule(),
        ]);
        $staff = User::query()->create([
            'name' => 'Prestador Booking Lead',
            'email' => 'prestador-booking-lead@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Prestador Booking Lead',
            'status' => Agent::STATUS_ACTIVE,
            'visible_in_booking' => true,
            'visible_in_agenda' => true,
        ]);

        return compact('store', 'agent');
    }

    public function test_today_slots_empty_when_min_lead_crosses_midnight(): void
    {
        $fx = $this->fixture();
        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');

        // Quarta-feira à noite (não feriado típico): agora + 30 min já é o dia seguinte.
        Carbon::setTestNow(Carbon::parse('2026-10-21 23:51:00', $tz));

        $response = $this->getJson(route('booking.availability', [
            'store' => $fx['store']->slug,
            'date' => '2026-10-21',
            'agent_id' => $fx['agent']->id,
            'duration' => 30,
        ]));

        $response->assertOk();
        $response->assertJsonPath('slots', []);

        Carbon::setTestNow();
    }

    public function test_today_slots_start_after_min_lead_same_evening(): void
    {
        $fx = $this->fixture();
        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');

        Carbon::setTestNow(Carbon::parse('2026-10-21 18:10:00', $tz));

        $response = $this->getJson(route('booking.availability', [
            'store' => $fx['store']->slug,
            'date' => '2026-10-21',
            'agent_id' => $fx['agent']->id,
            'duration' => 30,
        ]));

        $response->assertOk();
        $slots = $response->json('slots');
        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
        $this->assertSame('18:45', $slots[0]);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('18:00', $slots);
        $this->assertNotContains('18:15', $slots);
        $this->assertNotContains('18:30', $slots);

        Carbon::setTestNow();
    }
}
