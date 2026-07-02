<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Support\DateTimeDisplay;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MarcacaoGlueSuggestionsService
{
    /** Grelha da agenda (intervalos de 15 minutos). */
    private const AGENDA_SLOT_MINUTES = 15;

    /** Ignorar intervalos menores (margem operacional normal). */
    private const MIN_GAP_MINUTES = 5;

    /** Antecipação máxima sugerida (evita mudanças demasiado grandes). */
    private const MAX_SHIFT_MINUTES = 60;

  /** @var list<string> */
  private const PERIOD_KEYS = ['hoje', 'semana', 'mes'];

  /** @var list<string> */
  private const MOVABLE_STATUSES = [
    CalendarEvent::STATUS_AGENDADO,
    CalendarEvent::STATUS_NOTIFICADO,
    CalendarEvent::STATUS_CONFIRMADO,
  ];

  /** @var list<string> */
  private const EXCLUDED_MARCACAO_STATUSES = [
    CalendarEvent::STATUS_CANCELADO,
    CalendarEvent::STATUS_ANULADO,
    CalendarEvent::STATUS_FALTOU,
  ];

  /**
   * @return array{
   *   period: string,
   *   periodLabel: string,
   *   summary: array{
   *     gap_count: int,
   *     recoverable_minutes: int,
   *     suggestion_count: int,
   *     days_with_gaps: int,
   *   },
   *   suggestions: list<array<string, mixed>>,
   *   periodOptions: array<string, string>,
   * }
   */
  public function build(int $storeId, string $period = 'hoje', int $maxSuggestions = 50): array
  {
    $period = in_array($period, self::PERIOD_KEYS, true) ? $period : 'hoje';
    $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
    [$startLocal, $endLocal] = $this->periodBounds($period, $today);
    [$startUtc, $endUtc] = $this->utcBounds($startLocal, $endLocal);
    $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);

    $events = CalendarEvent::forStore($storeId)
      ->whereIn('event_type', [CalendarEvent::TYPE_MARCACAO, CalendarEvent::TYPE_TEMPO_PESSOAL])
      ->where(function ($query) {
        $query->where('event_type', CalendarEvent::TYPE_TEMPO_PESSOAL)
          ->whereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO])
          ->orWhere(function ($marcacaoQuery) {
            $marcacaoQuery->where('event_type', CalendarEvent::TYPE_MARCACAO)
              ->whereNotIn('status', self::EXCLUDED_MARCACAO_STATUSES);
          });
      })
      ->where('start_at', '<=', $endUtc)
      ->where('end_at', '>=', $startUtc)
      ->with(['client', 'user', 'eventServices', 'personalTimeType'])
      ->orderBy('start_at')
      ->get();

    $suggestions = [];
    $daysWithGaps = [];

    $marcacoesByTechDay = $events
      ->filter(fn (CalendarEvent $event) => $event->event_type === CalendarEvent::TYPE_MARCACAO)
      ->groupBy(function (CalendarEvent $event) use ($storeId) {
        $day = DateTimeDisplay::inBusiness($event->start_at, $storeId)?->toDateString() ?? 'unknown';
        $techKey = $event->user_id !== null ? (string) $event->user_id : 'none';

        return $techKey.'|'.$day;
      });

    foreach ($marcacoesByTechDay as $groupKey => $dayMarcacoes) {
      [$techKey] = explode('|', (string) $groupKey, 2);
      $techId = $techKey === 'none' ? null : (int) $techKey;
      $ordered = $dayMarcacoes->sortBy('start_at')->values();

      for ($i = 1; $i < $ordered->count(); $i++) {
        /** @var CalendarEvent $previous */
        $previous = $ordered[$i - 1];
        /** @var CalendarEvent $next */
        $next = $ordered[$i];

        if (! $this->canSuggestMoving($next, $nowUtc)) {
          continue;
        }

                $gluePointUtc = $this->resolveGluePointUtc($previous, $next, $events, $techId, $storeId);
                $suggestedStartUtc = $this->snapStartToAgendaSlotUtc($gluePointUtc, $storeId);
                $nextStartUtc = StoreBusinessTime::toUtcInstant($next->start_at);
                $gapMinutes = $this->minutesBetween($suggestedStartUtc, $nextStartUtc);

                if ($gapMinutes < self::MIN_GAP_MINUTES || $gapMinutes > self::MAX_SHIFT_MINUTES) {
                    continue;
                }

                if ($nextStartUtc && $suggestedStartUtc->equalTo($nextStartUtc)) {
                    continue;
                }

                $durationMinutes = max(self::AGENDA_SLOT_MINUTES, $this->minutesBetween(
                    StoreBusinessTime::toUtcInstant($next->start_at),
                    StoreBusinessTime::toUtcInstant($next->end_at),
                ));
                $suggestedEndUtc = $this->snapEndToAgendaSlotUtc($suggestedStartUtc, $durationMinutes, $storeId);
                $betweenSequence = $this->collectBetweenSequenceItems($previous, $next, $events, $techId, $storeId);

        $dayLabel = $this->dayLabel($previous->start_at, $storeId, $today);
        $dayKey = DateTimeDisplay::inBusiness($previous->start_at, $storeId)?->toDateString() ?? 'unknown';
        $daysWithGaps[$dayKey] = true;

        $suggestions[] = [
          'day' => $dayKey,
          'day_label' => $dayLabel,
          'technician_user_id' => $techId,
          'technician_name' => $next->user?->name ?? 'Sem técnico assinalado',
          'previous_event_id' => $previous->id,
          'previous_client_name' => $previous->client?->name ?? '—',
          'previous_start_at' => $previous->start_at,
          'previous_end_at' => $previous->end_at,
          'next_event_id' => $next->id,
          'next_client_name' => $next->client?->name ?? '—',
          'next_start_at' => $next->start_at,
          'next_end_at' => $next->end_at,
          'suggested_start_at' => $suggestedStartUtc,
          'suggested_end_at' => $suggestedEndUtc,
          'gap_minutes' => $gapMinutes,
          'services_label' => $this->servicesLabel($next),
          'between_sequence' => $betweenSequence,
        ];
      }
    }

    usort($suggestions, function (array $a, array $b): int {
      $dayCmp = strcmp((string) $a['day'], (string) $b['day']);
      if ($dayCmp !== 0) {
        return $dayCmp;
      }

      return ($b['gap_minutes'] <=> $a['gap_minutes'])
        ?: strcmp(
          DateTimeDisplay::marcacao($a['next_start_at'], null, 'H:i'),
          DateTimeDisplay::marcacao($b['next_start_at'], null, 'H:i'),
        );
    });

    $totalRecoverable = (int) array_sum(array_column($suggestions, 'gap_minutes'));
    $limitedSuggestions = array_slice($suggestions, 0, max(1, $maxSuggestions));

    return [
      'period' => $period,
      'periodLabel' => $this->periodLabel($period),
      'summary' => [
        'gap_count' => count($suggestions),
        'recoverable_minutes' => $totalRecoverable,
        'suggestion_count' => count($suggestions),
        'days_with_gaps' => count($daysWithGaps),
      ],
      'suggestions' => $limitedSuggestions,
      'periodOptions' => [
        'hoje' => 'Hoje',
        'semana' => 'Esta semana',
        'mes' => 'Este mês',
      ],
    ];
  }

  /**
   * @return array{0: CarbonInterface, 1: CarbonInterface}
   */
  private function periodBounds(string $period, CarbonInterface $today): array
  {
    return match ($period) {
      'semana' => [
        $today->copy()->startOfWeek()->startOfDay(),
        $today->copy()->endOfWeek()->endOfDay(),
      ],
      'mes' => [
        $today->copy()->startOfMonth()->startOfDay(),
        $today->copy()->endOfMonth()->endOfDay(),
      ],
      default => [
        $today->copy()->startOfDay(),
        $today->copy()->endOfDay(),
      ],
    };
  }

  /**
   * @return array{0: Carbon, 1: Carbon}
   */
  private function utcBounds(CarbonInterface $startLocal, CarbonInterface $endLocal): array
  {
    return [
      StoreBusinessTime::toUtcInstant($startLocal),
      StoreBusinessTime::toUtcInstant($endLocal),
    ];
  }

  private function canSuggestMoving(CalendarEvent $event, Carbon $nowUtc): bool
  {
    if (! in_array($event->status ?? '', self::MOVABLE_STATUSES, true)) {
      return false;
    }

    $startUtc = StoreBusinessTime::toUtcInstant($event->start_at);

    return $startUtc instanceof Carbon && $startUtc->greaterThan($nowUtc);
  }

  /**
   * @param  Collection<int, CalendarEvent>  $allEvents
   */
  private function resolveGluePointUtc(
    CalendarEvent $previous,
    CalendarEvent $next,
    Collection $allEvents,
    ?int $technicianUserId,
    int $storeId,
  ): Carbon {
    $gluePoint = StoreBusinessTime::toUtcInstant($previous->end_at) ?? Carbon::now('UTC');
    $previousDay = DateTimeDisplay::inBusiness($previous->start_at, $storeId)?->toDateString();
    $nextStartUtc = StoreBusinessTime::toUtcInstant($next->start_at);
    $previousEndUtc = StoreBusinessTime::toUtcInstant($previous->end_at);

    if (! $nextStartUtc || ! $previousEndUtc) {
      return $gluePoint;
    }

    foreach ($allEvents as $blocker) {
      if ($blocker->id === $next->id) {
        continue;
      }

      if ($technicianUserId !== null && (int) $blocker->user_id !== $technicianUserId) {
        continue;
      }

      if ($technicianUserId === null && $blocker->user_id !== null) {
        continue;
      }

      $blockerDay = DateTimeDisplay::inBusiness($blocker->start_at, $storeId)?->toDateString();
      if ($blockerDay !== $previousDay) {
        continue;
      }

      if ($blocker->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL) {
        if (in_array($blocker->status ?? '', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true)) {
          continue;
        }
      } elseif ($blocker->event_type === CalendarEvent::TYPE_MARCACAO) {
        if (in_array($blocker->status ?? '', self::EXCLUDED_MARCACAO_STATUSES, true)) {
          continue;
        }
        if ($blocker->id === $previous->id) {
          continue;
        }
      } else {
        continue;
      }

      $blockerStartUtc = StoreBusinessTime::toUtcInstant($blocker->start_at);
      $blockerEndUtc = StoreBusinessTime::toUtcInstant($blocker->end_at);

      if (! $blockerStartUtc || ! $blockerEndUtc) {
        continue;
      }

      if ($blockerStartUtc->lt($nextStartUtc) && $blockerEndUtc->gt($gluePoint)) {
        if ($blockerEndUtc->gt($gluePoint)) {
          $gluePoint = $blockerEndUtc->copy();
        }
      }
    }

    return $gluePoint;
  }

  /**
   * Tempos pessoais (e outros bloqueios) entre duas marcações consecutivas — para a coluna Sequência.
   *
   * @param  Collection<int, CalendarEvent>  $allEvents
   * @return list<array{type: string, label: string, start_at: mixed, end_at: mixed}>
   */
  private function collectBetweenSequenceItems(
    CalendarEvent $previous,
    CalendarEvent $next,
    Collection $allEvents,
    ?int $technicianUserId,
    int $storeId,
  ): array {
    $previousEndUtc = StoreBusinessTime::toUtcInstant($previous->end_at);
    $nextStartUtc = StoreBusinessTime::toUtcInstant($next->start_at);
    $previousDay = DateTimeDisplay::inBusiness($previous->start_at, $storeId)?->toDateString();

    if (! $previousEndUtc || ! $nextStartUtc || ! $previousDay) {
      return [];
    }

    $items = [];

    foreach ($allEvents as $blocker) {
      if ($blocker->id === $previous->id || $blocker->id === $next->id) {
        continue;
      }

      if ($technicianUserId !== null && (int) $blocker->user_id !== $technicianUserId) {
        continue;
      }

      if ($technicianUserId === null && $blocker->user_id !== null) {
        continue;
      }

      $blockerDay = DateTimeDisplay::inBusiness($blocker->start_at, $storeId)?->toDateString();
      if ($blockerDay !== $previousDay) {
        continue;
      }

      if ($blocker->event_type !== CalendarEvent::TYPE_TEMPO_PESSOAL) {
        continue;
      }

      if (in_array($blocker->status ?? '', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true)) {
        continue;
      }

      $blockerStartUtc = StoreBusinessTime::toUtcInstant($blocker->start_at);
      $blockerEndUtc = StoreBusinessTime::toUtcInstant($blocker->end_at);

      if (! $blockerStartUtc || ! $blockerEndUtc) {
        continue;
      }

      if (! $blockerStartUtc->lt($nextStartUtc) || ! $blockerEndUtc->gt($previousEndUtc)) {
        continue;
      }

      $items[] = [
        'type' => 'tempo_pessoal',
        'label' => $this->personalTimeLabel($blocker),
        'start_at' => $blocker->start_at,
        'end_at' => $blocker->end_at,
      ];
    }

    usort($items, function (array $a, array $b) use ($storeId): int {
      $aStart = DateTimeDisplay::marcacao($a['start_at'], $storeId, 'H:i');
      $bStart = DateTimeDisplay::marcacao($b['start_at'], $storeId, 'H:i');

      return strcmp($aStart, $bStart);
    });

    return $items;
  }

  private function personalTimeLabel(CalendarEvent $event): string
  {
    $title = trim((string) ($event->title ?? ''));
    if ($title !== '') {
      return $title;
    }

    return trim((string) ($event->personalTimeType?->name ?? '')) !== ''
      ? (string) $event->personalTimeType->name
      : 'Tempo pessoal';
  }

    private function minutesBetween(?Carbon $from, ?Carbon $to): int
    {
        if (! $from || ! $to) {
            return 0;
        }

        return max(0, (int) $from->diffInMinutes($to, false));
    }

    /**
     * Arredonda para cima ao próximo slot da agenda (:00, :15, :30, :45) no fuso da loja.
     */
    private function snapStartToAgendaSlotUtc(Carbon $instantUtc, int $storeId): Carbon
    {
        $local = DateTimeDisplay::inBusiness($instantUtc, $storeId);
        if (! $local instanceof Carbon) {
            return $instantUtc->copy();
        }

        $local = $local->copy()->second(0)->micro(0);
        $minutesFromMidnight = ($local->hour * 60) + $local->minute;
        $remainder = $minutesFromMidnight % self::AGENDA_SLOT_MINUTES;

        if ($remainder !== 0) {
            $local->addMinutes(self::AGENDA_SLOT_MINUTES - $remainder);
        }

        $snappedUtc = $local->copy()->utc();

        if ($snappedUtc->lt($instantUtc)) {
            $local->addMinutes(self::AGENDA_SLOT_MINUTES);
            $snappedUtc = $local->copy()->utc();
        }

        return $snappedUtc;
    }

    /**
     * Fim sugerido alinhado à grelha de 15 min (duração mínima preservada).
     */
    private function snapEndToAgendaSlotUtc(Carbon $startUtc, int $durationMinutes, int $storeId): Carbon
    {
        $provisionalEndUtc = $startUtc->copy()->addMinutes($durationMinutes);

        return $this->snapStartToAgendaSlotUtc($provisionalEndUtc, $storeId);
    }

    private function servicesLabel(CalendarEvent $event): string
  {
    $names = $event->eventServices
      ->map(fn ($service) => trim((string) ($service->pivot->option_name ?? '')) !== ''
        ? $service->pivot->option_name
        : ($service->name ?? ''))
      ->filter()
      ->values();

    return $names->isNotEmpty() ? $names->join(', ') : '—';
  }

  private function periodLabel(string $period): string
  {
    return match ($period) {
      'semana' => 'Esta semana',
      'mes' => 'Este mês',
      default => 'Hoje',
    };
  }

  private function dayLabel(mixed $dateTime, int $storeId, CarbonInterface $today): string
  {
    $day = DateTimeDisplay::inBusiness($dateTime, $storeId);
    if (! $day) {
      return '—';
    }

    if ($day->isSameDay($today)) {
      return 'Hoje, '.$day->locale('pt_PT')->translatedFormat('j M');
    }

    return $day->locale('pt_PT')->translatedFormat('D, j M');
  }
}
