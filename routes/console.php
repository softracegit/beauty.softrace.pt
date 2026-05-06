<?php

use App\Jobs\SendBookingReminderSmsJob;
use App\Models\CalendarEvent;
use App\Services\VendusApiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vendus:test-connection', function (VendusApiService $vendus) {
    $this->comment('A testar ligacao a API Vendus...');

    $result = $vendus->testConnection();

    if ($result['ok']) {
        $this->info($result['message'].' (HTTP '.$result['status'].')');

        return self::SUCCESS;
    }

    $this->error($result['message'].' (HTTP '.$result['status'].')');

    return self::FAILURE;
})->purpose('Testa autenticacao e acesso a API Vendus');

Artisan::command('booking:dispatch-sms-reminders', function () {
    $leadMinutes = max(1, (int) config('booking.sms_reminder_lead_minutes', 120));
    $graceMinutes = max(0, (int) config('booking.sms_reminder_grace_minutes', 5));
    $tz = (string) config('booking.business_timezone', config('app.timezone', 'UTC'));

    // Trabalha em minutos inteiros na timezone de negócio para evitar falhas por segundos.
    $targetMinute = CarbonImmutable::now($tz)->addMinutes($leadMinutes)->startOfMinute();
    $windowStartLocal = $targetMinute->subMinutes($graceMinutes);
    $windowEndLocal = $targetMinute->addMinute();

    $windowStart = $windowStartLocal->utc();
    $windowEnd = $windowEndLocal->utc();

    $count = 0;

    CalendarEvent::query()
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->whereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU])
        ->whereNull('booking_sms_reminder_sent_at')
        ->whereBetween('start_at', [$windowStart, $windowEnd])
        ->whereHas('client', function ($q): void {
            $q->where('notify_sms_booking_reminders', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '');
        })
        ->select(['id'])
        ->orderBy('id')
        ->chunkById(200, function ($events) use (&$count): void {
            foreach ($events as $event) {
                SendBookingReminderSmsJob::dispatch((int) $event->id);
                $count++;
            }
        });

    $this->info(sprintf(
        'SMS reminders despachados: %d (janela local %s - %s, lead=%d, grace=%d)',
        $count,
        $windowStartLocal->format('Y-m-d H:i'),
        $windowEndLocal->format('Y-m-d H:i'),
        $leadMinutes,
        $graceMinutes
    ));

    return self::SUCCESS;
})->purpose('Despacha SMS de lembrete para marcações próximas');

Schedule::command('booking:dispatch-sms-reminders')
    ->everyMinute()
    ->withoutOverlapping();
