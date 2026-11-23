<?php

namespace Tests\Feature\Booking;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PresetAgentBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, agent: Agent, agentUrl: string}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Preset Back',
            'slug' => 'org-preset-back',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Preset Back',
            'slug' => 'loja-preset-back',
            'timezone' => 'Europe/Lisbon',
        ]);
        $user = User::query()->create([
            'name' => 'Técnica Preset',
            'email' => 'tecnica-preset@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'name' => 'Técnica Preset',
            'status' => Agent::STATUS_ACTIVE,
            'visible_in_booking' => true,
            'visible_in_agenda' => true,
            'booking_slug' => 'tecnica-preset',
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'name' => 'Manicure',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'category_id' => $category->id,
            'name' => 'Verniz',
            'duration' => 45,
            'price' => 18,
            'online_price' => 18,
            'sort_order' => 1,
        ]);
        $service->agents()->attach($agent->id);

        return [
            'store' => $store,
            'agent' => $agent,
            'agentUrl' => route('booking.agent', [
                'store' => $store->slug,
                'agent' => $agent->booking_slug,
            ], false),
        ];
    }

    public function test_datetime_back_goes_to_agent_url_after_preset_landing(): void
    {
        $fx = $this->fixture();

        $this->get($fx['agentUrl'])->assertOk();

        $datetime = $this->get(route('booking.datetime', ['store' => $fx['store']->slug]));
        $datetime->assertOk();
        $datetime->assertSee('href="'.e($fx['agentUrl']).'"', false);
        $datetime->assertDontSee(
            'href="'.e(route('booking.technician', ['store' => $fx['store']->slug], false)).'"',
            false,
        );
    }

    public function test_technician_redirects_to_agent_url_while_preset_active(): void
    {
        $fx = $this->fixture();

        $this->get($fx['agentUrl'])->assertOk();

        $this->get(route('booking.technician', ['store' => $fx['store']->slug]))
            ->assertRedirect($fx['agentUrl']);
    }

    public function test_general_booking_index_clears_preset_so_datetime_backs_to_staff(): void
    {
        $fx = $this->fixture();

        $this->get($fx['agentUrl'])->assertOk();
        $this->get(route('booking.index', ['store' => $fx['store']->slug]))->assertOk();

        $datetime = $this->get(route('booking.datetime', ['store' => $fx['store']->slug]));
        $datetime->assertOk();
        $datetime->assertSee(
            'href="'.e(route('booking.technician', ['store' => $fx['store']->slug], false)).'"',
            false,
        );
    }
}
