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
use App\Support\BookingCartPriceToken;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingCartPriceTokenCheckoutTest extends TestCase
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
            'name' => 'Org Cart Token',
            'slug' => 'org-cart-token',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Cart Token',
            'slug' => 'loja-cart-token',
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
            'name' => 'Serviço Cart Token',
            'duration' => 30,
            'price' => 30,
            'online_price' => 30,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Prestador Cart Token',
            'email' => 'prestador-cart-token@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Prestador Cart Token',
            'status' => Agent::STATUS_ACTIVE,
            'visible_in_booking' => true,
            'visible_in_agenda' => true,
        ]);
        $agent->services()->sync([$service->id]);
        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Cliente Cart Token',
            'email' => 'cliente-cart-token@test.test',
            'phone' => '+351912345678',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        return compact('store', 'agent', 'service', 'client');
    }

    /**
     * @return array<string, mixed>
     */
    private function baseValidated(array $fx, array $services): array
    {
        return [
            'name' => 'Cliente Cart Token',
            'email' => 'cliente-cart-token@test.test',
            'phone' => '+351912345678',
            'date' => '2026-10-21',
            'time' => '10:00',
            'agent_id' => (string) $fx['agent']->id,
            'services' => $services,
        ];
    }

    public function test_valid_cart_token_keeps_price_when_catalog_rises(): void
    {
        $fx = $this->fixture();
        app(CurrentStore::class)->set($fx['store']);
        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', $tz));

        $token = BookingCartPriceToken::issue(
            BookingCartPriceToken::KIND_SERVICE,
            (int) $fx['service']->id,
            30.0,
            time(),
            (int) $fx['store']->id,
        );

        $fx['service']->update([
            'price' => 35,
            'online_price' => 35,
        ]);

        $checkout = app(OnlineBookingCheckoutService::class);
        $resolved = $checkout->resolveValidatedBookingPayload($this->baseValidated($fx, [
            [
                'id' => $fx['service']->id,
                'price_token' => $token,
            ],
        ]));

        $this->assertSame(30.0, (float) $resolved['bookingLines'][0]['price']);
        $this->assertSame(30.0, (float) $resolved['totalPrice']);

        $event = $checkout->persistMarcacao($resolved, $fx['client']);
        $this->assertSame(30.0, (float) $event->eventServices()->firstOrFail()->pivot->price);

        Carbon::setTestNow();
    }

    public function test_without_token_uses_live_catalog_price(): void
    {
        $fx = $this->fixture();
        app(CurrentStore::class)->set($fx['store']);
        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', $tz));

        $fx['service']->update([
            'price' => 35,
            'online_price' => 35,
        ]);

        $checkout = app(OnlineBookingCheckoutService::class);
        $resolved = $checkout->resolveValidatedBookingPayload($this->baseValidated($fx, [
            ['id' => $fx['service']->id],
        ]));

        $this->assertSame(35.0, (float) $resolved['bookingLines'][0]['price']);
        $this->assertSame(35.0, (float) $resolved['totalPrice']);

        Carbon::setTestNow();
    }

    public function test_expired_token_falls_back_to_live_price(): void
    {
        $fx = $this->fixture();
        app(CurrentStore::class)->set($fx['store']);
        $tz = (string) config('booking.business_timezone', 'Europe/Lisbon');
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', $tz));

        $token = BookingCartPriceToken::issue(
            BookingCartPriceToken::KIND_SERVICE,
            (int) $fx['service']->id,
            30.0,
            time() - (BookingCartPriceToken::ttlSeconds((int) $fx['store']->id) + 120),
            (int) $fx['store']->id,
        );

        $fx['service']->update([
            'price' => 35,
            'online_price' => 35,
        ]);

        $checkout = app(OnlineBookingCheckoutService::class);
        $resolved = $checkout->resolveValidatedBookingPayload($this->baseValidated($fx, [
            [
                'id' => $fx['service']->id,
                'price_token' => $token,
            ],
        ]));

        $this->assertSame(35.0, (float) $resolved['bookingLines'][0]['price']);
        $this->assertSame(35.0, (float) $resolved['totalPrice']);

        Carbon::setTestNow();
    }
}
