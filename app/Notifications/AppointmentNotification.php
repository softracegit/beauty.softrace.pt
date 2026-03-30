<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $calendarEventId,
        public string $type,
        public ?string $previousStatus = null,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database', 'mail'];
        }

        return UserNotificationPreference::channelsForMarcacaoNotification($notifiable, $this->type);
    }

    /**
     * Chamado no envio real de cada canal (inclui jobs em fila). Garante que sininho/email
     * respeitam a preferência atual mesmo que o job tenha sido enfileirado antes.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
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
        $title = $this->buildTitle($event);
        $body = $this->buildBody($event);
        $bodyWithSchedule = $body."\n".$this->formatStartLine($event)."\n".$this->formatEndLine($event);

        return [
            'title' => $title,
            'body' => $bodyWithSchedule,
            'url' => route('agenda.index', ['event' => $event->id]),
            'calendar_event_id' => $event->id,
            'type' => $this->type,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->loadEvent();
        $title = $this->buildTitle($event);
        $body = $this->buildBody($event);

        return (new MailMessage)
            ->subject($title)
            ->greeting('Olá '.($notifiable->name ?? '').',')
            ->line($body)
            ->line($this->formatStartLine($event))
            ->line($this->formatEndLine($event))
            ->action('Abrir agenda', route('agenda.index', ['event' => $event->id]));
    }

    private function loadEvent(): CalendarEvent
    {
        return CalendarEvent::query()
            ->with(['client', 'service', 'eventServices'])
            ->findOrFail($this->calendarEventId);
    }

    private function buildTitle(CalendarEvent $event): string
    {
        return match ($this->type) {
            'assigned' => 'Nova marcação atribuída a si',
            'reassigned' => 'Marcação transferida para si',
            'rescheduled' => 'Marcação reagendada',
            'status_changed' => 'Estado da marcação atualizado',
            default => 'Atualização na agenda',
        };
    }

    private function buildBody(CalendarEvent $event): string
    {
        $client = $event->client?->name ?? 'Cliente não indicado';

        return match ($this->type) {
            'assigned' => "Foi-lhe atribuída uma marcação: {$client}.",
            'reassigned' => "Uma marcação foi transferida para si: {$client}.",
            'rescheduled' => "A data ou hora da marcação de {$client} foi alterada.",
            'status_changed' => $this->buildStatusChangedBody($event, $client),
            default => "Marcação ({$client}).",
        };
    }

    private function buildStatusChangedBody(CalendarEvent $event, string $client): string
    {
        $statuses = CalendarEvent::statuses();
        $new = $event->status ?? CalendarEvent::STATUS_AGENDADO;
        $newLabel = $statuses[$new] ?? $new;
        $oldLabel = null;
        if ($this->previousStatus !== null) {
            $oldLabel = $statuses[$this->previousStatus] ?? $this->previousStatus;
        }

        if ($oldLabel) {
            return "O estado da marcação de {$client} passou de «{$oldLabel}» para «{$newLabel}».";
        }

        return "O estado da marcação de {$client} é agora «{$newLabel}».";
    }

    private function formatStartLine(CalendarEvent $event): string
    {
        $tz = config('app.timezone');
        if (! $event->start_at) {
            return 'Início: —';
        }
        $start = Carbon::parse($event->start_at)->timezone($tz)->format('d/m/Y H:i');

        return "Início: {$start}";
    }

    private function formatEndLine(CalendarEvent $event): string
    {
        $tz = config('app.timezone');
        if (! $event->end_at) {
            return 'Fim: —';
        }
        $end = Carbon::parse($event->end_at)->timezone($tz)->format('d/m/Y H:i');

        return "Fim: {$end}";
    }
}
