<?php

namespace Tests\Feature\Definicoes;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Services\BookingReminderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LembretesSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, admin: User}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Lembretes',
            'slug' => 'org-lembretes',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Lembretes',
            'slug' => 'loja-lembretes',
            'timezone' => 'Europe/Lisbon',
        ]);
        $admin = User::query()->create([
            'name' => 'Admin Lembretes',
            'email' => 'admin-lembretes@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $admin->id,
            'store_id' => $store->id,
            'name' => 'Admin Lembretes',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $admin->stores()->sync([$store->id]);

        return compact('store', 'admin');
    }

    public function test_page_shows_defaults_from_config(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('definicoes.lembretes'));

        $response->assertOk();
        $response->assertSee('Canais activos', false);
        $response->assertSee('Timings — dia anterior', false);
        $response->assertSee('Timings — próprio dia', false);
    }

    public function test_save_persists_toggles_and_timings(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('definicoes.lembretes.update'), [
                'sms_day_before_enabled' => '1',
                // sms same day off
                'email_day_before_enabled' => '1',
                'email_same_day_enabled' => '1',
                'day_before_send_start_hour' => 10,
                'day_before_send_end_hour' => 19,
                'min_minutes_after_created' => 60,
                'same_day_lead_min_hours' => 1.5,
                'same_day_lead_max_hours' => 2.5,
                'same_day_send_start_hour' => 8,
                'same_day_send_end_hour' => 18,
            ]);

        $response->assertRedirect(route('definicoes.lembretes'));

        $settings = BookingReminderSettings::forStore($fx['store']->id);
        $this->assertTrue($settings['sms_day_before_enabled']);
        $this->assertFalse($settings['sms_same_day_enabled']);
        $this->assertTrue($settings['email_day_before_enabled']);
        $this->assertTrue($settings['email_same_day_enabled']);
        $this->assertSame(10, $settings['day_before_send_start_hour']);
        $this->assertSame(19, $settings['day_before_send_end_hour']);
        $this->assertSame(60, $settings['min_minutes_after_created']);
        $this->assertSame(90, $settings['same_day_lead_min_minutes']);
        $this->assertSame(150, $settings['same_day_lead_max_minutes']);
        $this->assertSame(8 * 60, $settings['same_day_send_start_minutes']);
        $this->assertSame(18 * 60, $settings['same_day_send_end_minutes']);
    }

    public function test_dispatcher_skips_when_sms_same_day_disabled(): void
    {
        $fx = $this->fixture();
        BookingReminderSettings::save(array_merge(BookingReminderSettings::defaults(), [
            'sms_same_day_enabled' => false,
        ]), $fx['store']->id);

        $settings = BookingReminderSettings::forStore($fx['store']->id);
        $this->assertFalse($settings['sms_same_day_enabled']);
    }
}
