<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcacaoReminderResetOnRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Reminder Reset',
            'slug' => 'org-reminder-reset',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Reminder Reset',
            'slug' => 'loja-reminder-reset',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Reminder Reset',
            'email' => 'cliente-reminder-reset@test.test',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço',
            'duration' => 30,
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Receção Reminder Reset',
            'email' => 'rececao-reminder-reset@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Receção Reminder Reset',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'service');
    }

    public function test_changing_start_day_clears_reminder_flags_and_reopens_agendado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $start = Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon');
        $event = CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'client_id' => $fx['client']->id,
            'user_id' => $fx['staff']->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_NOTIFICADO,
            'title' => 'Marcação',
            'start_at' => $start->copy()->utc(),
            'end_at' => $start->copy()->addMinutes(30)->utc(),
            'service_id' => $fx['service']->id,
            'booking_sms_reminder_sent_at' => now(),
            'booking_sms_reminder_failed_at' => null,
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $fx['service']->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        $newStart = Carbon::parse('2026-06-18 10:00:00', 'Europe/Lisbon');
        $newEnd = $newStart->copy()->addMinutes(30);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->putJson(route('agenda.events.update', $event), [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newEnd->toIso8601String(),
            ])
            ->assertOk();

        $event->refresh();
        $this->assertNull($event->booking_sms_reminder_sent_at);
        $this->assertNull($event->booking_sms_reminder_failed_at);
        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->status);
    }

    public function test_same_day_time_change_keeps_reminder_flags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $start = Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon');
        $sentAt = now();
        $event = CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'client_id' => $fx['client']->id,
            'user_id' => $fx['staff']->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_NOTIFICADO,
            'title' => 'Marcação',
            'start_at' => $start->copy()->utc(),
            'end_at' => $start->copy()->addMinutes(30)->utc(),
            'service_id' => $fx['service']->id,
            'booking_sms_reminder_sent_at' => $sentAt,
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $fx['service']->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        $newStart = Carbon::parse('2026-06-15 14:00:00', 'Europe/Lisbon');
        $newEnd = $newStart->copy()->addMinutes(30);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->putJson(route('agenda.events.update', $event), [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newEnd->toIso8601String(),
            ])
            ->assertOk();

        $event->refresh();
        $this->assertNotNull($event->booking_sms_reminder_sent_at);
        $this->assertSame(CalendarEvent::STATUS_NOTIFICADO, $event->status);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
