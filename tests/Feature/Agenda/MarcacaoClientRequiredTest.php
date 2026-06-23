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

class MarcacaoClientRequiredTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Client Required',
            'slug' => 'org-client-required',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Client Required',
            'slug' => 'loja-client-required',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Obrigatório',
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
            'name' => 'Staff Client Required',
            'email' => 'staff-client-required@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Client Required',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $staff->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Cliente Obrigatório - Serviço',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(30),
            'service_id' => $service->id,
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 30,
            'sort_order' => 0,
        ]);

        return compact('store', 'staff', 'client', 'service', 'event');
    }

    public function test_store_marcacao_requires_client(): void
    {
        $fx = $this->fixture();
        $start = now()->addDays(2)->startOfHour();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.events.store'), [
                'title' => 'Sem cliente - Serviço',
                'start_at' => $start->toIso8601String(),
                'end_at' => $start->copy()->addMinutes(30)->toIso8601String(),
                'event_type' => CalendarEvent::TYPE_MARCACAO,
                'user_id' => $fx['staff']->id,
                'service_id' => $fx['service']->id,
                'services' => [[
                    'service_id' => $fx['service']->id,
                    'duration' => 30,
                    'price' => 30,
                    'original_price' => 30,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_update_marcacao_cannot_clear_client(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->putJson(route('agenda.events.update', $fx['event']), [
                'client_id' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);

        $this->assertSame($fx['client']->id, $fx['event']->fresh()->client_id);
    }
}
