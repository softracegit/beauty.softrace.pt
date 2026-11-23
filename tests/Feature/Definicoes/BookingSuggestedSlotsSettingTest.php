<?php

namespace Tests\Feature\Definicoes;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingSuggestedSlotsSettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, admin: User}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Booking Slots',
            'slug' => 'org-booking-slots',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Booking Slots',
            'slug' => 'loja-booking-slots',
            'timezone' => 'Europe/Lisbon',
        ]);
        $admin = User::query()->create([
            'name' => 'Admin Booking Slots',
            'email' => 'admin-booking-slots@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id,
            'store_id' => $store->id,
            'name' => 'Admin Booking Slots',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$store->id]);

        return compact('store', 'admin');
    }

    public function test_page_shows_suggested_slots_toggle_default_on(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('definicoes.marcacoes'));

        $response->assertOk();
        $response->assertSee('Próximos horários sugeridos', false);
        $response->assertSee('id="booking_suggested_slots_enabled"', false);
        $this->assertTrue(CrmSetting::bookingSuggestedSlotsEnabled((int) $fx['store']->id));
    }

    public function test_save_can_disable_suggested_slots(): void
    {
        $fx = $this->fixture();
        $storeId = (int) $fx['store']->id;

        $response = $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $storeId])
            ->post(route('definicoes.marcacoes.update'), [
                'booking_slot_hold_minutes' => CrmSetting::bookingSlotHoldMinutes($storeId),
                'booking_cart_ttl_hours' => CrmSetting::bookingCartTtlHours($storeId),
                'booking_slot_interval_minutes' => CrmSetting::bookingSlotIntervalMinutes($storeId),
                'booking_suggested_slots_enabled' => '0',
                'booking_cancellation_notice_hours' => CrmSetting::bookingCancellationNoticeHours($storeId),
                'booking_any_staff_rule' => CrmSetting::bookingAnyStaffRule($storeId),
                'booking_theme' => CrmSetting::bookingTheme($storeId),
                'booking_width' => CrmSetting::bookingWidth($storeId),
                'booking_services_layout' => CrmSetting::bookingServicesLayout($storeId),
            ]);

        $response->assertRedirect(route('definicoes.marcacoes'));
        $this->assertFalse(CrmSetting::bookingSuggestedSlotsEnabled($storeId));
    }
}
