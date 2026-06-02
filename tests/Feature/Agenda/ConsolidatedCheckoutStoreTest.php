<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsolidatedCheckoutStoreTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Consolidated',
            'slug' => 'org-consolidated',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Consolidated',
            'slug' => 'loja-consolidated',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Consolidated',
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
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff Consolidated',
            'email' => 'staff-consolidated@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Consolidated',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'client', 'service', 'staff');
    }

    private function makeEvent(Store $store, Client $client, Service $service, string $startAt): CalendarEvent
    {
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Evento',
            'start_at' => $startAt,
            'end_at' => now()->parse($startAt)->addMinutes(30),
        ]);
        $eventService = CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        return $event->setRelation('eventServiceItem', $eventService);
    }

    public function test_store_keeps_single_checkout_compatible(): void
    {
        $fx = $this->fixture();
        $event = $this->makeEvent($fx['store'], $fx['client'], $fx['service'], now()->addDay()->setHour(10)->toDateTimeString());

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $event->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [[
                    'tipo' => 'servico',
                    'calendar_event_service_id' => $event->eventServiceItems()->first()->id,
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 30,
                    'subtotal' => 30,
                ]],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('sales', ['calendar_event_id' => $event->id, 'total' => 30.0]);
        $this->assertDatabaseHas('sale_calendar_events', ['calendar_event_id' => $event->id, 'is_primary' => 1]);
    }

    public function test_store_supports_consolidated_checkout_and_marks_all_events_complete(): void
    {
        $fx = $this->fixture();
        $day = now()->addDay()->startOfDay();
        $eventA = $this->makeEvent($fx['store'], $fx['client'], $fx['service'], $day->copy()->addHours(10)->toDateTimeString());
        $eventB = $this->makeEvent($fx['store'], $fx['client'], $fx['service'], $day->copy()->addHours(15)->toDateTimeString());
        $esiA = $eventA->eventServiceItems()->first();
        $esiB = $eventB->eventServiceItems()->first();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $eventA->id,
                'event_ids' => [$eventA->id, $eventB->id],
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'items' => [
                    [
                        'tipo' => 'servico',
                        'calendar_event_service_id' => $esiA->id,
                        'descricao' => '10:00 - Serviço',
                        'quantidade' => 1,
                        'preco_unitario' => 30,
                        'subtotal' => 30,
                    ],
                    [
                        'tipo' => 'servico',
                        'calendar_event_service_id' => $esiB->id,
                        'descricao' => '15:00 - Serviço',
                        'quantidade' => 1,
                        'preco_unitario' => 30,
                        'subtotal' => 30,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('settled_event_ids.0', $eventA->id)
            ->assertJsonPath('settled_event_ids.1', $eventB->id);

        $sale = Sale::query()->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame((int) $eventA->id, (int) $sale->calendar_event_id);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 60.0]);
        $this->assertDatabaseHas('sale_calendar_events', ['sale_id' => $sale->id, 'calendar_event_id' => $eventA->id, 'is_primary' => 1]);
        $this->assertDatabaseHas('sale_calendar_events', ['sale_id' => $sale->id, 'calendar_event_id' => $eventB->id, 'is_primary' => 0]);
        $this->assertDatabaseHas('calendar_events', ['id' => $eventA->id, 'status' => CalendarEvent::STATUS_COMPLETO]);
        $this->assertDatabaseHas('calendar_events', ['id' => $eventB->id, 'status' => CalendarEvent::STATUS_COMPLETO]);
    }
}
