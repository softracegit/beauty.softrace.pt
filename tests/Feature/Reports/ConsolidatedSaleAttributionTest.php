<?php

namespace Tests\Feature\Reports;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\FinancialDashboardService;
use App\Services\VendasReportService;
use App\Support\SaleTechnicianAttribution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsolidatedSaleAttributionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{store: Store, client: Client, techA: User, techB: User, sale: Sale} */
    private function consolidatedFixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Attribution',
            'slug' => 'org-attribution',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-attribution',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Serenela Antunes',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat',
            'sort_order' => 1,
        ]);
        $serviceA = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço A',
            'duration' => 30,
            'price' => 20,
            'online_price' => 20,
            'sort_order' => 1,
        ]);
        $serviceB = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço B',
            'duration' => 30,
            'price' => 15,
            'online_price' => 15,
            'sort_order' => 2,
        ]);

        $techA = User::query()->create([
            'name' => 'Laissa',
            'email' => 'laissa@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        $techB = User::query()->create([
            'name' => 'Sandy',
            'email' => 'sandy@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $techA->id,
            'store_id' => $store->id,
            'name' => 'Laissa',
            'status' => Agent::STATUS_ACTIVE,
            'commission_rate' => 10,
            'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
        ]);
        Agent::query()->create([
            'user_id' => $techB->id,
            'store_id' => $store->id,
            'name' => 'Sandy',
            'status' => Agent::STATUS_ACTIVE,
            'commission_rate' => 10,
            'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
        ]);

        $day = Carbon::parse('2026-06-22 10:00:00');
        $eventA = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $techA->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação A',
            'start_at' => $day,
            'end_at' => $day->copy()->addMinutes(30),
        ]);
        $eventB = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $techB->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação B',
            'start_at' => $day->copy()->addHours(5),
            'end_at' => $day->copy()->addHours(5)->addMinutes(30),
        ]);
        $esiA = CalendarEventService::query()->create([
            'calendar_event_id' => $eventA->id,
            'service_id' => $serviceA->id,
            'duration' => 30,
            'price' => 20,
            'sort_order' => 0,
        ]);
        $esiB = CalendarEventService::query()->create([
            'calendar_event_id' => $eventB->id,
            'service_id' => $serviceB->id,
            'duration' => 30,
            'price' => 15,
            'sort_order' => 0,
        ]);

        $sale = Sale::query()->create([
            'store_id' => $store->id,
            'calendar_event_id' => $eventA->id,
            'client_id' => $client->id,
            'numero_fatura' => 'FR CONS/1',
            'data_emissao' => '2026-06-22',
            'total' => 35,
            'valor_pago' => 35,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_RASCUNHO,
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'tipo' => SaleItem::TIPO_SERVICO,
            'calendar_event_service_id' => $esiA->id,
            'service_id' => $serviceA->id,
            'descricao' => '10:00 - Serviço A',
            'quantidade' => 1,
            'preco_unitario' => 20,
            'subtotal' => 20,
            'sort_order' => 0,
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'tipo' => SaleItem::TIPO_SERVICO,
            'calendar_event_service_id' => $esiB->id,
            'service_id' => $serviceB->id,
            'descricao' => '15:00 - Serviço B',
            'quantidade' => 1,
            'preco_unitario' => 15,
            'subtotal' => 15,
            'sort_order' => 1,
        ]);

        return compact('store', 'client', 'techA', 'techB', 'sale');
    }

    public function test_consolidated_sale_splits_report_lines_by_technician(): void
    {
        $fx = $this->consolidatedFixture();
        $sale = $fx['sale']->load(['items.calendarEventService.event.user', 'calendarEvent.user']);

        $slices = SaleTechnicianAttribution::slicesForSale($sale);
        $this->assertCount(2, $slices);
        $this->assertSame(20.0, $slices[0]['valor']);
        $this->assertSame(15.0, $slices[1]['valor']);

        $lines = app(VendasReportService::class)->resumoCollection(collect([$sale]), null);
        $this->assertCount(2, $lines);
        $this->assertSame('Laissa', $lines[0]->tecnico);
        $this->assertSame('Sandy', $lines[1]->tecnico);
        $this->assertSame(20.0, (float) $lines[0]->valor);
        $this->assertSame(15.0, (float) $lines[1]->valor);
        $this->assertSame('FR CONS/1', $lines[0]->numero_fatura);
        $this->assertSame('FR CONS/1', $lines[1]->numero_fatura);
    }

    public function test_commission_estimate_splits_consolidated_sale(): void
    {
        $fx = $this->consolidatedFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $dashboard = app(FinancialDashboardService::class)->build(
            (int) $fx['store']->id,
            2026,
            6
        );

        $byName = collect($dashboard['comissoes_por_tecnica'])->keyBy('nome');
        $this->assertSame(20.0, (float) $byName['Laissa']->receita);
        $this->assertSame(15.0, (float) $byName['Sandy']->receita);
        $this->assertSame(2.0, (float) $byName['Laissa']->comissao);
        $this->assertSame(1.5, (float) $byName['Sandy']->comissao);
    }

    public function test_consolidated_sale_resolves_technician_when_calendar_event_service_id_missing(): void
    {
        $fx = $this->consolidatedFixture();
        $sale = $fx['sale']->load(['items.calendarEventService.event.user', 'calendarEvent.user', 'settledEvents']);

        SaleItem::query()
            ->where('sale_id', $sale->id)
            ->orderByDesc('sort_order')
            ->first()
            ?->update(['calendar_event_service_id' => null]);

        $sale->refresh()->load(['items.calendarEventService.event.user', 'calendarEvent.user', 'settledEvents']);

        DB::table('sale_calendar_events')->insert([
            'sale_id' => $sale->id,
            'calendar_event_id' => CalendarEvent::query()
                ->where('user_id', $fx['techB']->id)
                ->value('id'),
            'amount_settled_cents' => 1500,
            'is_primary' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sale->load('settledEvents');

        $lines = app(VendasReportService::class)->resumoCollection(collect([$sale->fresh([
            'items.calendarEventService.event.user',
            'calendarEvent.user',
            'settledEvents',
        ])]), null);

        $this->assertCount(2, $lines);
        $this->assertSame('Sandy', $lines[1]->tecnico);
        $this->assertSame(15.0, (float) $lines[1]->valor);
    }
}
