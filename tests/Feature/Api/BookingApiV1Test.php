<?php

namespace Tests\Feature\Api;

use App\Actions\SyncServiceOptionsAction;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\CrmSetting;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\PortugueseNationalHolidays;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * API v1 da app móvel (só leitura): loja, catálogo, técnicas e disponibilidade.
 */
class BookingApiV1Test extends TestCase
{
    use BookingApiFixture;
    use RefreshDatabase;

    public function test_store_endpoint_returns_profile_booking_settings_and_other_stores(): void
    {
        $fx = $this->fixture();
        CrmSetting::setBookingCancellationPolicyText('Cancele até 12 horas antes.', $fx['store']->id);

        $this->getJson('/api/v1/stores/aveiro')
            ->assertOk()
            ->assertJsonPath('data.slug', 'aveiro')
            ->assertJsonPath('data.name', 'Fada Studio Aveiro')
            ->assertJsonPath('data.booking.cancellation_policy_text', 'Cancele até 12 horas antes.')
            ->assertJsonPath('data.other_stores.0.slug', 'porto')
            ->assertJsonStructure(['data' => ['weekly_schedule' => ['mon' => ['enabled', 'start', 'end']], 'booking' => ['prepayment_required', 'cancellation_notice_hours', 'slot_interval_minutes']]]);
    }

    public function test_unknown_store_or_inactive_organization_returns_json_404(): void
    {
        $fx = $this->fixture();

        $this->get('/api/v1/stores/nao-existe')->assertNotFound()->assertHeader('content-type', 'application/json');

        $fx['org']->forceFill(['status' => 'inactive'])->save();
        $this->getJson('/api/v1/stores/aveiro')->assertNotFound();
    }

    public function test_catalog_hides_hidden_items_uses_online_price_and_bookable_extras(): void
    {
        $fx = $this->fixture();

        $res = $this->getJson('/api/v1/stores/aveiro/catalog')->assertOk();
        $categories = $res->json('data.categories');

        $this->assertSame(['Manicure'], array_column($categories, 'name'));
        $services = $categories[0]['services'];
        $this->assertSame(['Unhas de Gel'], array_column($services, 'name'));

        $gel = $services[0];
        $this->assertEquals(24, $gel['price']);
        $this->assertSame(['choice', 'choice', 'addon'], array_column($gel['options'], 'type'));
        $this->assertTrue($gel['options'][0]['is_baseline']);
        $this->assertSame(['Francesinha'], array_column($gel['extras'], 'name'));
    }

    public function test_technicians_lists_visible_agents_with_services_and_extra_pairs(): void
    {
        $fx = $this->fixture();

        $this->getJson('/api/v1/stores/aveiro/technicians')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Laissa')
            ->assertJsonPath('data.0.extra_pairs.0.service_id', $fx['gel']->id);
    }

    public function test_availability_returns_days_and_excludes_busy_agenda_times(): void
    {
        $fx = $this->fixture();
        $day = $this->nextWorkingDay();

        CalendarEvent::query()->create([
            'store_id' => $fx['store']->id,
            'user_id' => $fx['agent']->user_id,
            'title' => 'Ocupado',
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(12, 0),
        ]);

        $res = $this->getJson('/api/v1/stores/aveiro/availability?'.http_build_query([
            'from' => $day->format('Y-m-d'),
            'days' => 2,
            'agent_id' => (string) $fx['agent']->id,
            'duration' => 70,
            'service_ids' => (string) $fx['gel']->id,
        ]))->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($day->format('Y-m-d'), $data[0]['date']);

        $slots = $data[0]['slots'];
        // 70 min às 09:00 acabaria às 10:10, dentro do evento das 10:00–12:00
        $this->assertSame('12:00', $slots[0]);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('11:00', $slots);
        $this->assertContains('12:00', $slots);

        // "Qualquer profissional" também respeita a agenda
        $any = $this->getJson('/api/v1/stores/aveiro/availability?'.http_build_query([
            'from' => $day->format('Y-m-d'), 'days' => 1, 'duration' => 70, 'service_ids' => (string) $fx['gel']->id,
        ]))->assertOk()->json('data.0.slots');
        $this->assertNotContains('10:30', $any);
    }

    public function test_availability_validates_input(): void
    {
        $this->fixture();

        $this->getJson('/api/v1/stores/aveiro/availability?duration=70&days=40')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
        $this->get('/api/v1/stores/aveiro/availability')
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_web_booking_still_works_alongside_api(): void
    {
        $this->fixture();

        $this->get(route('booking.index', ['store' => 'aveiro']))->assertOk()->assertSee('Unhas de Gel');
    }
}
