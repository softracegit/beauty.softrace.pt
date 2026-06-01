<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Support\ApplicableFees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCatalogFeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_catalog_fees_are_deduplicated_by_fee_id(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org Booking Fees',
            'slug' => 'org-booking-fees',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Booking',
            'slug' => 'loja-booking-fees',
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat',
            'sort_order' => 1,
        ]);

        $serviceA = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço A',
            'duration' => 30,
            'price' => 20.0,
            'online_price' => 20.0,
            'sort_order' => 1,
        ]);
        $serviceB = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço B',
            'duration' => 45,
            'price' => 30.0,
            'online_price' => 30.0,
            'sort_order' => 2,
        ]);

        $sharedFee = Fee::query()->create([
            'store_id' => $store->id,
            'name' => 'Deslocação',
            'price' => 5.0,
            'sort_order' => 1,
        ]);
        $otherFee = Fee::query()->create([
            'store_id' => $store->id,
            'name' => 'Material',
            'price' => 2.5,
            'sort_order' => 2,
        ]);

        $serviceA->fees()->attach([$sharedFee->id, $otherFee->id]);
        $serviceB->fees()->attach($sharedFee->id);

        $fees = ApplicableFees::forServiceIds([$serviceA->id, $serviceB->id], $store->id);

        $this->assertCount(2, $fees);
        $feeIds = array_column($fees, 'fee_id');
        $this->assertEqualsCanonicalizing([$sharedFee->id, $otherFee->id], $feeIds);

        $servicesSubtotal = 50.0;
        $total = round($servicesSubtotal + ApplicableFees::sumPrices($fees), 2);
        $this->assertSame(57.5, $total);
    }
}
