<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrestadorDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, prestador: User, client: Client}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Prestador Dash',
            'slug' => 'org-prestador-dash',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-prestador-dash',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Dash',
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
            'name' => 'Manicure',
            'duration' => 30,
            'price' => 25,
            'online_price' => 25,
            'sort_order' => 1,
        ]);
        $prestador = User::query()->create([
            'name' => 'Técnica Dash',
            'email' => 'tecnica-dash@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $prestador->id,
            'store_id' => $store->id,
            'name' => 'Técnica Dash',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $prestador->stores()->sync([$store->id]);

        $today = now()->startOfDay()->addHours(10);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $prestador->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_CONFIRMADO,
            'title' => 'Marcação dash',
            'start_at' => $today,
            'end_at' => $today->copy()->addMinutes(30),
        ]);
        \App\Models\CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 25,
            'sort_order' => 0,
        ]);

        return compact('store', 'prestador', 'client');
    }

    public function test_prestador_can_open_personal_dashboard(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['prestador'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Marcações hoje')
            ->assertSee('Resumo do dia')
            ->assertSee('Cliente Dash');
    }

    public function test_prestador_is_redirected_from_admin_dashboard_sections(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['prestador'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('dashboard.marcacoes'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_rececao_sees_store_wide_dashboard(): void
    {
        $fx = $this->fixture();
        $org = $fx['store']->organization;

        $rececao = User::query()->create([
            'name' => 'Receção Dash',
            'email' => 'rececao-dash@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        $rececao->stores()->sync([$fx['store']->id]);

        $this->actingAs($rececao)
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Resumo da loja')
            ->assertSee('Marcações hoje')
            ->assertSee('Cliente Dash')
            ->assertSee('Técnica Dash');
    }

    public function test_rececao_is_redirected_from_admin_dashboard_sections(): void
    {
        $fx = $this->fixture();
        $org = $fx['store']->organization;

        $rececao = User::query()->create([
            'name' => 'Receção Dash',
            'email' => 'rececao-dash-redirect@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        $rececao->stores()->sync([$fx['store']->id]);

        $this->actingAs($rececao)
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('dashboard.financeiro'))
            ->assertRedirect(route('dashboard'));
    }
}
