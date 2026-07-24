<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\DateTimeDisplay;
use App\Support\MarcacaoMailCopy;
use App\Support\StoreMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $fromPublicBooking  Marcação em /booking: mailer "booking" (sem redirecionamento mail.to em local/staging).
     * @param  bool  $forReception  Destinatário receção: sininho + email próprio (sem CC).
     * @param  list<string>  $servicesAdded
     * @param  list<string>  $servicesRemoved
     */
    public function __construct(
        public int $calendarEventId,
        public string $type,
        public ?string $previousStatus = null,
        public bool $fromPublicBooking = false,
        public bool $forReception = false,
        public ?string $previousStartIso = null,
        public ?string $previousEndIso = null,
        public ?string $previousTechnicianName = null,
        public array $servicesAdded = [],
        public array $servicesRemoved = [],
    ) {}

    public function via(object $notifiable): array
    {
        if ($this->forReception) {
            $channels = [];
            if ($this->shouldReceiveBell()) {
                $channels[] = 'database';
            }
            if ($this->receptionShouldReceiveMail()) {
                $channels[] = 'mail';
            }

            return $channels;
        }

        if (! $notifiable instanceof User) {
            return ['database', 'mail'];
        }

        $channels = UserNotificationPreference::channelsForMarcacaoNotification($notifiable, $this->type);

        // Sininho de status_changed: só cancelado. Email: cancelado + faltou.
        if ($this->type === 'status_changed') {
            if (in_array('database', $channels, true) && ! $this->shouldReceiveBell()) {
                $channels = array_values(array_filter($channels, fn (string $c): bool => $c !== 'database'));
            }
            if (in_array('mail', $channels, true) && ! $this->isCancelOrMissedMail()) {
                $channels = array_values(array_filter($channels, fn (string $c): bool => $c !== 'mail'));
            }
        }

        return $channels;
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($this->forReception) {
            if ($channel === 'database') {
                return $this->shouldReceiveBell();
            }
            if ($channel === 'mail') {
                return $this->receptionShouldReceiveMail();
            }

            return false;
        }

        if ($channel === 'database' && $this->type === 'status_changed' && ! $this->shouldReceiveBell()) {
            return false;
        }

        if ($channel === 'mail' && $this->type === 'status_changed' && ! $this->isCancelOrMissedMail()) {
            return false;
        }

        if (! $notifiable instanceof User) {
            return true;
        }

        return UserNotificationPreference::wantsMarcacaoChannel($notifiable, $this->type, $channel);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $event = $this->loadEvent();

        return [
            'title' => $this->buildTitle($event),
            'body' => $this->buildBellBody($event),
            'url' => route('agenda.index', ['event' => $event->id]),
            'calendar_event_id' => $event->id,
            'type' => $this->type,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->loadEvent();
        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $name = trim((string) ($notifiable->name ?? ''));

        $mail = match ($this->type) {
            'assigned' => $this->mailAssigned($event, $store, $storeId, $name),
            'reassigned' => $this->mailReassigned($event, $store, $storeId, $name),
            'rescheduled' => $this->mailRescheduled($event, $store, $storeId, $name),
            'status_changed' => $this->mailStatusTerminal($event, $store, $storeId, $name),
            default => $this->mailAssigned($event, $store, $storeId, $name),
        };

        if ($this->fromPublicBooking) {
            $mail->mailer('booking');
        }

        return StoreMailBranding::applyToMailMessage($mail, $store);
    }

    private function mailAssigned(
        CalendarEvent $event,
        mixed $store,
        ?int $storeId,
        string $name,
    ): MailMessage {
        $subject = MarcacaoMailCopy::subject('Nova marcação', $store);
        $intro = $this->forReception
            ? 'A seguinte marcação foi criada'
            : 'A seguinte marcação foi criada para si.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting($this->greeting($name))
            ->line($intro)
            ->line(MarcacaoMailCopy::block([
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]))
            ->line(MarcacaoMailCopy::block([
                'Cliente: '.MarcacaoMailCopy::clientName($event),
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
                'Origem: '.MarcacaoMailCopy::originLabel($event),
            ]))
            ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));
    }

    private function mailReassigned(
        CalendarEvent $event,
        mixed $store,
        ?int $storeId,
        string $name,
    ): MailMessage {
        if ($this->forReception) {
            return (new MailMessage)
                ->subject(MarcacaoMailCopy::subject('Marcação transferida de técnica', $store))
                ->greeting($this->greeting($name))
                ->line('A seguinte marcação foi transferida de técnica.')
                ->line(MarcacaoMailCopy::block([
                    'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                    'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
                ]))
                ->line(MarcacaoMailCopy::block([
                    'Cliente: '.MarcacaoMailCopy::clientName($event),
                    'Serviço: '.MarcacaoMailCopy::servicesLine($event),
                    'Técnica anterior: '.$this->previousTechnicianLabel(),
                    'Nova técnica: '.$this->currentTechnicianLabel($event),
                ]))
                ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));
        }

        return (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Marcação transferida para si', $store))
            ->greeting($this->greeting($name))
            ->line('A seguinte marcação foi transferida para si.')
            ->line(MarcacaoMailCopy::block([
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]))
            ->line(MarcacaoMailCopy::block([
                'Cliente: '.MarcacaoMailCopy::clientName($event),
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
            ]))
            ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));
    }

    private function mailRescheduled(
        CalendarEvent $event,
        mixed $store,
        ?int $storeId,
        string $name,
    ): MailMessage {
        $prevStart = MarcacaoMailCopy::parseIso($this->previousStartIso);
        $datesChanged = MarcacaoMailCopy::startsDiffer($prevStart, $event->start_at, $storeId);

        $scheduleLines = $datesChanged
            ? [
                'Data anterior: '.MarcacaoMailCopy::dateTime($prevStart, $storeId),
                'Nova Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ]
            : [
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Duração: '.MarcacaoMailCopy::duration($event->start_at, $event->end_at),
            ];

        $detailLines = [
            'Cliente: '.MarcacaoMailCopy::clientName($event),
            ...MarcacaoMailCopy::serviceChangeLines($this->servicesAdded, $this->servicesRemoved),
            'Serviço: '.MarcacaoMailCopy::servicesLine($event),
        ];

        return (new MailMessage)
            ->subject(MarcacaoMailCopy::subject('Marcação alterada', $store))
            ->greeting($this->greeting($name))
            ->line('A seguinte marcação foi alterada.')
            ->line(MarcacaoMailCopy::block($scheduleLines))
            ->line(MarcacaoMailCopy::block($detailLines))
            ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));
    }

    private function mailStatusTerminal(
        CalendarEvent $event,
        mixed $store,
        ?int $storeId,
        string $name,
    ): MailMessage {
        $isMissed = ($event->status ?? '') === CalendarEvent::STATUS_FALTOU;

        $mail = (new MailMessage)
            ->subject(MarcacaoMailCopy::subject(
                $isMissed ? 'Marcação marcada como falta' : 'Marcação cancelada',
                $store,
            ))
            ->greeting($this->greeting($name))
            ->line($isMissed
                ? 'A seguinte marcação foi marcada como Falta.'
                : 'A seguinte marcação foi cancelada.')
            ->line(MarcacaoMailCopy::block([
                'Data: '.MarcacaoMailCopy::dateTime($event->start_at, $storeId),
                'Motivo: '.MarcacaoMailCopy::reason($event),
            ]))
            ->line(MarcacaoMailCopy::block([
                'Cliente: '.MarcacaoMailCopy::clientName($event),
                'Serviço: '.MarcacaoMailCopy::servicesLine($event),
            ]))
            ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));

        return $mail;
    }

    private function greeting(string $name): string
    {
        return $name !== '' ? 'Olá '.$name.',' : 'Olá,';
    }

    private function previousTechnicianLabel(): string
    {
        $name = trim((string) ($this->previousTechnicianName ?? ''));

        return $name !== '' ? $name : '-';
    }

    private function currentTechnicianLabel(CalendarEvent $event): string
    {
        $event->loadMissing('user');
        $name = trim((string) ($event->user?->name ?? ''));

        return $name !== '' ? $name : '-';
    }

    private function shouldReceiveBell(): bool
    {
        if ($this->type !== 'status_changed') {
            return true;
        }

        return ($this->loadEvent()->status ?? '') === CalendarEvent::STATUS_CANCELADO;
    }

    private function receptionShouldReceiveMail(): bool
    {
        if (in_array($this->type, ['assigned', 'reassigned', 'rescheduled'], true)) {
            return true;
        }

        return $this->type === 'status_changed' && $this->isCancelOrMissedMail();
    }

    private function isCancelOrMissedMail(): bool
    {
        $status = $this->loadEvent()->status ?? '';

        return in_array($status, [
            CalendarEvent::STATUS_CANCELADO,
            CalendarEvent::STATUS_FALTOU,
        ], true);
    }

    private function loadEvent(): CalendarEvent
    {
        return CalendarEvent::query()
            ->with(['client', 'service', 'eventServices', 'store', 'user'])
            ->findOrFail($this->calendarEventId);
    }

    private function buildTitle(CalendarEvent $event): string
    {
        $client = MarcacaoMailCopy::clientName($event, 'Cliente não indicado');

        if ($this->forReception) {
            return match ($this->type) {
                'assigned' => 'Nova marcação <strong>'.e($client).'</strong>',
                'reassigned' => 'Marcação transferida de técnica',
                'rescheduled' => 'Marcação alterada',
                'status_changed' => 'Marcação cancelada',
                default => 'Atualização na agenda',
            };
        }

        return match ($this->type) {
            'assigned' => 'Nova marcação <strong>'.e($client).'</strong>',
            'reassigned' => 'Marcação transferida para si',
            'rescheduled' => 'Marcação alterada',
            'status_changed' => 'Marcação cancelada',
            default => 'Atualização na agenda',
        };
    }

    /**
     * Corpo HTML do sininho (nomes em &lt;strong&gt;, linhas com &lt;br&gt;).
     */
    private function buildBellBody(CalendarEvent $event): string
    {
        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $client = MarcacaoMailCopy::clientName($event, 'Cliente não indicado');
        $services = MarcacaoMailCopy::servicesLine($event, 'Serviço não indicado');
        $clientLine = 'Cliente <strong>'.e($client).'</strong>';
        $techName = $this->currentTechnicianLabel($event);

        if ($this->type === 'assigned') {
            return implode('<br>', [
                e($this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                e($techName).' ('.e($services).')',
            ]);
        }

        if ($this->type === 'reassigned' && $this->forReception) {
            return implode('<br>', [
                'Passou de <strong>'.e($this->previousTechnicianLabel()).'</strong> para <strong>'.e($techName).'</strong>',
                $clientLine,
                e($this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                e($services),
            ]);
        }

        if ($this->type === 'rescheduled') {
            $prevStart = MarcacaoMailCopy::parseIso($this->previousStartIso);
            $prevEnd = MarcacaoMailCopy::parseIso($this->previousEndIso);
            $datesChanged = MarcacaoMailCopy::startsDiffer($prevStart, $event->start_at, $storeId);

            if ($datesChanged) {
                return implode('<br>', [
                    $clientLine,
                    e('Nova data: '.$this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                    e('Data anterior: '.$this->dateWithDuration($prevStart, $prevEnd, $storeId)),
                    e($services),
                ]);
            }

            return implode('<br>', [
                $clientLine,
                e($this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                e($services),
            ]);
        }

        if ($this->type === 'status_changed') {
            return implode('<br>', [
                $clientLine,
                e($this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                e('Motivo: '.MarcacaoMailCopy::reason($event)),
                e($services),
            ]);
        }

        if ($this->type === 'reassigned') {
            return implode('<br>', [
                $clientLine,
                e($this->dateWithDuration($event->start_at, $event->end_at, $storeId)),
                e($services),
            ]);
        }

        // Fallback — tipo inesperado
        $sep = $this->forReception ? ' — ' : ' - ';

        return e('Marcação ('.$client.')'.$sep.$services.'.')
            .'<br>'.e('Início: '.DateTimeDisplay::marcacao($event->start_at, $storeId))
            .'<br>'.e('Fim: '.DateTimeDisplay::marcacao($event->end_at, $storeId));
    }

    private function dateWithDuration(
        mixed $start,
        mixed $end,
        ?int $storeId,
    ): string {
        return MarcacaoMailCopy::dateTime($start, $storeId)
            .' - duração '
            .MarcacaoMailCopy::duration($start, $end);
    }
}
