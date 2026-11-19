<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\AgentServiceExtras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentServiceExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_service_assignment_grants_extras_and_booking_hides_unchecked_ones(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org Extras Equipa',
            'slug' => 'org-extras-equipa',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Extras',
            'slug' => 'loja-extras-equipa',
            'timezone' => 'Europe/Lisbon',
        ]);
        $category = Category::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Manicure',
            'duration' => 45,
            'price' => 20,
            'online_price' => 20,
            'sort_order' => 1,
        ]);
        $extraCategory = ExtraCategory::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $gel = Extra::query()->create([
            'extra_category_id' => $extraCategory->id,
            'name' => 'Gel',
            'duration' => 15,
            'price' => 8,
            'sort_order' => 1,
        ]);
        $nailArt = Extra::query()->create([
            'extra_category_id' => $extraCategory->id,
            'name' => 'Nail art',
            'duration' => 20,
            'price' => 10,
            'sort_order' => 2,
        ]);
        $service->extras()->sync([$gel->id, $nailArt->id]);

        $ana = $this->agent($org, $store, 'Ana Extras', 'ana-extras@test.test');
        $bia = $this->agent($org, $store, 'Bia Extras', 'bia-extras@test.test');
        $ana->services()->sync([$service->id]);
        $bia->services()->sync([$service->id]);

        AgentServiceExtras::syncForServiceAgents((int) $service->id, [], [(int) $ana->id, (int) $bia->id]);

        $this->assertDatabaseHas('agent_service_extra', [
            'agent_id' => $ana->id,
            'service_id' => $service->id,
            'extra_id' => $nailArt->id,
        ]);

        AgentServiceExtras::replaceStoreSelections(
            [
                ['service_id' => (int) $service->id, 'extra_id' => (int) $gel->id],
                ['service_id' => (int) $service->id, 'extra_id' => (int) $nailArt->id],
            ],
            [(int) $service->id => [(int) $ana->id, (int) $bia->id]],
            [
                (int) $service->id => [
                    (int) $gel->id => [(int) $ana->id, (int) $bia->id],
                    (int) $nailArt->id => [(int) $bia->id],
                ],
            ],
            [(int) $ana->id, (int) $bia->id],
        );

        $this->assertDatabaseMissing('agent_service_extra', [
            'agent_id' => $ana->id,
            'service_id' => $service->id,
            'extra_id' => $nailArt->id,
        ]);

        $bookable = AgentServiceExtras::bookableExtraIdsByService((int) $store->id);
        $this->assertEqualsCanonicalizing([(int) $gel->id, (int) $nailArt->id], $bookable[(int) $service->id]);

        $onlyAna = AgentServiceExtras::bookableExtraIdsByService((int) $store->id, (int) $ana->id);
        $this->assertSame([(int) $gel->id], $onlyAna[(int) $service->id]);

        $eligible = AgentServiceExtras::filterAgents(collect([$ana, $bia]), [[
            'service_id' => (int) $service->id,
            'extra_id' => (int) $nailArt->id,
        ]]);
        $this->assertSame([(int) $bia->id], $eligible->pluck('id')->map(fn ($id) => (int) $id)->all());

        $anaPairs = AgentServiceExtras::allowedPairSet((int) $ana->id);
        $this->assertArrayHasKey($service->id.':'.$gel->id, $anaPairs);
        $this->assertArrayNotHasKey($service->id.':'.$nailArt->id, $anaPairs);
    }

    public function test_linking_an_extra_to_a_service_grants_it_to_current_technicians(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org Extra Novo',
            'slug' => 'org-extra-novo',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Extra Novo',
            'slug' => 'loja-extra-novo',
        ]);
        $category = Category::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Manicure',
            'duration' => 30,
            'price' => 15,
            'sort_order' => 1,
        ]);
        $extraCategory = ExtraCategory::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $extra = Extra::query()->create([
            'extra_category_id' => $extraCategory->id,
            'name' => 'Francesinha',
            'duration' => 10,
            'price' => 5,
            'sort_order' => 1,
        ]);
        $agent = $this->agent($org, $store, 'Carla Extras', 'carla-extras@test.test');
        DB::table('agent_service')->insert([
            'agent_id' => $agent->id,
            'service_id' => $service->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AgentServiceExtras::syncForExtraServices((int) $extra->id, [], [(int) $service->id]);

        $this->assertDatabaseHas('agent_service_extra', [
            'agent_id' => $agent->id,
            'service_id' => $service->id,
            'extra_id' => $extra->id,
        ]);
    }

    private function agent(Organization $org, Store $store, string $name, string $email): Agent
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);

        return Agent::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'name' => $name,
            'status' => Agent::STATUS_ACTIVE,
            'visible_in_booking' => true,
            'visible_in_agenda' => true,
        ]);
    }
}
