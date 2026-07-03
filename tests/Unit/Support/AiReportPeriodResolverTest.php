<?php

namespace Tests\Unit\Support;

use App\Models\Organization;
use App\Models\Store;
use App\Support\AiReportPeriodResolver;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiReportPeriodResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_mes_passado_in_store_timezone(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-period',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-period',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-03-15 12:00:00', StoreBusinessTime::timezoneForStore($store->id)));

        $period = AiReportPeriodResolver::resolve($store->id, 'mes_passado');

        $this->assertSame('2026-02-01', $period['desde']);
        $this->assertSame('2026-02-28', $period['ate']);
        $this->assertSame('Mês passado', $period['label']);

        Carbon::setTestNow();
    }
}
