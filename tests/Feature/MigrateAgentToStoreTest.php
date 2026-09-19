<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\MigrateAgentToStoreService;
use App\Support\StoreBusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MigrateAgentToStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrates_future_events_and_keeps_past(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $storeA = Store::query()->create(['organization_id' => $org->id, 'name' => 'A', 'slug' => 'a', 'timezone' => 'Europe/Lisbon']);
        $storeB = Store::query()->create(['organization_id' => $org->id, 'name' => 'B', 'slug' => 'b', 'timezone' => 'Europe/Lisbon']);

        $cat = Category::query()->create([
            'organization_id' => $org->id, 'name' => 'Cat', 'sort_order' => 1,
        ]);
        $svc = Service::query()->create([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'name' => 'Corte', 'duration' => 30, 'price' => 15, 'sort_order' => 1,
        ]);

        $techUser = User::query()->create([
            'name' => 'Tech', 'email' => 'tech@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $techUser->id, 'store_id' => $storeA->id, 'name' => 'Tech', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $agent->services()->sync([$svc->id]);
        $techUser->stores()->sync([$storeA->id]);

        $now = StoreBusinessTime::nowUtcForStore((int) $storeA->id);
        $past = CalendarEvent::query()->create([
            'store_id' => $storeA->id,
            'user_id' => $techUser->id,
            'service_id' => $svc->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'start_at' => $now->copy()->subDays(2),
            'end_at' => $now->copy()->subDays(2)->addMinutes(30),
            'title' => 'Passado',
        ]);
        $future = CalendarEvent::query()->create([
            'store_id' => $storeA->id,
            'user_id' => $techUser->id,
            'service_id' => $svc->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'start_at' => $now->copy()->addDays(2),
            'end_at' => $now->copy()->addDays(2)->addMinutes(30),
            'title' => 'Futuro',
        ]);

        app(MigrateAgentToStoreService::class)->migrate($agent, $storeB);

        $agent->refresh();
        $past->refresh();
        $future->refresh();

        $this->assertSame((int) $storeB->id, (int) $agent->store_id);
        $this->assertSame((int) $storeA->id, (int) $past->store_id);
        $this->assertSame((int) $storeB->id, (int) $future->store_id);
        $this->assertSame((int) $svc->id, (int) $future->service_id);
        $this->assertSame([(int) $svc->id], $agent->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_reassigns_future_events_to_another_prestador(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org3', 'status' => 'active']);
        $storeA = Store::query()->create(['organization_id' => $org->id, 'name' => 'A', 'slug' => 'a3', 'timezone' => 'Europe/Lisbon']);
        $storeB = Store::query()->create(['organization_id' => $org->id, 'name' => 'B', 'slug' => 'b3', 'timezone' => 'Europe/Lisbon']);

        $cat = Category::query()->create([
            'organization_id' => $org->id, 'name' => 'Cat', 'sort_order' => 1,
        ]);
        $svc = Service::query()->create([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'name' => 'Corte', 'duration' => 30, 'price' => 15, 'sort_order' => 1,
        ]);

        $leavingUser = User::query()->create([
            'name' => 'Leaving', 'email' => 'leaving@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $keepingUser = User::query()->create([
            'name' => 'Keeping', 'email' => 'keeping@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $leaving = Agent::query()->create([
            'user_id' => $leavingUser->id, 'store_id' => $storeA->id, 'name' => 'Leaving', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $keeping = Agent::query()->create([
            'user_id' => $keepingUser->id, 'store_id' => $storeA->id, 'name' => 'Keeping', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $leaving->services()->sync([$svc->id]);
        $leavingUser->stores()->sync([$storeA->id]);
        $keepingUser->stores()->sync([$storeA->id]);

        $now = StoreBusinessTime::nowUtcForStore((int) $storeA->id);
        $future = CalendarEvent::query()->create([
            'store_id' => $storeA->id,
            'user_id' => $leavingUser->id,
            'service_id' => $svc->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'start_at' => $now->copy()->addDays(3),
            'end_at' => $now->copy()->addDays(3)->addMinutes(30),
            'title' => 'Futuro',
        ]);

        app(MigrateAgentToStoreService::class)->migrate(
            $leaving,
            $storeB,
            MigrateAgentToStoreService::MODE_REASSIGN,
            $keeping,
        );

        $leaving->refresh();
        $future->refresh();

        $this->assertSame((int) $storeB->id, (int) $leaving->store_id);
        $this->assertSame((int) $storeA->id, (int) $future->store_id);
        $this->assertSame((int) $keepingUser->id, (int) $future->user_id);
        $this->assertSame((int) $svc->id, (int) $future->service_id);
    }

    public function test_allows_migration_with_shared_org_service(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org2', 'status' => 'active']);
        $storeA = Store::query()->create(['organization_id' => $org->id, 'name' => 'A', 'slug' => 'a2']);
        $storeB = Store::query()->create(['organization_id' => $org->id, 'name' => 'B', 'slug' => 'b2']);
        $cat = Category::query()->create([
            'organization_id' => $org->id, 'name' => 'Cat', 'sort_order' => 1,
        ]);
        $svc = Service::query()->create([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'name' => 'Só na org', 'duration' => 30, 'price' => 10, 'sort_order' => 1,
        ]);
        $user = User::query()->create([
            'name' => 'T', 'email' => 't2@test.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $user->id, 'store_id' => $storeA->id, 'name' => 'T', 'status' => Agent::STATUS_ACTIVE,
        ]);
        $agent->services()->sync([$svc->id]);

        $conflicts = app(MigrateAgentToStoreService::class)->conflictMessages($agent, $storeB);
        $this->assertSame([], $conflicts);
    }
}
