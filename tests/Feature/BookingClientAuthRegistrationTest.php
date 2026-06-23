<?php

namespace Tests\Feature;

use App\Models\BookingAuthCode;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use App\Support\PhoneDisplay;
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
        $this->assertNotNull($client->phone_verified_at);
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

    public function test_complete_registration_phone_channel_uses_existing_client_email_when_submitted_email_differs(): void
    {
        $sharedEmail = 'existing-on-file@example.test';

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
            'email' => 'different@example.test',
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
        $this->assertSame($sharedEmail, strtolower((string) $client->email));
    }

    public function test_complete_registration_phone_channel_allows_missing_email_when_client_has_email_on_file(): void
    {
        $sharedEmail = 'legacy-with-email@example.test';

        Client::query()->create([
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
            'name' => 'Cliente CRM',
            'email' => '',
            'phone' => '',
            'terms_accepted' => true,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('users', [
            'email' => $sharedEmail,
            'role' => User::ROLE_CLIENTE,
        ]);
    }

    public function test_complete_registration_email_channel_uses_existing_client_phone_when_submitted_phone_differs(): void
    {
        $sharedEmail = 'legacy-email-channel@example.test';
        $clientPhone = '+351912345678';

        $client = Client::query()->create([
            'store_id' => Store::defaultPublicBookingStoreId(),
            'name' => 'Cliente Email',
            'email' => $sharedEmail,
            'phone' => $clientPhone,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $response = $this->withSession([
            'booking_auth.pending_registration.channel' => 'email',
            'booking_auth.pending_registration.identifier' => $sharedEmail,
        ])->postJson($this->bookingBasePath().'/auth/complete-registration', [
            'name' => 'Cliente Email',
            'email' => '',
            'phone' => '+351900000000',
            'terms_accepted' => true,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $client->refresh();
        $this->assertSame($clientPhone, PhoneDisplay::toE164((string) $client->phone));
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

    public function test_verify_code_phone_channel_marks_phone_verified_on_login(): void
    {
        $email = 'login-phone@example.test';
        $client = Client::query()->create([
            'store_id' => Store::defaultPublicBookingStoreId(),
            'name' => 'Cliente Login',
            'email' => $email,
            'phone' => self::PHONE_E164,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $user = User::query()->create([
            'name' => 'Cliente Login',
            'email' => $email,
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
        ]);

        $code = '123456';
        BookingAuthCode::query()->create([
            'store_id' => Store::defaultPublicBookingStoreId(),
            'email' => self::PHONE_E164,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson($this->bookingBasePath().'/auth/verify-code', [
            'phone' => self::PHONE_E164,
            'code' => $code,
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'is_new_account' => false]);
        $this->assertAuthenticatedAs($user);

        $client->refresh();
        $this->assertNotNull($client->phone_verified_at);
    }
}
