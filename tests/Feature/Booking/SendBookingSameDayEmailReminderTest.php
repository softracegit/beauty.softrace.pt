<?php

namespace Tests\Feature\Booking;

use App\Jobs\SendBookingSameDayEmailReminderJob;
use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Notifications\ClientAppointmentReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBookingSameDayEmailReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(bool $emailOptIn = true): CalendarEvent
    {
        $org = Organization::query()->create([
            'name' => 'FadaStudio',
            'slug' => 'fada-same-day-email',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Aveiro Centro',
            'slug' => 'aveiro-same-day-email',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'name' => 'Ana Silva',
            'email' => 'ana-same-day@test.test',
            'notify_email_booking_reminders' => $emailOptIn,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        return CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_NOTIFICADO,
            'title' => 'Lembrete mesmo dia email',
            'start_at' => Carbon::parse('2026-06-15 14:30:00', 'Europe/Lisbon')->utc(),
            'end_at' => Carbon::parse('2026-06-15 15:00:00', 'Europe/Lisbon')->utc(),
        ]);
    }

    public function test_opt_in_sends_without_confirm_button(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Lisbon'));
        Notification::fake();
        $event = $this->makeEvent(true);

        $this->app->call([new SendBookingSameDayEmailReminderJob((int) $event->id), 'handle']);

        Notification::assertSentOnDemand(ClientAppointmentReminderNotification::class, function ($notification) {
            return $notification->includeConfirmAction === false;
        });
        $this->assertNotNull($event->fresh()->booking_email_same_day_sent_at);
        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $event->getMorphClass())
                ->where('subject_id', $event->id)
                ->where('event', 'lembrete_email')
                ->where('description', 'Email de lembrete (mesmo dia) enviado')
                ->exists()
        );

        $mail = (new ClientAppointmentReminderNotification(
            (int) $event->id,
            includeConfirmAction: false,
            isSameDay: true,
        ))->toMail((object) ['email' => 'ana-same-day@test.test']);
        $this->assertNull($mail->actionText);
        $this->assertNull($mail->actionUrl);
        $rendered = implode("\n", array_map(
            static fn ($line) => is_string($line) ? $line : (string) $line,
            $mail->introLines
        ));
        $this->assertStringContainsString('Lembramos da sua marcação hoje.', $rendered);
    }

    public function test_opt_out_marks_failed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Lisbon'));
        Notification::fake();
        $event = $this->makeEvent(false);

        $this->app->call([new SendBookingSameDayEmailReminderJob((int) $event->id), 'handle']);

        Notification::assertNothingSent();
        $this->assertNotNull($event->fresh()->booking_email_same_day_failed_at);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
