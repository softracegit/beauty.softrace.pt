<?php

namespace Tests\Feature\Wallet;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\ClientWalletService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWalletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_with_wallet_payment_method_debits_balance(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org POS',
            'slug' => 'org-pos',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja POS',
            'slug' => 'loja-pos',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente POS',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        app(ClientWalletService::class)->credit(
            $client,
            5000,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:pos1',
            ['description' => 'Crédito inicial'],
        );

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação POS',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);

        $staff = User::query()->create([
            'name' => 'Staff POS',
            'email' => 'staff-pos@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff POS',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $response = $this->actingAs($staff)
            ->withSession([SetCurrentStore::SESSION_KEY => $store->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $event->id,
                'payment_method' => Sale::PAYMENT_CREDITOS_CARTEIRA,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [
                    [
                        'tipo' => 'servico',
                        'descricao' => 'Corte',
                        'quantidade' => 1,
                        'preco_unitario' => 30,
                        'subtotal' => 30,
                    ],
                ],
            ]);

        $response->assertOk();
        $client->refresh();
        $event->refresh();

        $this->assertSame(2000, $client->wallet_balance_cents);
        $this->assertSame(CalendarEvent::STATUS_COMPLETO, $event->status);
        $this->assertDatabaseHas('sales', [
            'calendar_event_id' => $event->id,
            'payment_method' => Sale::PAYMENT_CREDITOS_CARTEIRA,
            'status' => Sale::STATUS_PAGO,
        ]);
        $this->assertDatabaseHas('client_wallet_transactions', [
            'client_id' => $client->id,
            'type' => ClientWalletTransaction::TYPE_DEBIT_POS_CHECKOUT,
            'amount_cents' => -3000,
        ]);
    }
}
