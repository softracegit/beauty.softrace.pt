<?php

namespace Tests\Feature\Cancellation;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingAccountCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_client_can_cancel_future_appointment_without_503(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-cancel-booking',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-cancel-booking',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Booking',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $user = User::query()->create([
            'name' => 'Cliente Booking',
            'email' => 'booking-cancel@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
        ]);

        $startUtc = Carbon::parse('2026-06-15 15:00:00', 'Europe/Lisbon')->timezone(config('app.timezone'));
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Teste',
            'start_at' => $startUtc,
            'end_at' => $startUtc->copy()->addHour(),
        ]);

        $response = $this->actingAs($user)->post(
            route('booking.conta.marcacoes.cancel', [
                'store' => $store->slug,
                'calendarEvent' => $event->id,
            ]),
            ['cancellation_reason' => 'Teste cancelamento'],
        );

        $response->assertRedirect(route('booking.conta.marcacoes', ['store' => $store->slug]));
        $response->assertSessionHas('success');
        $this->assertSame(CalendarEvent::STATUS_CANCELADO, $event->fresh()->status);

        Carbon::setTestNow();
    }
}
