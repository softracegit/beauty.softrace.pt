<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Models\UserPageViewLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPageViewLogTest extends TestCase
{
    use RefreshDatabase;

    protected function createBackofficeUser(Store $store): User
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-nav@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $store->organization_id,
        ]);
        Agent::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'name' => 'Admin',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $user->stores()->sync([$store->id]);

        return $user;
    }

    public function test_get_page_view_is_logged_with_debounce(): void
    {
        config(['user_navigation_log.debounce_seconds' => 60]);

        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-nav',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-nav',
        ]);
        $user = $this->createBackofficeUser($store);

        $this->actingAs($user)
            ->withSession([SetCurrentStore::SESSION_KEY => $store->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDatabaseCount('user_page_view_logs', 1);
        $this->assertDatabaseHas('user_page_view_logs', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'route_name' => 'dashboard',
        ]);

        $this->actingAs($user)
            ->withSession([SetCurrentStore::SESSION_KEY => $store->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDatabaseCount('user_page_view_logs', 1);
    }

    public function test_ajax_get_is_not_logged(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-ajax',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-ajax',
        ]);
        $user = $this->createBackofficeUser($store);

        $this->actingAs($user)
            ->withSession([SetCurrentStore::SESSION_KEY => $store->id])
            ->getJson(route('agenda.events'))
            ->assertOk();

        $this->assertDatabaseCount('user_page_view_logs', 0);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
