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
        Agent::query()->create([
            'user_id' => $tech->id,
            'store_id' => $store->id,
            'name' => 'Laissa Osto',
            'status' => Agent::STATUS_ACTIVE,
            'commission_rate' => 70,
            'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
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
}
