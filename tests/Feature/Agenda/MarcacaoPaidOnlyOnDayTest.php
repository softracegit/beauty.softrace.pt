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
use App\Support\MarcacaoPaymentGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcacaoPaidOnlyOnDayTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Paid Day',
            'slug' => 'org-paid-day',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Paid Day',
            'slug' => 'loja-paid-day',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Paid Day',
            'email' => 'cliente-paid-day@test.test',
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
            'name' => 'Receção Paid Day',
            'email' => 'rececao-paid-day@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Receção Paid Day',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'service');
    }

    private function createMarcacao(array $fx, Carbon $start): CalendarEvent
    {
        $event = CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'client_id' => $fx['client']->id,
            'user_id' => $fx['staff']->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_TERMINADO,
            'title' => 'Marcação Paid Day',
            'start_at' => $start->copy()->utc(),
            'end_at' => $start->copy()->addMinutes(30)->utc(),
            'service_id' => $fx['service']->id,
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $fx['service']->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        return $event;
    }

    public function test_completo_before_appointment_day_returns_422(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 20:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_COMPLETO,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', MarcacaoPaymentGate::denialMessage());

        $this->assertSame(CalendarEvent::STATUS_TERMINADO, $event->fresh()->status);
    }

    public function test_completo_on_appointment_day_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_COMPLETO,
            ])
            ->assertOk()
            ->assertJsonPath('status', CalendarEvent::STATUS_COMPLETO);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
