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
use App\Notifications\ClientAppointmentCancelledNotification;
use App\Support\MarcacaoTerminalReasons;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarcacaoTerminalStatusTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Terminal',
            'slug' => 'org-terminal',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Terminal',
            'slug' => 'loja-terminal',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Terminal',
            'email' => 'cliente-terminal@test.test',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
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
            'name' => 'Receção Terminal',
            'email' => 'rececao-terminal@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Receção Terminal',
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
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação Terminal',
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_faltou_with_valid_reason_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:30:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
                'cancellation_reason' => MarcacaoTerminalReasons::FALTOU[0],
                'cancellation_notes' => 'Cliente não atendeu telefone.',
                'notify_client' => false,
            ])
            ->assertOk()
            ->assertJsonPath('status', CalendarEvent::STATUS_FALTOU);

        $event->refresh();
        $this->assertSame(MarcacaoTerminalReasons::FALTOU[0], $event->cancellation_reason);
        $this->assertSame('Cliente não atendeu telefone.', $event->cancellation_notes);
        $this->assertSame(CalendarEvent::STATUS_FALTOU, $event->cancellation_type);
    }

    public function test_faltou_without_reason_returns_422(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:30:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_faltou_future_start_returns_422(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 13:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
                'cancellation_reason' => MarcacaoTerminalReasons::FALTOU[0],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Só é possível registar falta depois do horário de início da marcação.');
    }

    public function test_cancelado_requires_valid_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 15:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_CANCELADO,
                'cancellation_reason' => MarcacaoTerminalReasons::CANCELADO[0],
            ])
            ->assertOk()
            ->assertJsonPath('status', CalendarEvent::STATUS_CANCELADO);

        $event->refresh();
        $this->assertSame(MarcacaoTerminalReasons::CANCELADO[0], $event->cancellation_reason);
    }

    public function test_faltou_notify_client_sends_email_when_requested(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
                'cancellation_reason' => 'Cliente não disponível',
                'notify_client' => true,
            ])
            ->assertOk();

        Notification::assertSentOnDemand(ClientAppointmentCancelledNotification::class);
    }

    public function test_outra_reason_requires_free_text(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:00:00', 'Europe/Lisbon'));
        $fx = $this->fixture();
        $event = $this->createMarcacao($fx, Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
                'cancellation_reason' => 'Outra razão',
            ])
            ->assertStatus(422);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.status', $event), [
                'status' => CalendarEvent::STATUS_FALTOU,
                'cancellation_reason' => 'Outra razão',
                'cancellation_outra_text' => 'Detalhe personalizado',
            ])
            ->assertOk();

        $event->refresh();
        $this->assertSame('Detalhe personalizado', $event->cancellation_reason);
    }
}
