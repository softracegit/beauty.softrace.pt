<?php

namespace Tests\Feature\Api;

use App\Mail\BookingAuthCodeMail;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientApiToken;
use App\Models\CrmSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * API v1 fase 2: entrada com código, conta, criar e cancelar marcações.
 */
class ClientApiV1Test extends TestCase
{
    use BookingApiFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    private function lastCodeSentTo(string $email): string
    {
        $code = null;
        Mail::assertSent(BookingAuthCodeMail::class, function (BookingAuthCodeMail $mail) use ($email, &$code) {
            if ($mail->hasTo($email)) {
                $code = $mail->code;

                return true;
            }

            return false;
        });

        return (string) $code;
    }

    /** Novo cliente: pede código, valida, conclui registo e recebe token. */
    private function registerNewClient(string $email = 'nova@cliente.test'): string
    {
        $this->postJson('/api/v1/stores/aveiro/auth/request-code', ['login' => $email])
            ->assertOk()
            ->assertJsonPath('channel', 'email')
            ->assertJsonPath('known_account', false);

        $verify = $this->postJson('/api/v1/stores/aveiro/auth/verify-code', [
            'login' => $email,
            'code' => $this->lastCodeSentTo($email),
        ])->assertOk()->assertJsonPath('requires_registration', true);

        $done = $this->postJson('/api/v1/stores/aveiro/auth/complete-registration', [
            'registration_token' => $verify->json('registration_token'),
            'name' => 'Joana Cliente',
            'phone' => '912345678',
            'terms_accepted' => true,
            'device_name' => 'iPhone de teste',
        ])->assertOk()
            ->assertJsonPath('is_new_account', true)
            ->assertJsonPath('customer.email', $email)
            ->assertJsonPath('customer.name', 'Joana Cliente');

        $token = (string) $done->json('token');
        $this->assertStringContainsString('|', $token);

        return $token;
    }

    private function nextWorkingDay(): Carbon
    {
        $day = now('Europe/Lisbon')->addDays(3)->startOfDay();
        while ($day->isSunday() || \App\Support\PortugueseNationalHolidays::isHoliday($day)) {
            $day->addDay();
        }

        return $day;
    }

    public function test_full_flow_register_book_list_cancel_and_logout(): void
    {
        $fx = $this->fixture();
        CrmSetting::setBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, false, $fx['store']->id);
        $token = $this->registerNewClient();
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/stores/aveiro/me', $auth)
            ->assertOk()
            ->assertJsonPath('data.phone', '+351912345678');

        $gel = $fx['gel']->load('options');
        $comExtensao = $gel->options->firstWhere('name', 'Com extensão');
        $reparacao = $gel->options->firstWhere('name', 'Reparação');
        $day = $this->nextWorkingDay();

        $created = $this->postJson('/api/v1/stores/aveiro/bookings', [
            'date' => $day->format('Y-m-d'),
            'time' => '10:00',
            'agent_id' => 'any',
            'notes' => 'Primeira vez',
            'services' => [
                ['id' => $gel->id, 'service_option_id' => $comExtensao->id],
                ['id' => $gel->id, 'service_option_id' => $reparacao->id],
            ],
        ], $auth);
        $created->assertCreated();

        $created->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.technician_name', 'Laissa')
            ->assertJsonPath('data.starts_at', $day->format('Y-m-d').'T10:00')
            ->assertJsonPath('data.ends_at', $day->format('Y-m-d').'T12:05')
            ->assertJsonPath('data.can_cancel', true);
        $this->assertEquals(39, $created->json('data.total'));

        $eventId = (int) $created->json('data.id');
        $this->assertDatabaseHas('calendar_events', ['id' => $eventId, 'event_type' => CalendarEvent::TYPE_MARCACAO]);

        // O mesmo horário já não está disponível
        $this->postJson('/api/v1/stores/aveiro/bookings', [
            'date' => $day->format('Y-m-d'), 'time' => '10:00', 'agent_id' => (string) $fx['agent']->id,
            'services' => [['id' => $gel->id, 'service_option_id' => $comExtensao->id]],
        ], $auth)->assertStatus(422);

        $this->getJson('/api/v1/stores/aveiro/me/bookings', $auth)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $eventId);

        // Simula um pedido novo (no servidor real a loja atual não vem do pedido anterior).
        $this->app->forgetInstance(\App\Support\CurrentStore::class);
        $this->postJson("/api/v1/stores/aveiro/bookings/{$eventId}/cancel", ['reason' => 'Imprevisto'], $auth)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson('/api/v1/stores/aveiro/auth/logout', [], $auth)->assertOk();
        $this->assertSame(0, ClientApiToken::query()->count());
        $this->getJson('/api/v1/stores/aveiro/me', $auth)->assertUnauthorized();
    }

    public function test_existing_client_logs_in_with_code_and_updates_name(): void
    {
        $this->fixture();
        $this->registerNewClient('ja@cliente.test');
        ClientApiToken::query()->delete();
        Mail::fake();
        \Illuminate\Support\Facades\Cache::flush(); // ignora o intervalo mínimo entre códigos

        $this->postJson('/api/v1/stores/aveiro/auth/request-code', ['login' => 'ja@cliente.test'])
            ->assertOk()->assertJsonPath('known_account', true);

        $res = $this->postJson('/api/v1/stores/aveiro/auth/verify-code', [
            'login' => 'ja@cliente.test',
            'code' => $this->lastCodeSentTo('ja@cliente.test'),
        ])->assertOk()->assertJsonPath('is_new_account', false);

        $auth = ['Authorization' => 'Bearer '.$res->json('token')];
        $this->patchJson('/api/v1/stores/aveiro/me', ['name' => 'Joana Silva Cliente'], $auth)
            ->assertOk()
            ->assertJsonPath('data.name', 'Joana Silva Cliente');
        $this->assertSame('Joana Silva Cliente', Client::query()->first()->name);
    }

    public function test_wrong_code_and_expired_registration_token_are_rejected(): void
    {
        $this->fixture();
        $this->postJson('/api/v1/stores/aveiro/auth/request-code', ['login' => 'x@cliente.test'])->assertOk();

        $this->postJson('/api/v1/stores/aveiro/auth/verify-code', ['login' => 'x@cliente.test', 'code' => '000000'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->postJson('/api/v1/stores/aveiro/auth/complete-registration', [
            'registration_token' => 'inválido', 'name' => 'Xpto Teste', 'phone' => '912345678', 'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['login']);
    }

    public function test_protected_endpoints_require_token_and_cannot_touch_other_clients_bookings(): void
    {
        $fx = $this->fixture();
        $this->getJson('/api/v1/stores/aveiro/me')->assertUnauthorized();
        $this->getJson('/api/v1/stores/aveiro/me', ['Authorization' => 'Bearer 1|nope'])->assertUnauthorized();

        $other = Client::query()->create([
            'store_id' => $fx['store']->id, 'organization_id' => $fx['org']->id,
            'name' => 'Outra Pessoa', 'email' => 'outra@cliente.test', 'phone' => '+351913000000',
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $fx['store']->id, 'client_id' => $other->id, 'user_id' => $fx['agent']->user_id,
            'event_type' => CalendarEvent::TYPE_MARCACAO, 'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Outra', 'start_at' => now()->addDays(5), 'end_at' => now()->addDays(5)->addHour(),
        ]);

        $token = $this->registerNewClient();
        $this->postJson("/api/v1/stores/aveiro/bookings/{$event->id}/cancel", [], ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();
        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->fresh()->status);
    }

    public function test_store_with_prepayment_asks_to_use_web(): void
    {
        $fx = $this->fixture();
        CrmSetting::setBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, true, $fx['store']->id);
        $token = $this->registerNewClient();

        $this->postJson('/api/v1/stores/aveiro/bookings', [
            'date' => $this->nextWorkingDay()->format('Y-m-d'), 'time' => '10:00', 'agent_id' => 'any',
            'services' => [['id' => $fx['gel']->id]],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'web_url']);
    }

    public function test_web_booking_login_route_still_works(): void
    {
        $this->fixture();
        $this->postJson(route('booking.auth.request_code', ['store' => 'aveiro']), ['login' => 'web@cliente.test'])
            ->assertOk()
            ->assertJsonPath('channel', 'email');
        $this->assertFalse(User::query()->where('email', 'web@cliente.test')->exists());
    }
}
