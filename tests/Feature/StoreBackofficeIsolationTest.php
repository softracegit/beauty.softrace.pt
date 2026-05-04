<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreBackofficeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_from_other_store_returns_404_when_session_store_differs(): void
    {
        $org = Organization::query()->create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja A',
            'slug' => 'loja-a',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja B',
            'slug' => 'loja-b',
        ]);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $user->id,
            'store_id' => $storeA->id,
            'name' => 'Admin',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $user->stores()->sync([$storeA->id, $storeB->id]);

        $catB = Category::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Cat B',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->withSession([SetCurrentStore::SESSION_KEY => $storeA->id])
            ->get('/categories/'.$catB->id)
            ->assertNotFound();
    }

    public function test_public_booking_service_from_wrong_store_slug_returns_404(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org 2',
            'slug' => 'org-2',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Alpha',
            'slug' => 'alpha',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Beta',
            'slug' => 'beta',
        ]);

        $cat = Category::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Corte',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $storeB->id,
            'category_id' => $cat->id,
            'name' => 'Serviço B',
            'duration' => 30,
            'price' => 20,
            'online_price' => 20,
            'sort_order' => 1,
        ]);

        $this->get('/booking/'.$storeA->slug.'/servico/'.$service->id)
            ->assertNotFound();
    }
}
