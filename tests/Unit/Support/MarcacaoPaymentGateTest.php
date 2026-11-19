<?php

namespace Tests\Unit\Support;

use App\Models\CalendarEvent;
use App\Models\Organization;
use App\Models\Store;
use App\Support\MarcacaoPaymentGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcacaoPaymentGateTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        $org = Organization::query()->create([
            'name' => 'Org Pay Gate',
            'slug' => 'org-pay-gate',
            'status' => 'active',
        ]);

        return Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Pay Gate',
            'slug' => 'loja-pay-gate',
            'timezone' => 'Europe/Lisbon',
        ]);
    }

    public function test_cannot_mark_paid_before_appointment_civil_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 18:00:00', 'Europe/Lisbon'));
        $store = $this->store();
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'store_id' => $store->id,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'start_at' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon')->utc(),
        ]);

        $this->assertFalse(MarcacaoPaymentGate::canMarkPaid($event));
    }

    public function test_can_mark_paid_on_appointment_civil_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00', 'Europe/Lisbon'));
        $store = $this->store();
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'store_id' => $store->id,
            'status' => CalendarEvent::STATUS_TERMINADO,
            'start_at' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon')->utc(),
        ]);

        $this->assertTrue(MarcacaoPaymentGate::canMarkPaid($event));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
