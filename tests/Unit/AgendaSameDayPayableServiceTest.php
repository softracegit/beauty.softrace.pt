<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Services\AgendaSameDayPayableService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSameDayPayableServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'UTC',
            'booking.business_timezone' => 'Europe/Lisbon',
        ]);
    }

    public function test_unpaid_marcacoes_start_time_uses_store_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 08:00:00', 'UTC'));

        $org = Organization::query()->create([
            'name' => 'Org TZ',
            'slug' => 'org-tz',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja TZ',
            'slug' => 'loja-tz',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente TZ',
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

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_TERMINADO,
            'title' => 'Marcação',
            'start_at' => Carbon::parse('2026-06-18 09:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-06-18 09:30:00', 'UTC'),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 40,
            'sort_order' => 0,
        ]);

        $result = app(AgendaSameDayPayableService::class)->unpaidMarcacoesTodayForStore($store->id);

        $this->assertSame(1, $result['count']);
        $this->assertSame('10:00', $result['rows'][0]['start_time']);
    }

    public function test_unpaid_marcacoes_ignores_consolidated_secondary_event_paid_with_sibling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 08:00:00', 'UTC'));

        $org = Organization::query()->create([
            'name' => 'Org Consolidado',
            'slug' => 'org-consolidado',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Consolidado',
            'slug' => 'loja-consolidado',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Tamiris da',
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
            'name' => 'Extensão de Pestanas Fio a Fio Clasicos',
            'duration' => 90,
            'price' => 45,
            'online_price' => 45,
            'sort_order' => 1,
        ]);

        $primaryEvent = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação 1',
            'start_at' => Carbon::parse('2026-06-18 12:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-06-18 13:30:00', 'UTC'),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $primaryEvent->id,
            'service_id' => $service->id,
            'duration' => 90,
            'price' => 45,
            'sort_order' => 0,
        ]);

        $secondaryEvent = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação 2',
            'start_at' => Carbon::parse('2026-06-18 13:15:00', 'UTC'),
            'end_at' => Carbon::parse('2026-06-18 14:45:00', 'UTC'),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $secondaryEvent->id,
            'service_id' => $service->id,
            'duration' => 90,
            'price' => 45,
            'sort_order' => 0,
        ]);

        $sale = \App\Models\Sale::query()->create([
            'store_id' => $store->id,
            'calendar_event_id' => $primaryEvent->id,
            'client_id' => $client->id,
            'numero_fatura' => '2026/06-900',
            'data_emissao' => '2026-06-18',
            'total' => 90,
            'valor_pago' => 90,
            'payment_method' => \App\Models\Sale::PAYMENT_CARTAO,
            'scope' => \App\Models\Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => \App\Models\Sale::STATUS_PAGO,
            'invoice_status' => \App\Models\Sale::INVOICE_STATUS_FATURADO,
        ]);

        \Illuminate\Support\Facades\DB::table('sale_calendar_events')->insert([
            [
                'sale_id' => $sale->id,
                'calendar_event_id' => $primaryEvent->id,
                'amount_settled_cents' => 4500,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_id' => $sale->id,
                'calendar_event_id' => $secondaryEvent->id,
                'amount_settled_cents' => 4500,
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = app(AgendaSameDayPayableService::class)->unpaidMarcacoesTodayForStore($store->id);

        $this->assertSame(0, $result['count'], 'Marcações liquidadas em pagamento conjunto não devem bloquear o fecho da caixa.');
        $this->assertSame([], $result['rows']);
    }
}
