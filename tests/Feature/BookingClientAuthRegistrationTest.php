<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingClientAuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_E164 = '+351934567890';

    public function test_complete_registration_phone_channel_attaches_booking_user_to_existing_client(): void
    {
        $sharedEmail = 'legacy-booking-client@example.test';

        $client = Client::query()->create([
            'name' => 'Cliente CRM',
            'email' => $sharedEmail,
            'phone' => self::PHONE_E164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson('/booking/auth/complete-registration', [
            'name' => 'Nome Actualizado',
            'email' => $sharedEmail,
            'phone' => '',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'is_new_account' => true]);

        $this->assertDatabaseHas('users', [
            'email' => $sharedEmail,
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
        ]);

        $client->refresh();
        $this->assertSame('Nome Actualizado', $client->name);
        $this->assertSame(self::PHONE_E164, $client->phone);
    }

    public function test_complete_registration_phone_channel_creates_new_client_when_phone_unknown(): void
    {
        $email = 'brand-new-booking@example.test';

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson('/booking/auth/complete-registration', [
            'name' => 'Cliente Novo',
            'email' => $email,
            'phone' => '',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('clients', [
            'email' => $email,
            'phone' => self::PHONE_E164,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => User::ROLE_CLIENTE,
        ]);
    }

    public function test_complete_registration_phone_channel_rejects_conflicting_email_on_existing_phone(): void
    {
        Client::query()->create([
            'name' => 'Outro',
            'email' => 'existing-on-file@example.test',
            'phone' => self::PHONE_E164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson('/booking/auth/complete-registration', [
            'name' => 'Tentativa',
            'email' => 'different@example.test',
            'phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_complete_registration_rejects_when_pending_session_missing(): void
    {
        $response = $this->postJson('/booking/auth/complete-registration', [
            'name' => 'Sem Sessão',
            'email' => 'orphan@example.test',
            'phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }
}
