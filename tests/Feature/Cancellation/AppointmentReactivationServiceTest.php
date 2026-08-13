<?php

namespace Tests\Feature\Cancellation;

use App\Exceptions\AppointmentReactivationException;
use App\Http\Middleware\SetCurrentStore;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ClientAppointmentReactivatedNotification;
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentReactivationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentReactivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Client $client;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::query()->create([
            'name' => 'Org Reactivate',
            'slug' => 'org-reactivate',
            'status' => 'active',
        ]);
        $this->store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Reactivate',
            'slug' => 'loja-reactivate',
            'timezone' => 'Europe/Lisbon',
        ]);
        $this->client = Client::query()->create([
            'store_id' => $this->store->id,
            'name' => 'Cliente Reactivate',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'email' => 'cliente-reactivate@test.test',
            'notify_email_booking_updates' => true,
        ]);
        $this->admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-reactivate@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        $this->admin->stores()->attach($this->store->id);
        $this->technician = User::query()->create([
            'name' => 'Técnica',
            'email' => 'tech-reactivate@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
        ]);

        CrmSetting::setInt(
            CrmSetting::KEY_BOOKING_CANCELLATION_NOTICE_HOURS,
            3,
            $this->store->id,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reactivate_faltou_sets_agendado_and_clears_cancellation_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill([
            'status' => CalendarEvent::STATUS_FALTOU,
            'cancellation_type' => CalendarEvent::STATUS_FALTOU,
            'cancellation_reason' => 'Não apareceu',
            'avisou_dentro_prazo' => false,
            'refund_reserva' => false,
        ])->save();

        $result = app(AppointmentReactivationService::class)->reactivate($event, [
            'reactivation_reason' => 'Cliente ligou a dizer que chega',
        ]);

        $event->refresh();

        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->status);
        $this->assertNull($event->cancellation_type);
        $this->assertNull($event->cancellation_reason);
        $this->assertNull($event->avisou_dentro_prazo);
        $this->assertFalse((bool) $event->refund_reserva);
        $this->assertSame(CalendarEvent::STATUS_FALTOU, $result->previousStatus);
    }

    public function test_reactivate_blocked_when_wallet_was_credited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        Booking::query()->create([
            'store_id' => $this->store->id,
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'calendar_event_id' => $event->id,
            'client_id' => $this->client->id,
            'total_price' => 100,
            'paid_amount' => 20,
            'remaining_amount' => 80,
            'deposit_percent_used' => 20,
            'payment_status' => Booking::PAYMENT_PAID,
            'request_payload' => [],
        ]);

        app(AppointmentCancellationService::class)->cancel($event);

        $this->expectException(AppointmentReactivationException::class);
        app(AppointmentReactivationService::class)->reactivate($event->fresh());
    }

    public function test_reactivate_blocked_when_slot_occupied(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $cancelled = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $cancelled->forceFill([
            'status' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_type' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_reason' => 'Erro',
        ])->save();

        $this->createMarcacaoAtLocal('2026-06-15 15:00:00');

        $blockers = app(AppointmentReactivationService::class)->blockers($cancelled->fresh());

        $this->assertNotEmpty($blockers);
        $this->assertStringContainsString('ocupado', $blockers[0]);

        $this->expectException(AppointmentReactivationException::class);
        app(AppointmentReactivationService::class)->reactivate($cancelled->fresh());
    }

    public function test_reactivate_blocked_when_start_is_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 16:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill([
            'status' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_type' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_reason' => 'Erro',
        ])->save();

        $blockers = app(AppointmentReactivationService::class)->blockers($event->fresh());

        $this->assertNotEmpty($blockers);
        $this->assertStringContainsString('passado', $blockers[0]);

        $this->expectException(AppointmentReactivationException::class);
        app(AppointmentReactivationService::class)->reactivate($event->fresh());
    }

    public function test_reactivate_allowed_when_start_is_exactly_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 15:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill([
            'status' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_type' => CalendarEvent::STATUS_CANCELADO,
        ])->save();

        $result = app(AppointmentReactivationService::class)->reactivate($event->fresh());

        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $result->event->status);
    }

    public function test_reactivate_rejects_anulado_and_agendado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $agendado = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $this->expectException(AppointmentReactivationException::class);
        app(AppointmentReactivationService::class)->reactivate($agendado);
    }

    public function test_reactivate_notifies_client_when_requested(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill([
            'status' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_type' => CalendarEvent::STATUS_CANCELADO,
        ])->save();

        $result = app(AppointmentReactivationService::class)->reactivate($event, [
            'notify_client' => true,
            'reactivation_reason' => 'Reabertura pedida',
        ]);

        $this->assertTrue($result->clientNotified);
        Notification::assertSentOnDemand(ClientAppointmentReactivatedNotification::class);
    }

    public function test_http_reativar_requires_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill(['status' => CalendarEvent::STATUS_FALTOU])->save();

        $rececao = User::query()->create([
            'name' => 'Receção',
            'email' => 'rececao-reactivate@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $this->store->organization_id,
        ]);
        $rececao->stores()->attach($this->store->id);

        $this->actingAs($rececao)
            ->withSession([SetCurrentStore::SESSION_KEY => $this->store->id])
            ->postJson(route('relatorios.marcacoes.reativar', $event), [
                'reactivation_reason' => 'teste',
            ])
            ->assertForbidden();
    }

    public function test_http_reativar_success_for_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $event->forceFill([
            'status' => CalendarEvent::STATUS_CANCELADO,
            'cancellation_reason' => 'Erro de clique',
        ])->save();

        $this->actingAs($this->admin)
            ->withSession([SetCurrentStore::SESSION_KEY => $this->store->id])
            ->postJson(route('relatorios.marcacoes.reativar', $event), [
                'reactivation_reason' => 'Foi um erro',
                'notify_client' => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->fresh()->status);
    }

    public function test_http_preview_returns_blockers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $cancelled = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $cancelled->forceFill(['status' => CalendarEvent::STATUS_CANCELADO])->save();
        $this->createMarcacaoAtLocal('2026-06-15 15:00:00');

        $this->actingAs($this->admin)
            ->withSession([SetCurrentStore::SESSION_KEY => $this->store->id])
            ->getJson(route('relatorios.marcacoes.reativar-preview', $cancelled))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'can_reactivate' => false,
            ])
            ->assertJsonStructure(['blockers']);
    }

    private function createMarcacaoAtLocal(string $localDateTime): CalendarEvent
    {
        $tz = 'Europe/Lisbon';
        $startLocal = Carbon::parse($localDateTime, $tz);
        $startUtc = $startLocal->copy()->timezone(config('app.timezone'));

        return CalendarEvent::query()->create([
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'user_id' => $this->technician->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação teste',
            'start_at' => $startUtc,
            'end_at' => $startUtc->copy()->addHour(),
        ]);
    }
}
