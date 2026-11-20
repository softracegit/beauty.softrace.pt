<?php

namespace Tests\Feature\Booking;

use App\Jobs\SendBookingSameDayReminderSmsJob;
use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendBookingSameDayReminderSmsTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $phone, Carbon $start, string $status = CalendarEvent::STATUS_AGENDADO): CalendarEvent
    {
        $org = Organization::query()->create([
            'name' => 'FadaStudio',
            'slug' => 'fada-'.substr(md5($phone.$start->timestamp), 0, 8),
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Aveiro Centro',
            'slug' => 'aveiro-'.substr(md5($phone.$start->timestamp), 0, 8),
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'name' => 'Cliente Same Day',
            'phone' => $phone,
            'notify_sms_booking_reminders' => true,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        return CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => $status,
            'title' => 'Lembrete mesmo dia',
            'start_at' => $start->copy()->utc(),
            'end_at' => $start->copy()->addMinutes(30)->utc(),
        ]);
    }

    public function test_job_marks_failed_for_blocked_country_without_twilio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Lisbon'));
        $event = $this->makeEvent('+34612345678', Carbon::parse('2026-06-15 14:30:00', 'Europe/Lisbon'));

        $this->app->call([new SendBookingSameDayReminderSmsJob((int) $event->id), 'handle']);

        $event->refresh();
        $this->assertNotNull($event->booking_sms_same_day_failed_at);
        $this->assertNull($event->booking_sms_same_day_sent_at);
    }

    public function test_dispatcher_skips_outside_lead_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));
        // Faltam 5h (> 3h) — fora da janela 2–3h
        $this->makeEvent('+351912345678', Carbon::parse('2026-06-15 15:00:00', 'Europe/Lisbon'));

        Artisan::call('booking:dispatch-same-day-sms-reminders');

        $this->assertDatabaseMissing('calendar_events', [
            'booking_sms_same_day_sent_at' => now(),
        ]);
        $this->assertSame(0, CalendarEvent::query()->whereNotNull('booking_sms_same_day_sent_at')->count());
        $this->assertSame(0, CalendarEvent::query()->whereNotNull('booking_sms_same_day_failed_at')->count());
    }

    public function test_job_body_has_no_url_and_uses_brand_store(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Lisbon'));
        $event = $this->makeEvent('+351912345678', Carbon::parse('2026-06-15 14:30:00', 'Europe/Lisbon'));

        config([
            'booking.business_timezone' => 'Europe/Lisbon',
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'token',
            'services.twilio.sms_from' => 'SoftRace',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SMtest', 'status' => 'queued'], 201),
        ]);

        $this->app->call([new SendBookingSameDayReminderSmsJob((int) $event->id), 'handle']);

        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, 'Twilio HTTP request was not recorded');
        $body = (string) ($recorded[0][0]->data()['Body'] ?? '');
        $expectedTime = $event->start_at->copy()
            ->timezone((string) config('booking.business_timezone', 'Europe/Lisbon'))
            ->format('H:i');
        $this->assertStringStartsWith('Lembrete:', $body);
        $this->assertStringNotContainsString('FadaStudio', $body);
        $this->assertStringNotContainsString('Aveiro Centro', $body);
        $this->assertStringContainsString($expectedTime, $body);
        $this->assertStringNotContainsString('http', $body);
        $this->assertStringNotContainsString('/r/', $body);

        $event->refresh();
        $this->assertNotNull($event->booking_sms_same_day_sent_at);
        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->status);
        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $event->getMorphClass())
                ->where('subject_id', $event->id)
                ->where('event', 'lembrete_sms')
                ->where('description', 'SMS de lembrete (mesmo dia) enviado')
                ->exists()
        );
    }

    #[DataProvider('eligibleStatuses')]
    public function test_eligible_statuses(string $status): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Lisbon'));
        $event = $this->makeEvent('+351912345678', Carbon::parse('2026-06-15 14:30:00', 'Europe/Lisbon'), $status);

        config([
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'token',
            'services.twilio.sms_from' => 'SoftRace',
        ]);
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SMtest', 'status' => 'queued'], 201),
        ]);

        $this->app->call([new SendBookingSameDayReminderSmsJob((int) $event->id), 'handle']);

        $this->assertNotNull($event->fresh()->booking_sms_same_day_sent_at);
    }

    public static function eligibleStatuses(): array
    {
        return [
            'agendado' => [CalendarEvent::STATUS_AGENDADO],
            'notificado' => [CalendarEvent::STATUS_NOTIFICADO],
            'confirmado' => [CalendarEvent::STATUS_CONFIRMADO],
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
