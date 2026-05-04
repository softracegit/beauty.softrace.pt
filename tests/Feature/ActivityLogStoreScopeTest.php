<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Activity;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityLogStoreScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_index_excludes_rows_from_other_stores(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org',
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

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Log only loja B',
            'event' => 'created',
            'store_id' => $storeB->id,
            'organization_id' => (int) $org->id,
        ]);
        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Log loja A',
            'event' => 'created',
            'store_id' => $storeA->id,
            'organization_id' => (int) $org->id,
        ]);

        $html = $this->actingAs($user)
            ->withSession([SetCurrentStore::SESSION_KEY => $storeA->id])
            ->get(route('activity.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Log loja A', $html);
        $this->assertStringNotContainsString('Log only loja B', $html);
    }
}
