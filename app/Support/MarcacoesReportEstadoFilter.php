<?php

namespace App\Support;

use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Builder;

class MarcacoesReportEstadoFilter
{
    public const TUDO = 'tudo';

    public const ATIVAS = 'ativas';

    public const NAO_REALIZADAS = 'nao_realizadas';

    public const TEMPO_PESSOAL = 'tempo_pessoal';

    public static function default(): string
    {
        return self::ATIVAS;
    }

    public static function resolve(?string $value): string
    {
        if ($value === null || $value === '') {
            return self::default();
        }

        if (self::isValid($value)) {
            return $value;
        }

        return self::default();
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::allValues(), true);
    }

    /** @return list<string> */
    public static function allValues(): array
    {
        return array_merge(
            [self::TUDO, self::ATIVAS, self::NAO_REALIZADAS, self::TEMPO_PESSOAL],
            array_keys(self::ativasIndividualOptions()),
            array_keys(self::naoRealizadasIndividualOptions()),
        );
    }

    /** @return list<string> */
    public static function ativasStatuses(): array
    {
        return [
            CalendarEvent::STATUS_AGENDADO,
            CalendarEvent::STATUS_NOTIFICADO,
            CalendarEvent::STATUS_CONFIRMADO,
            CalendarEvent::STATUS_CHEGOU,
            CalendarEvent::STATUS_INICIADO,
            CalendarEvent::STATUS_TERMINADO,
            CalendarEvent::STATUS_COMPLETO,
        ];
    }

    /** @return list<string> */
    public static function naoRealizadasStatuses(): array
    {
        return [
            CalendarEvent::STATUS_CANCELADO,
            CalendarEvent::STATUS_FALTOU,
            CalendarEvent::STATUS_ANULADO,
        ];
    }

    /** @return array<string, string> */
    public static function ativasIndividualOptions(): array
    {
        return [
            CalendarEvent::STATUS_AGENDADO => 'Agendado',
            CalendarEvent::STATUS_CONFIRMADO => 'Confirmado',
            CalendarEvent::STATUS_CHEGOU => 'Chegou',
            CalendarEvent::STATUS_INICIADO => 'Iniciado',
            CalendarEvent::STATUS_COMPLETO => 'Pago',
        ];
    }

    /** @return array<string, string> */
    public static function naoRealizadasIndividualOptions(): array
    {
        return [
            CalendarEvent::STATUS_CANCELADO => 'Cancelado',
            CalendarEvent::STATUS_FALTOU => 'Faltou',
        ];
    }

    public static function label(string $filter): string
    {
        return match ($filter) {
            self::TUDO => 'Ver tudo',
            self::ATIVAS => 'Marcações realizadas / por realizar',
            self::NAO_REALIZADAS => 'Marcações não realizadas',
            self::TEMPO_PESSOAL => 'Tempos pessoais',
            default => CalendarEvent::statuses()[$filter] ?? $filter,
        };
    }

    public static function eventRowStatusLabel(CalendarEvent $event): string
    {
        if ($event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL) {
            return 'Tempo pessoal';
        }

        return CalendarEvent::statuses()[$event->status] ?? (string) $event->status;
    }

    public static function eventRowServicesLabel(CalendarEvent $event): string
    {
        if ($event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL) {
            $name = trim((string) ($event->personalTimeType?->name ?? ''));

            return $name !== '' ? $name : (trim((string) ($event->title ?? '')) ?: '—');
        }

        return $event->eventServiceItems
            ->map(function ($es) {
                $optionName = trim((string) ($es->option_name ?? ''));

                return $optionName !== '' ? $optionName : ($es->service?->name ?? null);
            })
            ->filter()
            ->implode(', ') ?: '—';
    }

    /**
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public static function apply(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            self::TUDO => $query->whereIn('event_type', [
                CalendarEvent::TYPE_MARCACAO,
                CalendarEvent::TYPE_TEMPO_PESSOAL,
            ]),
            self::TEMPO_PESSOAL => $query->where('event_type', CalendarEvent::TYPE_TEMPO_PESSOAL),
            self::ATIVAS => $query
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->whereIn('status', self::ativasStatuses()),
            self::NAO_REALIZADAS => $query
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->whereIn('status', self::naoRealizadasStatuses()),
            default => $query
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->where('status', $filter),
        };
    }
}
