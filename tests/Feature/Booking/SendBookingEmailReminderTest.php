<?php

namespace Tests\Feature\Booking;

use App\Jobs\SendBookingEmailReminderJob;
use App\Models\Activity;
use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Notifications\ClientAppointmentReminderNotification;
use App\Support\BookingPublicUrl;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBookingEmailReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(bool $emailOptIn, ?string $email = 'cliente@test.test'): CalendarEvent
    {
        $org = Organization::query()->create([
            'name' => 'FadaStudio',
            'slug' => 'fada-email-'.($emailOptIn ? 'on' : 'off'),
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Aveiro Centro',
            'slug' => 'aveiro-email-'.($emailOptIn ? 'on' : 'off'),
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'name' => 'Ana Silva',
            'email' => $email,
            'notify_email_booking_reminders' => $emailOptIn,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        return CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Lembrete email',
            'start_at' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon')->utc(),
            'end_at' => Carbon::parse('2026-06-15 10:30:00', 'Europe/Lisbon')->utc(),
            'created_at' => Carbon::parse('2026-06-13 10:00:00', 'Europe/Lisbon')->utc(),
        ]);
    }

    public function test_opt_out_marks_failed_and_does_not_send(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        Notification::fake();
        $event = $this->makeEvent(false);

        $this->app->call([new SendBookingEmailReminderJob((int) $event->id), 'handle']);

        Notification::assertNothingSent();
        $event->refresh();
        $this->assertNotNull($event->booking_email_reminder_failed_at);
        $this->assertNull($event->booking_email_reminder_sent_at);
    }

    public function test_opt_in_sends_and_marks_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        Notification::fake();
        $event = $this->makeEvent(true);

        $this->app->call([new SendBookingEmailReminderJob((int) $event->id), 'handle']);

        Notification::assertSentOnDemand(ClientAppointmentReminderNotification::class);
        $event->refresh();
        $this->assertNotNull($event->booking_email_reminder_sent_at);
        $this->assertNull($event->booking_email_reminder_failed_at);
        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $event->getMorphClass())
                ->where('subject_id', $event->id)
                ->where('event', 'lembrete_email')
                ->where('description', 'Email de lembrete (dia anterior) enviado')
                ->exists()
        );
    }

    public function test_still_sends_when_status_already_notificado_after_sms(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        Notification::fake();
        $event = $this->makeEvent(true);
        $event->update([
            'status' => CalendarEvent::STATUS_NOTIFICADO,
            'booking_sms_reminder_sent_at' => now(),
        ]);

        $this->app->call([new SendBookingEmailReminderJob((int) $event->id), 'handle']);

        Notification::assertSentOnDemand(ClientAppointmentReminderNotification::class);
        $this->assertNotNull($event->fresh()->booking_email_reminder_sent_at);
    }

    public function test_notification_copy_mentions_brand_and_store(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        $event = $this->makeEvent(true);
        $mail = (new ClientAppointmentReminderNotification((int) $event->id))
            ->toMail((object) ['email' => 'cliente@test.test']);

        $this->assertStringContainsString('FadaStudio', $mail->subject);
        $this->assertStringNotContainsString('Aveiro Centro', $mail->subject);
        $rendered = implode("\n", array_map(
            static fn ($line) => is_string($line) ? $line : (string) $line,
            $mail->introLines
        ));
        $this->assertStringContainsString('Lembramos da sua marcação amanhã.', $rendered);
    }

    public function test_notification_includes_confirm_action_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'Europe/Lisbon'));
        $event = $this->makeEvent(true);
        $mail = (new ClientAppointmentReminderNotification((int) $event->id))
            ->toMail((object) ['email' => 'cliente@test.test']);

        $link = BookingSmsActionLink::query()
            ->where('calendar_event_id', $event->id)
            ->first();
        $this->assertNotNull($link);
        $this->assertSame('Confirmar marcação', $mail->actionText);
        $this->assertSame(
            BookingPublicUrl::smsConfirmUrl($link->token, $event->store?->slug),
            $mail->actionUrl
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
