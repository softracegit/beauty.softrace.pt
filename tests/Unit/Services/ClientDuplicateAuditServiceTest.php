<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\ClientDuplicateAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDuplicateAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function storeFixture(): Store
    {
        $org = Organization::query()->create([
            'name' => 'Org AI',
            'slug' => 'org-ai',
            'status' => 'active',
        ]);

        return Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja AI',
            'slug' => 'loja-ai',
        ]);
    }

    public function test_finds_pair_with_same_name_and_one_digit_phone_difference(): void
    {
        $store = $this->storeFixture();

        Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Maria Silva',
            'phone' => '+351912345678',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Maria Silva',
            'phone' => '+351912345679',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $pairs = app(ClientDuplicateAuditService::class)->findSuspects($store->id);

        $this->assertCount(1, $pairs);
        $this->assertSame(1, $pairs->first()->phone_distance);
        $this->assertSame('alta', $pairs->first()->confidence);
    }

    public function test_finds_pair_with_same_name_and_missing_phone(): void
    {
        $store = $this->storeFixture();

        $withPhone = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Ana Costa',
            'phone' => '+351934567890',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $withoutPhone = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Ana Costa',
            'phone' => null,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        ZappyImportRef::query()->create([
            'store_id' => $store->id,
            'entity_type' => ZappyImportRef::TYPE_CLIENT,
            'zappy_key' => 'phone:934567890',
            'local_id' => $withoutPhone->id,
        ]);

        $pairs = app(ClientDuplicateAuditService::class)->findSuspects($store->id);

        $this->assertCount(1, $pairs);
        $this->assertTrue($pairs->first()->from_zappy);
        $this->assertContains($withPhone->id, [$pairs->first()->client_a_id, $pairs->first()->client_b_id]);
    }

    public function test_ignores_different_names(): void
    {
        $store = $this->storeFixture();

        Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente A',
            'phone' => '+351912345678',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente B',
            'phone' => '+351912345679',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $pairs = app(ClientDuplicateAuditService::class)->findSuspects($store->id);

        $this->assertCount(0, $pairs);
    }
}
