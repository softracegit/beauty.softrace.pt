<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\PersonalTimeType;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\PrestadorDashboardService;
use Carbon\Carbon;
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

    public function test_dashboard_metrics_exclude_personal_time(): void
    {
        $fx = $this->fixture();
        $today = now()->startOfDay()->addHours(11);

        $personalType = PersonalTimeType::query()->create([
            'store_id' => $fx['store']->id,
            'name' => 'Almoço',
            'icon' => 'ph-clock',
            'duration' => 60,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'user_id' => $fx['prestador']->id,
            'event_type' => CalendarEvent::TYPE_TEMPO_PESSOAL,
            'personal_time_type_id' => $personalType->id,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Almoço',
            'start_at' => $today,
            'end_at' => $today->copy()->addHour(),
        ]);

        $service = app(PrestadorDashboardService::class);
        $prestadorMetrics = $service->build($fx['prestador'], $fx['store']->id);
        $storeMetrics = $service->buildForStore($fx['store']->id);

        $this->assertSame(1, $prestadorMetrics['marcacoesHoje']);
        $this->assertSame(1, $prestadorMetrics['marcacoesEsteMes']);
        $this->assertSame(1, $storeMetrics['marcacoesHoje']);
        $this->assertSame(1, $storeMetrics['marcacoesMesPorRealizar']);
    }

    public function test_dashboard_metrics_exclude_cancelled_appointments(): void
    {
        $fx = $this->fixture();
        $today = now()->startOfDay()->addHours(12);

        CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'client_id' => $fx['client']->id,
            'user_id' => $fx['prestador']->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_CANCELADO,
            'title' => 'Marcação cancelada',
            'start_at' => $today,
            'end_at' => $today->copy()->addMinutes(30),
        ]);

        $service = app(PrestadorDashboardService::class);
        $metrics = $service->build($fx['prestador'], $fx['store']->id);

        $this->assertSame(1, $metrics['marcacoesHoje']);
        $this->assertSame(1, $metrics['marcacoesEsteMes']);
        $this->assertSame(1, $metrics['marcacoesEstaSemana']);
    }

    public function test_completed_today_counts_past_appointments_regardless_of_status(): void
    {
        Carbon::setTestNow(now()->startOfDay()->addHours(18)->addMinutes(48));

        try {
            $fx = $this->fixture();

            $futureStart = now()->startOfDay()->addHours(20);
            CalendarEvent::query()->create([
                'store_id' => $fx['store']->id,
                'client_id' => $fx['client']->id,
                'user_id' => $fx['prestador']->id,
                'event_type' => CalendarEvent::TYPE_MARCACAO,
                'status' => CalendarEvent::STATUS_CONFIRMADO,
                'title' => 'Marcação futura',
                'start_at' => $futureStart,
                'end_at' => $futureStart->copy()->addMinutes(30),
            ]);

            $service = app(PrestadorDashboardService::class);
            $metrics = $service->build($fx['prestador'], $fx['store']->id);

            $this->assertSame(2, $metrics['marcacoesHoje']);
            $this->assertSame(1, $metrics['marcacoesConcluidasHoje']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rececao_dashboard_has_collapsed_sidebar_panel_by_default(): void
    {
        $fx = $this->fixture();
        $org = $fx['store']->organization;

        $rececao = User::query()->create([
            'name' => 'Receção Sidebar',
            'email' => 'rececao-sidebar@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        $rececao->stores()->sync([$fx['store']->id]);

        $this->actingAs($rececao)
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('body class="sidebar-panel-collapsed', false);
    }
}
