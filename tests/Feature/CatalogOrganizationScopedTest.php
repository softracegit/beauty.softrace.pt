<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContextPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogOrganizationScopedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   org: Organization,
     *   storeA: Store,
     *   storeB: Store,
     *   admin: User,
     *   agentA: Agent,
     *   agentB: Agent,
     *   category: Category,
     *   service: Service
     * }
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Catalog',
            'slug' => 'org-catalog',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja A',
            'slug' => 'loja-a-catalog',
            'timezone' => 'Europe/Lisbon',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja B',
            'slug' => 'loja-b-catalog',
            'timezone' => 'Europe/Lisbon',
        ]);
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-catalog@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id,
            'store_id' => $storeA->id,
            'name' => 'Admin A',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $techAUser = User::query()->create([
            'name' => 'Tech A',
            'email' => 'techa-catalog@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agentA = Agent::query()->create([
            'user_id' => $techAUser->id,
            'store_id' => $storeA->id,
            'name' => 'Tech A',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $techBUser = User::query()->create([
            'name' => 'Tech B',
            'email' => 'techb-catalog@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agentB = Agent::query()->create([
            'user_id' => $techBUser->id,
            'store_id' => $storeB->id,
            'name' => 'Tech B',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$storeA->id, $storeB->id]);
        $techAUser->stores()->sync([$storeA->id]);
        $techBUser->stores()->sync([$storeB->id]);

        $category = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Unhas',
            'sort_order' => 1,
            'hidden_from_booking' => false,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'category_id' => $category->id,
            'name' => 'Manicure',
            'duration' => 45,
            'price' => 25,
            'online_price' => 25,
            'sort_order' => 1,
            'hidden_from_booking' => false,
        ]);

        return compact('org', 'storeA', 'storeB', 'admin', 'agentA', 'agentB', 'category', 'service');
    }

    public function test_booking_lists_only_services_with_agent_in_that_store(): void
    {
        $fx = $this->fixture();
        $fx['service']->agents()->sync([$fx['agentA']->id]);

        $this->get(route('booking.index', $fx['storeA']->slug))
            ->assertOk()
            ->assertSee('Manicure');

        $this->get(route('booking.index', $fx['storeB']->slug))
            ->assertOk()
            ->assertDontSee('Manicure');
    }

    public function test_service_crud_is_organization_wide(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['storeA']->id])
            ->postJson(route('services.store'), [
                'category_id' => $fx['category']->id,
                'name' => 'Pedicure',
                'duration' => 60,
                'price' => 30,
                'online_price' => 30,
                'agent_ids' => [$fx['agentA']->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $created = Service::query()->where('name', 'Pedicure')->first();
        $this->assertNotNull($created);
        $this->assertSame((int) $fx['org']->id, (int) $created->organization_id);

        // Visível no CRUD a partir da outra loja (mesmo catálogo org).
        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['storeB']->id])
            ->getJson(route('services.allGrouped'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pedicure']);
    }

    public function test_sync_tecnicos_only_updates_agents_of_selected_store(): void
    {
        $fx = $this->fixture();
        $fx['service']->agents()->sync([$fx['agentA']->id]);

        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['storeB']->id])
            ->post(route('services.tecnicos.sync'), [
                StoreContextPreference::QUERY_STORE => $fx['storeB']->id,
                'assignments' => [
                    $fx['service']->id => [$fx['agentB']->id],
                ],
            ])
            ->assertRedirect();

        $linked = $fx['service']->fresh()->agents()->pluck('agents.id')->map(fn ($id) => (int) $id)->all();
        $this->assertEqualsCanonicalizing(
            [(int) $fx['agentA']->id, (int) $fx['agentB']->id],
            $linked
        );

        $this->get(route('booking.index', $fx['storeB']->slug))
            ->assertOk()
            ->assertSee('Manicure');
    }
}
