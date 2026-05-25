<?php

namespace Tests\Feature\Wallet;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Organization;
use App\Models\Store;
use App\Services\ClientWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClientWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientWalletService $wallet;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallet = app(ClientWalletService::class);

        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-wallet',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Wallet',
            'slug' => 'loja-wallet',
        ]);
        $this->client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Carteira',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
    }

    public function test_credit_updates_balance_and_ledger(): void
    {
        $tx = $this->wallet->credit(
            $this->client,
            2000,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:1',
            ['description' => 'Crédito teste'],
        );

        $this->client->refresh();

        $this->assertSame(2000, $tx->amount_cents);
        $this->assertSame(2000, $tx->balance_after_cents);
        $this->assertSame(2000, $this->wallet->getBalanceCents($this->client));
        $this->assertSame(2000, $this->wallet->getLedgerBalanceCents($this->client));
    }

    public function test_debit_fails_when_insufficient_balance(): void
    {
        $this->expectException(InsufficientWalletBalanceException::class);

        $this->wallet->debit(
            $this->client,
            500,
            ClientWalletTransaction::TYPE_DEBIT_POS_CHECKOUT,
            'pos_debit:sale:1',
            ['description' => 'Débito teste'],
        );
    }

    public function test_idempotency_returns_existing_transaction(): void
    {
        $first = $this->wallet->credit(
            $this->client,
            1000,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:99',
            ['description' => 'Primeiro'],
        );

        $second = $this->wallet->credit(
            $this->client,
            1000,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:99',
            ['description' => 'Duplicado'],
        );

        $this->client->refresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1000, $this->wallet->getBalanceCents($this->client));
        $this->assertSame(1, ClientWalletTransaction::query()->where('client_id', $this->client->id)->count());
    }

    public function test_reconcile_detects_drift(): void
    {
        $this->wallet->credit(
            $this->client,
            1500,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:2',
            ['description' => 'Crédito'],
        );

        Client::query()->whereKey($this->client->id)->update(['wallet_balance_cents' => 0]);

        $result = $this->wallet->reconcileClient($this->client->fresh());

        $this->assertFalse($result->isConsistent());
        $this->assertSame(0, $result->cachedBalanceCents);
        $this->assertSame(1500, $result->ledgerBalanceCents);
    }

    public function test_reconcile_fix_aligns_cached_balance(): void
    {
        $this->wallet->credit(
            $this->client,
            800,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:3',
            ['description' => 'Crédito'],
        );

        Client::query()->whereKey($this->client->id)->update(['wallet_balance_cents' => 100]);

        $result = $this->wallet->reconcileClient($this->client->fresh(), fix: true);

        $this->assertTrue($result->wasFixed);
        $this->assertTrue($result->isConsistent());
        $this->assertSame(800, $this->wallet->getBalanceCents($this->client->fresh()));
    }

    public function test_wallet_reconcile_command_reports_success_when_consistent(): void
    {
        $this->wallet->credit(
            $this->client,
            300,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:4',
            ['description' => 'Crédito'],
        );

        Artisan::call('wallet:reconcile');

        $this->assertSame(0, Artisan::call('wallet:reconcile'));
    }

    public function test_wallet_reconcile_command_fails_on_drift_without_fix(): void
    {
        $this->wallet->credit(
            $this->client,
            400,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:5',
            ['description' => 'Crédito'],
        );

        Client::query()->whereKey($this->client->id)->update(['wallet_balance_cents' => 0]);

        $this->assertSame(1, Artisan::call('wallet:reconcile'));
    }

    public function test_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        $tx = $this->wallet->credit(
            $this->client,
            100,
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
            'cancel_credit:event:6',
            ['description' => 'Crédito'],
        );

        $this->expectException(\LogicException::class);
        $tx->update(['description' => 'Alterado']);
    }
}
