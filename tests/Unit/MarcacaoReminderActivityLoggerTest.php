<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Services\MarcacaoReminderActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcacaoReminderActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_sms_and_email_day_before_and_same_day(): void
    {
        $org = Organization::query()->create([
            'name' => 'Org Reminder Log',
            'slug' => 'org-reminder-log',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Reminder Log',
            'slug' => 'loja-reminder-log',
            'timezone' => 'Europe/Lisbon',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'name' => 'Cliente Reminder Log',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação reminder log',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);

        $logger = app(MarcacaoReminderActivityLogger::class);
        $logger->logSmsDayBefore($event);
        $logger->logSmsSameDay($event);
        $logger->logEmailDayBefore($event);
        $logger->logEmailSameDay($event);

        $descriptions = Activity::query()
            ->where('subject_type', $event->getMorphClass())
            ->where('subject_id', $event->id)
            ->whereIn('event', ['lembrete_sms', 'lembrete_email'])
            ->orderBy('id')
            ->pluck('description')
            ->all();

        $this->assertSame([
            'SMS de lembrete (dia anterior) enviado',
            'SMS de lembrete (mesmo dia) enviado',
            'Email de lembrete (dia anterior) enviado',
            'Email de lembrete (mesmo dia) enviado',
        ], $descriptions);

        $this->assertSame(2, Activity::query()->where('event', 'lembrete_sms')->count());
        $this->assertSame(2, Activity::query()->where('event', 'lembrete_email')->count());
    }
}
