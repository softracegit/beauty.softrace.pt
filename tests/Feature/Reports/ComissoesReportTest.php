<?php

namespace Tests\Feature\Reports;

use App\Models\Agent;
use App\Models\AgentCommissionPeriod;
use App\Models\AgentRetentionFeePeriod;
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
use App\Services\ComissoesReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComissoesReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{store: Store, sale: Sale, tech: User} */
    private function singleSaleFixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Comissões',
            'slug' => 'org-comissoes',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-comissoes',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Clara Martins',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Remoção de Gel / Acrílico',
            'duration' => 30,
            'price' => 10,
            'online_price' => 10,
            'sort_order' => 1,
        ]);

        $tech = User::query()->create([
            'name' => 'Laissa Osto',
            'email' => 'laissa-com@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $tech->id,
            'store_id' => $store->id,
            'name' => 'Laissa Osto',
            'status' => Agent::STATUS_ACTIVE,
            'commission_rate' => 70,
            'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
            'commission_effective_from' => '2026-01-01',
        ]);
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 70,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-01-01',
        ]);

        $day = Carbon::parse('2026-06-20 21:45:00');
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $tech->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação',
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
        ]);
        $esi = CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 10,
            'sort_order' => 0,
        ]);

        $sale = Sale::query()->create([
            'store_id' => $store->id,
            'calendar_event_id' => $event->id,
            'client_id' => $client->id,
            'numero_fatura' => 'FR CAIXA1/38/3',
            'data_emissao' => $day,
            'total' => 10,
            'iva_total' => 1.87,
            'valor_pago' => 10,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_FATURADO,
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'tipo' => SaleItem::TIPO_SERVICO,
            'calendar_event_service_id' => $esi->id,
            'service_id' => $service->id,
            'descricao' => 'Remoção de Gel / Acrílico',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'subtotal' => 10,
            'sort_order' => 0,
        ]);

        return compact('store', 'sale', 'tech');
    }

    public function test_commission_line_matches_percent_after_discount(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $sale = $fx['sale']->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);

        $service = app(ComissoesReportService::class);
        $lines = $service->linesCollection(collect([$sale]));

        $this->assertCount(1, $lines);
        $line = $lines->first();
        $this->assertSame('Laissa Osto', $line->tecnico);
        $this->assertSame('Clara Martins', $line->cliente);
        $this->assertSame(10.0, (float) $line->valor_com_iva);
        $this->assertSame(7.0, (float) $line->comissao_com_iva);
        $this->assertSame('70,00 %', $line->comissao_taxa);
        $this->assertLessThan(10.0, (float) $line->valor_sem_iva);
        $this->assertLessThan(7.0, (float) $line->comissao_sem_iva);
    }

    public function test_commission_report_filters_by_marcacao_period(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $service = app(ComissoesReportService::class);
        $sales = $service->salesForReport([
            'desde' => '2026-06-01',
            'ate' => '2026-06-30',
        ]);

        $this->assertCount(1, $sales);

        $salesOut = $service->salesForReport([
            'desde' => '2026-07-01',
            'ate' => '2026-07-31',
        ]);

        $this->assertCount(0, $salesOut);
    }

    public function test_commission_applies_euro_retention_after_percent(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $agent = Agent::query()->where('user_id', $fx['tech']->id)->firstOrFail();
        AgentCommissionPeriod::query()->where('agent_id', $agent->id)->delete();
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 60,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-01-01',
        ]);
        $agent->refreshCommissionCache();
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 1.5,
            'unit' => Agent::COMMISSION_UNIT_EURO,
            'effective_from' => '2026-06-01',
        ]);
        $agent->refreshRetentionFeeCache();

        // 27€ c/ IVA → ~21,95€ s/ IVA; retenção 1,50€ depois da % (diferença exacta de 1,50€).
        $fx['sale']->update(['total' => 27, 'iva_total' => 5.05, 'valor_pago' => 27]);
        $fx['sale']->items()->update([
            'preco_unitario' => 27,
            'subtotal' => 27,
        ]);

        $sale = $fx['sale']->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);

        $lines = app(ComissoesReportService::class)->linesCollection(collect([$sale]));
        $line = $lines->first();

        $this->assertSame(27.0, (float) $line->valor_com_iva);
        $this->assertSame(14.7, (float) $line->comissao_com_iva); // 60% de 27 − 1,50
        $this->assertEqualsWithDelta(21.95, (float) $line->valor_sem_iva, 0.01);
        $this->assertEqualsWithDelta(11.67, (float) $line->comissao_sem_iva, 0.01); // 60% de 21,95 − 1,50
        $this->assertSame('1,50 €', $line->retencao_taxa);

        // Antes da data de início: sem retenção.
        AgentRetentionFeePeriod::query()->where('agent_id', $agent->id)->delete();
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 1.5,
            'unit' => Agent::COMMISSION_UNIT_EURO,
            'effective_from' => '2026-07-01',
        ]);
        $agent->refreshRetentionFeeCache();

        $linesBefore = app(ComissoesReportService::class)->linesCollection(collect([$sale]));
        $this->assertNull($linesBefore->first()->retencao_taxa);
        $this->assertSame(16.2, (float) $linesBefore->first()->comissao_com_iva); // 60% de 27
    }

    public function test_retention_fee_history_uses_rate_in_force_on_sale_date(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $agent = Agent::query()->where('user_id', $fx['tech']->id)->firstOrFail();
        AgentCommissionPeriod::query()->where('agent_id', $agent->id)->delete();
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 60,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-01-01',
        ]);
        $agent->refreshCommissionCache();
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 1.5,
            'unit' => Agent::COMMISSION_UNIT_EURO,
            'effective_from' => '2026-06-01',
        ]);
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 2.0,
            'unit' => Agent::COMMISSION_UNIT_EURO,
            'effective_from' => '2026-11-01',
        ]);
        $agent->refreshRetentionFeeCache();

        $fx['sale']->update([
            'total' => 27,
            'iva_total' => 5.05,
            'valor_pago' => 27,
            'data_emissao' => '2026-10-15',
        ]);
        $fx['sale']->items()->update([
            'preco_unitario' => 27,
            'subtotal' => 27,
        ]);

        $saleOct = $fx['sale']->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);
        $lineOct = app(ComissoesReportService::class)->linesCollection(collect([$saleOct]))->first();
        $this->assertSame('1,50 €', $lineOct->retencao_taxa);
        $this->assertSame(14.7, (float) $lineOct->comissao_com_iva); // 16,20 − 1,50

        $saleNov = $saleOct->replicate();
        $saleNov->numero_fatura = 'FR CAIXA1/38/4';
        $saleNov->data_emissao = Carbon::parse('2026-11-05');
        $saleNov->save();
        foreach ($saleOct->items as $item) {
            $saleNov->items()->create($item->only([
                'tipo',
                'service_id',
                'descricao',
                'quantidade',
                'preco_unitario',
                'subtotal',
                'calendar_event_service_id',
            ]));
        }
        $saleNov = $saleNov->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);

        $lineNov = app(ComissoesReportService::class)->linesCollection(collect([$saleNov]))->first();
        $this->assertSame('2,00 €', $lineNov->retencao_taxa);
        $this->assertSame(14.2, (float) $lineNov->comissao_com_iva); // 16,20 − 2,00
    }

    public function test_retention_fee_cleared_from_date_keeps_earlier_sales(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $agent = Agent::query()->where('user_id', $fx['tech']->id)->firstOrFail();
        AgentCommissionPeriod::query()->where('agent_id', $agent->id)->delete();
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 60,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-01-01',
        ]);
        $agent->refreshCommissionCache();
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 1.5,
            'unit' => Agent::COMMISSION_UNIT_EURO,
            'effective_from' => '2026-06-01',
        ]);
        AgentRetentionFeePeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => null,
            'unit' => null,
            'effective_from' => '2026-10-01',
        ]);
        $agent->refreshRetentionFeeCache();

        $fx['sale']->update([
            'total' => 27,
            'iva_total' => 5.05,
            'valor_pago' => 27,
            'data_emissao' => '2026-06-20',
        ]);
        $fx['sale']->items()->update([
            'preco_unitario' => 27,
            'subtotal' => 27,
        ]);

        $saleJun = $fx['sale']->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);
        $lineJun = app(ComissoesReportService::class)->linesCollection(collect([$saleJun]))->first();
        $this->assertSame('1,50 €', $lineJun->retencao_taxa);
        $this->assertSame(14.7, (float) $lineJun->comissao_com_iva);

        $saleOct = $saleJun->replicate();
        $saleOct->numero_fatura = 'FR CAIXA1/38/5';
        $saleOct->data_emissao = Carbon::parse('2026-10-15');
        $saleOct->save();
        foreach ($saleJun->items as $item) {
            $saleOct->items()->create($item->only([
                'tipo',
                'service_id',
                'descricao',
                'quantidade',
                'preco_unitario',
                'subtotal',
                'calendar_event_service_id',
            ]));
        }
        $saleOct = $saleOct->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);

        $lineOct = app(ComissoesReportService::class)->linesCollection(collect([$saleOct]))->first();
        $this->assertNull($lineOct->retencao_taxa);
        $this->assertSame(16.2, (float) $lineOct->comissao_com_iva);
    }

    public function test_commission_history_uses_rate_in_force_on_sale_date(): void
    {
        $fx = $this->singleSaleFixture();
        session([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $agent = Agent::query()->where('user_id', $fx['tech']->id)->firstOrFail();
        AgentCommissionPeriod::query()->where('agent_id', $agent->id)->delete();
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 50,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-06-01',
        ]);
        AgentCommissionPeriod::query()->create([
            'agent_id' => $agent->id,
            'rate' => 55,
            'unit' => Agent::COMMISSION_UNIT_PERCENT,
            'effective_from' => '2026-11-01',
        ]);
        $agent->refreshCommissionCache();

        $fx['sale']->update([
            'total' => 100,
            'iva_total' => 0,
            'valor_pago' => 100,
            'data_emissao' => '2026-10-15',
        ]);
        $fx['sale']->items()->update([
            'preco_unitario' => 100,
            'subtotal' => 100,
        ]);

        $saleOct = $fx['sale']->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);
        $lineOct = app(ComissoesReportService::class)->linesCollection(collect([$saleOct]))->first();
        $this->assertSame('50,00 %', $lineOct->comissao_taxa);
        $this->assertSame(50.0, (float) $lineOct->comissao_com_iva);

        $saleNov = $saleOct->replicate();
        $saleNov->numero_fatura = 'FR CAIXA1/38/6';
        $saleNov->data_emissao = Carbon::parse('2026-11-05');
        $saleNov->save();
        foreach ($saleOct->items as $item) {
            $saleNov->items()->create($item->only([
                'tipo',
                'service_id',
                'descricao',
                'quantidade',
                'preco_unitario',
                'subtotal',
                'calendar_event_service_id',
            ]));
        }
        $saleNov = $saleNov->fresh()->load([
            'client',
            'items.service',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
        ]);

        $lineNov = app(ComissoesReportService::class)->linesCollection(collect([$saleNov]))->first();
        $this->assertSame('55,00 %', $lineNov->comissao_taxa);
        $this->assertSame(55.0, (float) $lineNov->comissao_com_iva);
    }
}
