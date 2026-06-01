<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\ClientWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgendaDepositReceptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent}
     */
    private function agendaDepositFixture(float $servicePrice = 50.0): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Agenda Deposit',
            'slug' => 'org-agenda-deposit',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Deposit',
            'slug' => 'loja-deposit',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Reserva',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'phone' => '+351912345678',
        ]);

        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $cat->id,
            'name' => 'Corte',
            'duration' => 30,
            'price' => $servicePrice,
            'online_price' => $servicePrice,
            'sort_order' => 1,
        ]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação reserva',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);

        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => $servicePrice,
            'sort_order' => 0,
        ]);

        $staff = User::query()->create([
            'name' => 'Staff Deposit',
            'email' => 'staff-deposit@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Deposit',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'event');
    }

    public function test_event_show_includes_deposit_payload_fields(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(50.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.events.show', $fixture['event']))
            ->assertOk()
            ->assertJsonPath('deposit_percent', 20)
            ->assertJsonPath('deposit_amount_expected', 10)
            ->assertJsonPath('can_collect_deposit', true)
            ->assertJsonPath('has_booking_reserva_sale', false)
            ->assertJsonPath('has_saved_cards', false);
    }

    public function test_deposit_preview_endpoint(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(100.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.deposit.show', $fixture['event']))
            ->assertOk()
            ->assertJsonPath('subtotal', 100)
            ->assertJsonPath('deposit_percent', 20)
            ->assertJsonPath('deposit_amount', 20)
            ->assertJsonPath('can_collect', true);
    }

    public function test_cash_deposit_creates_booking_and_reserva_sale(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(50.0);

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.store', $fixture['event']), [
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deposit_amount', 10);

        $this->assertDatabaseHas('bookings', [
            'calendar_event_id' => $fixture['event']->id,
            'payment_status' => Booking::PAYMENT_PAID,
            'paid_amount' => 10.0,
        ]);

        $this->assertDatabaseHas('sales', [
            'calendar_event_id' => $fixture['event']->id,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'valor_pago' => 10.0,
            'status' => Sale::STATUS_PAGO,
        ]);
    }

    public function test_duplicate_deposit_is_blocked(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(50.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.store', $fixture['event']), [
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertOk();

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.store', $fixture['event']), [
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Já existe uma fatura de pré-pagamento para esta marcação.');
    }

    public function test_wallet_only_deposit_marks_booking_without_reserva_sale(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(50.0);

        app(ClientWalletService::class)->credit(
            $fixture['client'],
            1000,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'credit:deposit-wallet-only',
            ['description' => 'Crédito teste'],
        );

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.store', $fixture['event']), [
                'wallet_apply' => true,
                'wallet_apply_cents' => 1000,
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallet_applied_cents', 1000)
            ->assertJsonPath('sale_id', null);

        $this->assertDatabaseHas('bookings', [
            'calendar_event_id' => $fixture['event']->id,
            'payment_status' => Booking::PAYMENT_PAID,
            'paid_amount' => 10.0,
            'wallet_applied_cents' => 1000,
        ]);

        $this->assertDatabaseMissing('sales', [
            'calendar_event_id' => $fixture['event']->id,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
        ]);
    }

    public function test_deposit_with_wallet_and_mbway_counts_full_prepayment_in_amount_due(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fixture = $this->agendaDepositFixture(35.0);

        Booking::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'total_price' => 35.0,
            'paid_amount' => 7.0,
            'wallet_applied_cents' => 300,
            'remaining_amount' => 28.0,
            'deposit_percent_used' => 20,
            'payment_status' => Booking::PAYMENT_PAID,
            'request_payload' => ['source' => 'test'],
        ]);

        Sale::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'numero_fatura' => '2026/05-099',
            'data_emissao' => now()->toDateString(),
            'total' => 4.0,
            'valor_pago' => 4.0,
            'payment_method' => Sale::PAYMENT_MBWAY,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
        ]);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.events.show', $fixture['event']))
            ->assertOk()
            ->assertJsonPath('booking_paid_amount', 7)
            ->assertJsonPath('amount_due', 28)
            ->assertJsonPath('invoice_settled', false);
    }

    public function test_custom_amount_when_deposit_percent_zero(): void
    {
        Config::set('booking.deposit_percent', 0);
        $fixture = $this->agendaDepositFixture(50.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.store', $fixture['event']), [
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'custom_amount' => 15.0,
            ])
            ->assertOk()
            ->assertJsonPath('deposit_amount', 15);

        $this->assertDatabaseHas('bookings', [
            'calendar_event_id' => $fixture['event']->id,
            'paid_amount' => 15.0,
        ]);

        $this->assertDatabaseHas('sales', [
            'calendar_event_id' => $fixture['event']->id,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'valor_pago' => 15.0,
        ]);
    }
}
