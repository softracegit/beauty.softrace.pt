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

class MarcacaoActivityLogEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Marcacao Log',
            'slug' => 'org-marcacao-log',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Marcacao Log',
            'slug' => 'loja-marcacao-log',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Logs',
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
            'name' => 'Manutenção de Gel',
            'duration' => 30,
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_CONFIRMADO,
            'title' => 'Cliente Logs - Manutenção de Gel',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff Logs',
            'email' => 'staff-marcacao-log@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Logs',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'event');
    }

    public function test_activity_log_endpoint_returns_html_with_marcacao_entries(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id]);

        $fx['event']->update(['status' => CalendarEvent::STATUS_CHEGOU]);

        $response = $this->getJson(route('agenda.events.activity_log', $fx['event']));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, (int) $response->json('count'));

        $body = (string) $response->json('html');
        $this->assertStringContainsString('Marcação criada', $body);
        $this->assertStringContainsString('Cliente Logs', $body);
        $this->assertStringContainsString('activity-log', $body);
        $this->assertStringContainsString('por Staff Logs', $body);
    }

    public function test_activity_log_endpoint_denies_other_store(): void
    {
        $fx = $this->fixture();
        $otherStore = Store::query()->create([
            'organization_id' => $fx['store']->organization_id,
            'name' => 'Outra loja',
            'slug' => 'outra-loja-log',
        ]);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $otherStore->id])
            ->getJson(route('agenda.events.activity_log', $fx['event']))
            ->assertNotFound();
    }
}
