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
}
