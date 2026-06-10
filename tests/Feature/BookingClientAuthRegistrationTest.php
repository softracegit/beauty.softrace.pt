<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingClientAuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_E164 = '+351934567890';

    private function bookingBasePath(): string
    {
        return '/booking/'.Store::defaultPublicBookingStoreSlug();
    }

    public function test_complete_registration_phone_channel_attaches_booking_user_to_existing_client(): void
    {
        $sharedEmail = 'legacy-booking-client@example.test';

        $client = Client::query()->create([
            'store_id' => Store::defaultPublicBookingStoreId(),
            'name' => 'Cliente CRM',
            'email' => $sharedEmail,
            'phone' => self::PHONE_E164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Nome Actualizado',
            'email' => $sharedEmail,
            'phone' => '',
            'terms_accepted' => true,
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
        $this->assertNotNull($client->terms_accepted_at);
        $this->assertSame((string) config('legal.privacy_version'), $client->privacy_policy_version);
    }

    public function test_complete_registration_phone_channel_creates_new_client_when_phone_unknown(): void
    {
        $email = 'brand-new-booking@example.test';

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Cliente Novo',
            'email' => $email,
            'phone' => '',
            'terms_accepted' => true,
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
            'store_id' => Store::defaultPublicBookingStoreId(),
            'name' => 'Outro',
            'email' => 'existing-on-file@example.test',
            'phone' => self::PHONE_E164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Tentativa',
            'email' => 'different@example.test',
            'phone' => '',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_complete_registration_rejects_when_pending_session_missing(): void
    {
        $response = $this->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Sem Sessão',
            'email' => 'orphan@example.test',
            'phone' => '',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }

    public function test_complete_registration_rejects_without_terms_accepted(): void
    {
        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'phone',
            'booking_auth.pending_registration.identifier' => self::PHONE_E164,
        ])->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Sem Termos',
            'email' => 'noterms@example.test',
            'phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terms_accepted']);
    }
}
