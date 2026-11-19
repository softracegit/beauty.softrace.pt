<?php

namespace Tests\Feature\Booking;

use App\Jobs\SendBookingReminderSmsJob;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Services\TwilioSmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendBookingReminderSmsBlockedCountryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blockedPhonesProvider')]
    public function test_job_marks_failed_and_does_not_call_twilio(string $phone): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));

        $org = Organization::query()->create([
            'name' => 'Org SMS Block',
            'slug' => 'org-sms-block-'.substr(md5($phone), 0, 8),
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja SMS Block',
            'slug' => 'loja-sms-block-'.substr(md5($phone), 0, 8),
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Block',
            'phone' => $phone,
            'notify_sms_booking_reminders' => true,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Lembrete',
            'start_at' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon')->utc(),
            'end_at' => Carbon::parse('2026-06-15 10:30:00', 'Europe/Lisbon')->utc(),
        ]);

        // Sem mock: o job sai antes de Twilio (país bloqueado).
        (new SendBookingReminderSmsJob((int) $event->id))->handle(app(TwilioSmsService::class));

        $event->refresh();
        $this->assertNotNull($event->booking_sms_reminder_failed_at);
        $this->assertNull($event->booking_sms_reminder_sent_at);
        $this->assertSame(CalendarEvent::STATUS_AGENDADO, $event->status);
    }

    public static function blockedPhonesProvider(): array
    {
        return [
            'us' => ['+12025550123'],
            'es' => ['+34612345678'],
            'br' => ['+5511987654321'],
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
