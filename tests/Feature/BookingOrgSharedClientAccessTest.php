<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingOrgSharedClientAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_client_from_sibling_store_can_open_account_and_checkout(): void
    {
        $org = Organization::query()->create([
            'name' => 'Fada Org',
            'slug' => 'fada-org-shared',
            'status' => 'active',
        ]);
        $homeStore = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Fada Studio',
            'slug' => 'fadastudio-shared',
            'timezone' => 'Europe/Lisbon',
        ]);
        $otherStore = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Fada Forca',
            'slug' => 'fadastudioforca-shared',
            'timezone' => 'Europe/Lisbon',
        ]);

        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $homeStore->id,
            'name' => 'Cliente Fada',
            'email' => 'cliente-fada@test.test',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $user = User::query()->create([
            'name' => 'Cliente Fada',
            'email' => 'cliente-fada@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_CLIENTE,
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->get(route('booking.conta.index', ['store' => $otherStore->slug]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('booking.conta.settings', ['store' => $otherStore->slug]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('booking.step3', ['store' => $otherStore->slug]))
            ->assertOk();
    }

    public function test_booking_client_from_other_organization_gets_404_on_account(): void
    {
        $orgA = Organization::query()->create([
            'name' => 'Org A',
            'slug' => 'org-a-shared-deny',
            'status' => 'active',
        ]);
        $orgB = Organization::query()->create([
            'name' => 'Org B',
            'slug' => 'org-b-shared-deny',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $orgA->id,
            'name' => 'Loja A',
            'slug' => 'loja-a-shared-deny',
            'timezone' => 'Europe/Lisbon',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $orgB->id,
            'name' => 'Loja B',
            'slug' => 'loja-b-shared-deny',
            'timezone' => 'Europe/Lisbon',
        ]);

        $client = Client::query()->create([
            'organization_id' => $orgA->id,
            'store_id' => $storeA->id,
            'name' => 'Cliente A',
            'email' => 'cliente-a-deny@test.test',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $user = User::query()->create([
            'name' => 'Cliente A',
            'email' => 'cliente-a-deny@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_CLIENTE,
            'organization_id' => $orgA->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->get(route('booking.conta.index', ['store' => $storeB->slug]))
            ->assertNotFound();
    }
}
