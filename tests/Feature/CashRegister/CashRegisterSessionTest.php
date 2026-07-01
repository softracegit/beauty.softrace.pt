<?php

namespace Tests\Feature\CashRegister;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\OpensCashRegister;
use Tests\TestCase;

class CashRegisterSessionTest extends TestCase
{
    use OpensCashRegister;
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent, service: Service}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Caixa',
            'slug' => 'org-caixa',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Caixa',
            'slug' => 'loja-caixa',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Caixa',
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
            'price' => 40,
            'online_price' => 40,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff Caixa',
            'email' => 'staff-caixa@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Caixa',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação',
            'start_at' => now()->addDay()->setHour(10),
            'end_at' => now()->addDay()->setHour(10)->addMinutes(30),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 40,
            'sort_order' => 0,
        ]);

        return compact('store', 'staff', 'client', 'event', 'service');
    }

    public function test_open_session_stores_float_and_status(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('caixa.open'), ['opening_float' => '100.50'])
            ->assertRedirect(route('relatorios.caixa'));

        $this->assertDatabaseHas('cash_register_sessions', [
            'store_id' => $fx['store']->id,
            'status' => CashRegisterSession::STATUS_OPEN,
            'opening_float_cents' => 10050,
        ]);

        $session = CashRegisterSession::query()
            ->where('store_id', $fx['store']->id)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->first();

        $this->assertNotNull($session);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $session->getMorphClass(),
            'subject_id' => $session->id,
            'event' => 'caixa_aberta',
            'description' => 'Caixa aberta',
            'causer_id' => $fx['staff']->id,
            'store_id' => $fx['store']->id,
        ]);
    }

    public function test_second_open_on_same_store_fails(): void
    {
        $fx = $this->fixture();
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 50);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('caixa.open'), ['opening_float' => '20'])
            ->assertSessionHasErrors('opening_float');
    }

    public function test_checkout_without_open_session_returns_422(): void
    {
        $fx = $this->fixture();
        $esi = $fx['event']->eventServiceItems()->first();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [[
                    'tipo' => 'servico',
                    'calendar_event_service_id' => $esi->id,
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 40,
                    'subtotal' => 40,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Abra o dia na caixa antes de cobrar.');
    }

    public function test_checkout_with_open_session_succeeds(): void
    {
        $fx = $this->fixture();
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        $esi = $fx['event']->eventServiceItems()->first();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [[
                    'tipo' => 'servico',
                    'calendar_event_service_id' => $esi->id,
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 40,
                    'subtotal' => 40,
                ]],
            ])
            ->assertOk();

        $session = CashRegisterSession::query()
            ->where('store_id', $fx['store']->id)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->first();

        $this->assertDatabaseHas('sales', [
            'calendar_event_id' => $fx['event']->id,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'total' => 40,
            'cash_register_session_id' => $session?->id,
        ]);
    }

    public function test_open_assigns_booking_orphans_after_previous_close(): void
    {
        $fx = $this->fixture();
        $first = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        app(CashRegisterService::class)->closeSession($first, $fx['staff'], 0);
        $closedAt = $first->fresh()->closed_at;

        $orphan = Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-100',
            'data_emissao' => now()->toDateString(),
            'total' => 15,
            'valor_pago' => 15,
            'payment_method' => Sale::PAYMENT_CARTAO,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'created_at' => $closedAt?->copy()->addHour(),
            'updated_at' => $closedAt?->copy()->addHour(),
        ]);

        $second = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);

        $orphan->refresh();
        $this->assertSame((int) $second->id, (int) $orphan->cash_register_session_id);
    }

    public function test_first_open_does_not_assign_pre_existing_booking_sales(): void
    {
        $fx = $this->fixture();

        $old = Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-099',
            'data_emissao' => now()->toDateString(),
            'total' => 20,
            'valor_pago' => 20,
            'payment_method' => Sale::PAYMENT_MBWAY,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);

        $old->refresh();
        $this->assertNull($old->cash_register_session_id);
    }

    public function test_close_summary_includes_orphan_booking_sale_assigned_on_open(): void
    {
        $fx = $this->fixture();
        $first = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        app(CashRegisterService::class)->closeSession($first, $fx['staff'], 0);

        Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-101',
            'data_emissao' => now()->toDateString(),
            'total' => 25,
            'valor_pago' => 25,
            'payment_method' => Sale::PAYMENT_MBWAY,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('caixa.close.summary'))
            ->assertOk()
            ->assertJsonPath('summary.by_method.mbway', 25)
            ->assertJsonPath('summary.booking_prepayments.count', 1)
            ->assertJsonPath('summary.booking_prepayments.total', 25);
    }

    public function test_orphan_still_pending_after_second_close_if_never_assigned(): void
    {
        $fx = $this->fixture();
        $first = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        app(CashRegisterService::class)->closeSession($first, $fx['staff'], 0);

        $orphan = Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-102',
            'data_emissao' => now()->toDateString(),
            'total' => 12,
            'valor_pago' => 12,
            'payment_method' => Sale::PAYMENT_CARTAO,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $second = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        $orphan->update(['cash_register_session_id' => null]);
        app(CashRegisterService::class)->closeSession($second, $fx['staff'], 0);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('caixa.open.preview'))
            ->assertOk()
            ->assertJsonPath('pending_booking.count', 1);

        $orphan->refresh();
        $this->assertNull($orphan->cash_register_session_id);
    }

    public function test_close_summary_returns_json_when_session_open(): void
    {
        $fx = $this->fixture();
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 25);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('caixa.close.summary'))
            ->assertOk()
            ->assertJsonPath('session.opening_float', 25)
            ->assertJsonStructure([
                'summary' => ['methods', 'expected_cash_in_drawer'],
                'unpaid_marcacoes' => ['count', 'total_due', 'rows'],
            ]);
    }

    public function test_close_summary_lists_unpaid_marcacoes_for_today(): void
    {
        $fx = $this->fixture();
        $fx['event']->update([
            'start_at' => now()->setHour(10),
            'end_at' => now()->setHour(10)->addMinutes(30),
            'status' => CalendarEvent::STATUS_TERMINADO,
        ]);
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 25);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('caixa.close.summary'))
            ->assertOk()
            ->assertJsonPath('unpaid_marcacoes.count', 1)
            ->assertJsonPath('unpaid_marcacoes.total_due', 40)
            ->assertJsonPath('unpaid_marcacoes.rows.0.client_name', 'Cliente Caixa')
            ->assertJsonPath('unpaid_marcacoes.rows.0.pending_invoice', false);
    }

    public function test_close_blocked_when_unpaid_marcacoes_today(): void
    {
        $fx = $this->fixture();
        $fx['event']->update([
            'start_at' => now()->setHour(10),
            'end_at' => now()->setHour(10)->addMinutes(30),
            'status' => CalendarEvent::STATUS_TERMINADO,
        ]);
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 25);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('caixa.close.store'), [
                'counted_cash' => '25.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Não é possível fechar a caixa: existe 1 marcação de hoje por liquidar ou faturar.');

        $this->assertDatabaseHas('cash_register_sessions', [
            'store_id' => $fx['store']->id,
            'status' => CashRegisterSession::STATUS_OPEN,
        ]);
    }

    public function test_close_calculates_cash_difference(): void
    {
        $fx = $this->fixture();
        $session = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 100);
        $esi = $fx['event']->eventServiceItems()->first();

        Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-001',
            'data_emissao' => now()->toDateString(),
            'total' => 40,
            'valor_pago' => 40,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('caixa.close.store'), [
                'counted_cash' => '135.00',
                'notes' => 'Teste fecho',
            ])
            ->assertRedirect(route('relatorios.caixa'));

        $session->refresh();
        $this->assertSame(CashRegisterSession::STATUS_CLOSED, $session->status);
        $this->assertSame(13500, (int) $session->closing_cash_counted_cents);
        $this->assertEquals(140.0, (float) ($session->closing_summary['expected_cash_in_drawer'] ?? 0));
        $this->assertEquals(-5.0, (float) ($session->closing_summary['cash_difference'] ?? 0));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $session->getMorphClass(),
            'subject_id' => $session->id,
            'event' => 'caixa_fechada',
            'description' => 'Caixa fechada',
            'causer_id' => $fx['staff']->id,
            'store_id' => $fx['store']->id,
        ]);
    }

    public function test_close_excludes_draft_sales_from_expected_cash(): void
    {
        $fx = $this->fixture();
        $session = $this->openCashRegisterForStore($fx['staff'], $fx['store'], 50);

        Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-201',
            'data_emissao' => now()->toDateString(),
            'total' => 25,
            'valor_pago' => 25,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_FATURADO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sale::query()->create([
            'store_id' => $fx['store']->id,
            'calendar_event_id' => $fx['event']->id,
            'client_id' => $fx['client']->id,
            'numero_fatura' => '2026/06-202',
            'data_emissao' => now()->toDateString(),
            'total' => 25,
            'valor_pago' => 25,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_RASCUNHO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(CashRegisterService::class)->buildExpectedSummary($session);

        $this->assertEquals(25.0, $summary['cash_sales_total']);
        $this->assertEquals(75.0, $summary['expected_cash_in_drawer']);
        $this->assertSame(1, $summary['sales_count']);
    }

    public function test_checkout_blocked_again_after_close(): void
    {
        $fx = $this->fixture();
        $this->openCashRegisterForStore($fx['staff'], $fx['store'], 0);
        app(CashRegisterService::class)->closeSession(
            app(CashRegisterService::class)->getOpenSession((int) $fx['store']->id),
            $fx['staff'],
            0,
        );
        $esi = $fx['event']->eventServiceItems()->first();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [[
                    'tipo' => 'servico',
                    'calendar_event_service_id' => $esi->id,
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 40,
                    'subtotal' => 40,
                ]],
            ])
            ->assertStatus(422);
    }
}
