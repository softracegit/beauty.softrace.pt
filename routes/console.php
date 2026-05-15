<?php

use App\Jobs\SendBookingReminderSmsJob;
use App\Models\CalendarEvent;
use App\Services\VendusApiService;
use App\Services\VendusPaymentMethodResolver;
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

Artisan::command('vendus:list-payment-methods', function (VendusPaymentMethodResolver $resolver) {
    if ((string) config('services.vendus.api_key') === '' || (string) config('services.vendus.base_url') === '') {
        $this->error('Configure VENDUS_API_KEY e VENDUS_BASE_URL no .env.');

        return self::FAILURE;
    }

    if ((bool) config('services.vendus.simulate', false)) {
        $this->warn('VENDUS_SIMULATE=true: a listagem ainda chama a API; em alternativa desative para testes reais.');
    }

    $rows = $resolver->listPaymentMethods();
    if ($rows === []) {
        $this->warn('Nenhum meio de pagamento devolvido (ou falha na API). Verifique os logs.');

        return self::FAILURE;
    }

    $this->table(
        ['id', 'title', 'type', 'status'],
        array_map(static function (array $r): array {
            return [
                (string) ($r['id'] ?? ''),
                (string) ($r['title'] ?? ''),
                (string) ($r['type'] ?? ''),
                (string) ($r['status'] ?? ''),
            ];
        }, $rows)
    );

    $this->line('');
    $this->comment('Mapeamento interno: dinheiro→NU, mbway→MBWAY, multibanco→MB, transferencia→TB, cartao→CC/CD, outro→OU.');
    $this->comment('Override fixo no .env: VENDUS_PAYMENT_METHOD_ID=<id>');

    return self::SUCCESS;
})->purpose('Lista meios de pagamento Vendus (ids para FR / debug)');

Artisan::command('booking:dispatch-sms-reminders', function () {
    $tz = (string) config('booking.business_timezone', config('app.timezone', 'UTC'));
    $nowLocal = CarbonImmutable::now($tz);
    $startHour = (int) config('booking.sms_reminder_day_before_send_start_hour', 8);
    $endHour = (int) config('booking.sms_reminder_day_before_send_end_hour', 22);
    $maxPerRun = max(1, (int) config('booking.sms_reminder_max_per_run', 15));

    if ($endHour > $startHour) {
        $hour = (int) $nowLocal->format('G');
        if ($hour < $startHour || $hour >= $endHour) {
            $this->comment(sprintf(
                'SMS lembrete: fora da janela de envio (hora local %d; permitido %d–%d).',
                $hour,
                $startHour,
                $endHour
            ));

            return self::SUCCESS;
        }
    }

    // Início do «amanhã» civil na timezone de negócio → intervalo em UTC na BD.
    $tomorrowStartLocal = $nowLocal->addDay()->startOfDay();
    $tomorrowEndLocal = $tomorrowStartLocal->addDay();
    $windowStartUtc = $tomorrowStartLocal->utc();
    $windowEndUtc = $tomorrowEndLocal->utc();

    $sameDayCreationSkip = static function (CalendarEvent $event) use ($tz): bool {
        if ($event->start_at === null || $event->created_at === null) {
            return false;
        }

        return $event->start_at->copy()->timezone($tz)->toDateString()
            === $event->created_at->copy()->timezone($tz)->toDateString();
    };

    $count = 0;
    $skippedSameDay = 0;

    CalendarEvent::query()
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', CalendarEvent::STATUS_AGENDADO)
        ->whereNull('booking_sms_reminder_sent_at')
        ->where('start_at', '>=', $windowStartUtc)
        ->where('start_at', '<', $windowEndUtc)
        ->whereHas('client', function ($q): void {
            $q->where('notify_sms_booking_reminders', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '');
        })
        ->select(['id', 'start_at', 'created_at'])
        ->orderBy('id')
        ->chunkById(100, function ($events) use (&$count, &$skippedSameDay, $maxPerRun, $sameDayCreationSkip): bool {
            foreach ($events as $event) {
                if ($count >= $maxPerRun) {
                    return false;
                }
                if ($sameDayCreationSkip($event)) {
                    $skippedSameDay++;

                    continue;
                }
                SendBookingReminderSmsJob::dispatch((int) $event->id);
                $count++;
            }

            return true;
        });

    $this->info(sprintf(
        'SMS lembretes despachados: %d (dia da marcação = amanhã %s; TZ=%s; máx/run=%d; ignorados marcação-no-mesmo-dia=%d)',
        $count,
        $tomorrowStartLocal->format('Y-m-d'),
        $tz,
        $maxPerRun,
        $skippedSameDay
    ));

    return self::SUCCESS;
})->purpose('Despacha SMS de lembrete no dia anterior à marcação (timezone de negócio)');

Schedule::command('booking:dispatch-sms-reminders')
    ->everyMinute()
    ->withoutOverlapping();
