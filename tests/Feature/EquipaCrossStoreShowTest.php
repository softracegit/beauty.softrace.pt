<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EquipaCrossStoreShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_agent_from_other_store_while_session_is_on_home_store(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-equipa',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja A',
            'slug' => 'loja-a-equipa',
            'timezone' => 'Europe/Lisbon',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja B',
            'slug' => 'loja-b-equipa',
            'timezone' => 'Europe/Lisbon',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-equipa@test.test',
            'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id,
            'store_id' => $storeA->id,
            'name' => 'Admin',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$storeA->id, $storeB->id]);

        $memberUser = User::query()->create([
            'name' => 'Novo',
            'email' => 'novo-equipa@test.test',
            'password' => Hash::make('x'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $member = Agent::query()->create([
            'user_id' => $memberUser->id,
            'store_id' => $storeB->id,
            'name' => 'Novo Membro',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $memberUser->stores()->sync([$storeB->id]);

        // Sessão na loja A (como «Todas as lojas» mantém a sessão na home)
        $this->actingAs($admin)
            ->withSession([SetCurrentStore::SESSION_KEY => $storeA->id])
            ->get(route('equipa.show', $member))
            ->assertOk()
            ->assertSee('Novo Membro');
    }
}
