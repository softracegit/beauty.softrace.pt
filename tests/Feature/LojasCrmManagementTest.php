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

class LojasCrmManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{org: Organization, store: Store, admin: User}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Fada',
            'slug' => 'fada',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Aveiro',
            'slug' => 'aveiro',
            'timezone' => 'Europe/Lisbon',
        ]);
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@fada.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id,
            'store_id' => $store->id,
            'name' => 'Admin',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$store->id]);

        return compact('org', 'store', 'admin');
    }

    public function test_admin_can_list_and_create_store(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('lojas.index'))
            ->assertOk()
            ->assertSee('Aveiro')
            ->assertDontSee('lojas.duplicate-form', false);

        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('lojas.store'), [
                'name' => 'Forca',
                'slug' => 'forca',
                'address_line' => 'Rua A 1',
                'city' => 'Forca',
                'postal_code' => '1000-001',
                'maps_url' => 'https://maps.google.com/?q=Forca',
            ])
            ->assertRedirect(route('lojas.index'));

        $this->assertDatabaseHas('stores', [
            'organization_id' => $fx['org']->id,
            'slug' => 'forca',
            'name' => 'Forca',
            'address_line' => 'Rua A 1',
            'phone' => null,
        ]);
    }

    public function test_rececao_cannot_access_lojas(): void
    {
        $fx = $this->fixture();
        $rececao = User::query()->create([
            'name' => 'Rececao',
            'email' => 'rec@fada.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $fx['org']->id,
        ]);
        Agent::query()->create([
            'user_id' => $rececao->id,
            'store_id' => $fx['store']->id,
            'name' => 'Rececao',
            'status' => Agent::STATUS_ACTIVE,
        ]);

        $this->actingAs($rececao)
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('lojas.index'))
            ->assertRedirect();
    }
}
