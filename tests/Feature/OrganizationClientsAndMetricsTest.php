<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationClientsAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_are_listed_org_wide(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $storeA = Store::query()->create(['organization_id' => $org->id, 'name' => 'A', 'slug' => 'a']);
        $storeB = Store::query()->create(['organization_id' => $org->id, 'name' => 'B', 'slug' => 'b']);
        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'a@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN, 'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id, 'store_id' => $storeA->id, 'name' => 'Admin', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$storeA->id, $storeB->id]);

        Client::query()->create([
            'organization_id' => $org->id, 'store_id' => $storeB->id, 'name' => 'Cliente B', 'phone' => '+351911111111',
        ]);

        $html = $this->actingAs($admin)
            ->withSession([SetCurrentStore::SESSION_KEY => $storeA->id])
            ->get(route('clientes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cliente B', $html);
    }

    public function test_organizacao_report_aggregates_stores(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'orgm', 'status' => 'active']);
        $storeA = Store::query()->create(['organization_id' => $org->id, 'name' => 'A', 'slug' => 'am', 'timezone' => 'Europe/Lisbon']);
        $storeB = Store::query()->create(['organization_id' => $org->id, 'name' => 'B', 'slug' => 'bm', 'timezone' => 'Europe/Lisbon']);
        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'am@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN, 'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id, 'store_id' => $storeA->id, 'name' => 'Admin', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$storeA->id, $storeB->id]);

        $client = Client::query()->create([
            'organization_id' => $org->id, 'store_id' => $storeA->id, 'name' => 'C', 'phone' => '+351922222222',
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $storeA->id,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'start_at' => now()->utc(),
            'end_at' => now()->utc()->addHour(),
            'title' => 'X',
        ]);
        Sale::query()->create([
            'store_id' => $storeA->id,
            'client_id' => $client->id,
            'calendar_event_id' => $event->id,
            'status' => Sale::STATUS_PAGO,
            'total' => 50,
            'numero_fatura' => 'FT-ORG-1',
            'data_emissao' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->withSession([SetCurrentStore::SESSION_KEY => $storeA->id])
            ->get(route('relatorios.organizacao', [
                'desde' => now()->subDay()->toDateString(),
                'ate' => now()->addDay()->toDateString(),
                'lojas_scope' => 'todas',
            ]))
            ->assertOk()
            ->assertSee('Resumo empresa')
            ->assertSee('Total previsto')
            ->assertSee('Vendas feitas')
            ->assertSee('Por fazer')
            ->assertSee('Ticket médio')
            ->assertSee('Taxa de conclusão')
            ->assertSee('Clientes únicos atendidos')
            ->assertSee('50,00');
    }
}
