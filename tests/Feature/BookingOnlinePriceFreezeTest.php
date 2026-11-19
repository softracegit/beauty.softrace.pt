<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\OnlineBookingCheckoutService;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingOnlinePriceFreezeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     store: Store,
     *     agent: Agent,
     *     service: Service,
     *     client: Client,
     * }
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Price Freeze',
            'slug' => 'org-price-freeze',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Price Freeze',
            'slug' => 'loja-price-freeze',
            'timezone' => 'Europe/Lisbon',
            'weekly_schedule' => Store::defaultWeeklySchedule(),
        ]);
        $category = Category::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço Freeze',
            'duration' => 30,
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Prestador Freeze',
            'email' => 'prestador-price-freeze@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Prestador Freeze',
            'status' => Agent::STATUS_ACTIVE,
            'visible_in_booking' => true,
            'visible_in_agenda' => true,
        ]);
        $agent->services()->sync([$service->id]);
        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Cliente Freeze',
            'email' => 'cliente-price-freeze@test.test',
            'phone' => '+351912345678',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        return compact('store', 'agent', 'service', 'client');
    }

    public function test_complete_keeps_intent_price_when_catalog_changes(): void
    {
        $fx = $this->fixture();
        app(CurrentStore::class)->set($fx['store']);

        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', $tz));

        $validated = [
            'name' => 'Cliente Freeze',
            'email' => 'cliente-price-freeze@test.test',
            'phone' => '+351912345678',
            'date' => '2026-10-21',
            'time' => '10:00',
            'agent_id' => (string) $fx['agent']->id,
            'services' => [
                ['id' => $fx['service']->id],
            ],
        ];

        $checkout = app(OnlineBookingCheckoutService::class);
        $atIntent = $checkout->resolveValidatedBookingPayload($validated);
        $this->assertSame(30.0, (float) $atIntent['bookingLines'][0]['price']);
        $this->assertSame(30.0, (float) $atIntent['totalPrice']);

        $snapshot = $checkout->buildPriceSnapshot($atIntent);
        $this->assertSame(30.0, (float) $snapshot['total_price']);
        $this->assertSame(30.0, (float) $snapshot['lines'][0]['price']);

        $fx['service']->update([
            'price' => 35,
            'online_price' => 35,
        ]);

        $live = $checkout->resolveValidatedBookingPayload($validated);
        $this->assertSame(35.0, (float) $live['bookingLines'][0]['price']);
        $this->assertSame(35.0, (float) $live['totalPrice']);

        $frozen = $checkout->resolveValidatedBookingPayload($validated, $snapshot);
        $this->assertSame(30.0, (float) $frozen['bookingLines'][0]['price']);
        $this->assertSame(30.0, (float) $frozen['totalPrice']);

        $event = $checkout->persistMarcacao($frozen, $fx['client']);
        $pivotPrice = (float) $event->eventServices()->firstOrFail()->pivot->price;
        $this->assertSame(30.0, $pivotPrice);

        Carbon::setTestNow();
    }
}
