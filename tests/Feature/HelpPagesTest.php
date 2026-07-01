<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, store: Store}
     */
    private function staffFixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Ajuda',
            'slug' => 'org-ajuda',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Ajuda',
            'slug' => 'loja-ajuda',
        ]);
        $user = User::query()->create([
            'name' => 'Receção Ajuda',
            'email' => 'rececao-ajuda@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RECECAO,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'name' => 'Receção Ajuda',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $user->stores()->sync([$store->id]);

        return ['user' => $user, 'store' => $store];
    }

    public function test_ajuda_index_redirects_to_agenda_guide(): void
    {
        $fx = $this->staffFixture();

        $this->actingAs($fx['user'])
            ->get(route('ajuda.index'))
            ->assertRedirect(route('ajuda.agenda'));
    }

    public function test_ajuda_agenda_page_is_accessible(): void
    {
        $fx = $this->staffFixture();

        $this->actingAs($fx['user'])
            ->get(route('ajuda.agenda'))
            ->assertOk()
            ->assertSee('Guia de utilização da agenda', false)
            ->assertSee('Perguntas frequentes', false)
            ->assertSee(route('agenda.index'), false);
    }
}
