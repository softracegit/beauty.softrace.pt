<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\TechnicianDailyDigestNotification;
use App\Support\StoreBusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendTechnicianDailyDigestCommand extends Command
{
    protected $signature = 'staff:send-daily-appointment-digest
                            {--store= : ID da loja (opcional)}
                            {--dry-run : Listar destinatários sem enviar}';

    protected $description = 'Envia email matinal às técnicas com as marcações do dia (só se tiverem pelo menos uma)';

    public function handle(): int
    {
        $storeOpt = $this->option('store');
        if ($storeOpt !== null && $storeOpt !== '' && ! ctype_digit((string) $storeOpt)) {
            $this->error('A opção --store deve ser um ID numérico.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stores = Store::query()
            ->when($storeOpt !== null && $storeOpt !== '', fn ($q) => $q->whereKey((int) $storeOpt))
            ->orderBy('id')
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('Nenhuma loja encontrada.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skippedNoEmail = 0;
        $skippedOptOut = 0;

        foreach ($stores as $store) {
            $storeId = (int) $store->id;
            $day = StoreBusinessTime::nowForStore($storeId);
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $events = CalendarEvent::query()
                ->forStore($storeId)
                ->countableForDashboard()
                ->whereNotNull('user_id')
                ->whereBetween('start_at', [$dayStart, $dayEnd])
                ->with(['client', 'eventServices', 'eventServiceItems.service', 'store'])
                ->orderBy('start_at')
                ->get();

            if ($events->isEmpty()) {
                $this->comment(sprintf(
                    'Loja #%d (%s): sem marcações em %s.',
                    $storeId,
                    $store->name,
                    $day->toDateString()
                ));

                continue;
            }

            $grouped = $events->groupBy(fn (CalendarEvent $ev) => (int) $ev->user_id);

            foreach ($grouped as $userId => $userEvents) {
                $user = User::query()->find($userId);
                if (! $user) {
                    continue;
                }

                $email = trim((string) ($user->email ?? ''));
                if ($email === '') {
                    $skippedNoEmail++;

                    continue;
                }

                if (! UserNotificationPreference::wantsDailyDigestEmail($user)) {
                    $skippedOptOut++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] Loja #%d → %s <%s>: %d marcação(ões) em %s',
                        $storeId,
                        $user->name,
                        $email,
                        $userEvents->count(),
                        $day->toDateString()
                    ));
                    $sent++;

                    continue;
                }

                Notification::send(
                    $user,
                    new TechnicianDailyDigestNotification($store, $userEvents->values(), $day->copy())
                );
                $sent++;
            }
        }

        $this->info(sprintf(
            'Digest diário: %d envio(s)%s; sem email=%d; opt-out=%d.',
            $sent,
            $dryRun ? ' (dry-run)' : '',
            $skippedNoEmail,
            $skippedOptOut
        ));

        return self::SUCCESS;
    }
}
