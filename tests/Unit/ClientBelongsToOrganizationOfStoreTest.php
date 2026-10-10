<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBelongsToOrganizationOfStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_matches_sibling_store_in_same_organization(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org Partilha',
            'slug' => 'org-partilha-clientes',
            'status' => 'active',
        ]);
        $storeA = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja A',
            'slug' => 'loja-a-partilha',
            'timezone' => 'Europe/Lisbon',
        ]);
        $storeB = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja B',
            'slug' => 'loja-b-partilha',
            'timezone' => 'Europe/Lisbon',
        ]);
        $otherOrg = Organization::query()->create([
            'name' => 'Outra Org',
            'slug' => 'outra-org-partilha',
            'status' => 'active',
        ]);
        $otherStore = Store::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Loja Outra',
            'slug' => 'loja-outra-partilha',
            'timezone' => 'Europe/Lisbon',
        ]);

        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $storeA->id,
            'name' => 'Cliente Partilhado',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $this->assertTrue($client->belongsToOrganizationOfStore($storeA));
        $this->assertTrue($client->belongsToOrganizationOfStore($storeB));
        $this->assertTrue($client->belongsToOrganizationOfStore((int) $storeB->id));
        $this->assertFalse($client->belongsToOrganizationOfStore($otherStore));
    }
}
