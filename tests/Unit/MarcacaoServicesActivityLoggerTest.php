<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Store;
use App\Services\MarcacaoServicesActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcacaoServicesActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_lines_show_readable_before_and_after_labels(): void
    {
        $store = $this->storeFixture();
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $serviceA = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Verniz de Gel / Gelinho',
            'duration' => 30,
            'price' => 20,
            'online_price' => 20,
            'sort_order' => 1,
        ]);
        $serviceB = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Manicure',
            'duration' => 45,
            'price' => 25,
            'online_price' => 25,
            'sort_order' => 2,
        ]);

        $logger = app(MarcacaoServicesActivityLogger::class);
        $lines = $logger->changeLines(
            [[
                'service_id' => $serviceA->id,
                'service_option_id' => null,
                'duration' => 30,
                'price' => 20,
                'original_price' => 20,
                'extras' => [],
            ]],
            [[
                'service_id' => $serviceB->id,
                'service_option_id' => null,
                'duration' => 45,
                'price' => 25,
                'original_price' => 25,
                'extras' => [],
            ]],
            (int) $store->id,
        );

        $this->assertSame(['Verniz de Gel / Gelinho → Manicure'], $lines);
    }

    public function test_change_lines_include_option_and_extra_names(): void
    {
        $store = $this->storeFixture();
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Pés',
            'sort_order' => 1,
        ]);
        $extraCategory = ExtraCategory::query()->create([
            'store_id' => $store->id,
            'name' => 'Tratamentos',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Pedicure',
            'duration' => 30,
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $option = ServiceOption::query()->create([
            'service_id' => $service->id,
            'name' => 'Spa completo',
            'duration' => 45,
            'price' => 35,
            'online_price' => 35,
            'sort_order' => 0,
        ]);
        $extra = Extra::query()->create([
            'store_id' => $store->id,
            'extra_category_id' => $extraCategory->id,
            'name' => 'Parafina',
            'duration' => 10,
            'price' => 5,
            'sort_order' => 1,
        ]);

        $logger = app(MarcacaoServicesActivityLogger::class);
        $before = [[
            'service_id' => $service->id,
            'service_option_id' => null,
            'duration' => 30,
            'price' => 30,
            'original_price' => 30,
            'extras' => [],
        ]];
        $after = [[
            'service_id' => $service->id,
            'service_option_id' => $option->id,
            'duration' => 45,
            'price' => 35,
            'original_price' => 35,
            'extras' => [[
                'extra_id' => $extra->id,
                'duration' => 10,
                'price' => 5,
            ]],
        ]];

        $lines = $logger->changeLines($before, $after, (int) $store->id);

        $this->assertSame(['Pedicure → Spa completo + Parafina'], $lines);
    }

    private function storeFixture(): Store
    {
        $org = Organization::query()->create([
            'name' => 'Org Services Log',
            'slug' => 'org-services-log',
            'status' => 'active',
        ]);

        return Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Services Log',
            'slug' => 'loja-services-log',
        ]);
    }
}
