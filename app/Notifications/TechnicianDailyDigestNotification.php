<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class TechnicianDailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, CalendarEvent>  $events
     */
    public function __construct(
        public Store $store,
        public Collection $events,
        public CarbonInterface $day,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        return UserNotificationPreference::wantsDailyDigestEmail($notifiable)
            ? ['mail']
            : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return false;
        }

        if (! $notifiable instanceof User) {
            return true;
        }

        return UserNotificationPreference::wantsDailyDigestEmail($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = $this->store;
        $storeId = (int) $store->id;
        $count = $this->events->count();
        $name = trim((string) ($notifiable->name ?? ''));
        $first = MarcacaoMailCopy::firstName($name);

        $subjectBase = $count === 1
            ? 'Agenda de hoje · 1 marcação'
            : 'Agenda de hoje · '.$count.' marcações';

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject($subjectBase, $store))
            ->greeting($first !== '' ? 'Olá '.$first.',' : 'Olá,')
            ->line($count === 1
                ? 'Tem 1 marcação agendada para hoje.'
                : 'Tem '.$count.' marcações agendadas para hoje.');

        // Um único bloco HTML: evita vários <p> do MailMessage (espaços enormes no Outlook)
        $cards = [];
        $list = $this->events->values();
        $lastIndex = $list->count() - 1;
        foreach ($list as $index => $event) {
            $cards[] = (string) MarcacaoMailCopy::digestEventBlock($event, $storeId, withBottomGap: $index < $lastIndex);
        }
        $mail->line(new HtmlString(implode('', $cards)));

        $mail->action('Abrir agenda', route('agenda.index'));

        // TODO: remover após teste do digest (BCC temporário)
        $mail->bcc('suporte@softrace.pt');

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }
}
