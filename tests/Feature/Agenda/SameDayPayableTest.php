<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SameDayPayableTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Same Day',
            'slug' => 'org-same-day',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Same Day',
            'slug' => 'loja-same-day',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Same Day',
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
            'name' => 'Staff Same Day',
            'email' => 'staff-same-day@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Same Day',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'client', 'service', 'staff');
    }

    private function createEvent(Store $store, Client $client, Service $service, string $startAt, string $status = CalendarEvent::STATUS_AGENDADO): CalendarEvent
    {
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => $status,
            'title' => 'Evento',
            'start_at' => $startAt,
            'end_at' => now()->parse($startAt)->addMinutes(30),
        ]);

        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        return $event;
    }

    public function test_same_day_payable_returns_only_unpaid_markings_same_day(): void
    {
        $fx = $this->fixture();
        $day = now()->addDay()->startOfDay();
        $anchor = $this->createEvent($fx['store'], $fx['client'], $fx['service'], $day->copy()->addHours(10)->toDateTimeString());
        $second = $this->createEvent($fx['store'], $fx['client'], $fx['service'], $day->copy()->addHours(15)->toDateTimeString());
        $this->createEvent($fx['store'], $fx['client'], $fx['service'], $day->copy()->addDays(1)->addHours(9)->toDateTimeString());

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $second->id,
                'payment_method' => 'dinheiro',
                'invoice_fiscal_mode' => 'consumer',
                'items' => [[
                    'tipo' => 'servico',
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 30,
                    'subtotal' => 30,
                ]],
            ])
            ->assertOk();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.events.same_day_payable', $anchor))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('rows.0.id', $anchor->id)
            ->assertJsonPath('rows.0.amount_due', 30.0)
            ->assertJsonPath('total_due', 30.0);
    }

    public function test_same_day_payable_returns_empty_when_event_has_no_client(): void
    {
        $fx = $this->fixture();
        $event = CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'client_id' => null,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Sem cliente',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(30),
        ]);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.events.same_day_payable', $event))
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('rows', []);
    }
}
