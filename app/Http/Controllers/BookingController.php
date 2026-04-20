<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\CrmSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /** Horário da loja para marcação online (cruza com o horário do técnico). */
    private const STORE_OPEN = '09:00';

    private const STORE_CLOSE = '20:00';

    /**
     * Lista de serviços para marcação online (estilo Square).
     * Nota: as colunas is_active / is_visible_online foram removidas de `services` na migration
     * 2026_02_18_193135; mostramos todos os serviços. Para filtrar no futuro, volta a adicionar
     * colunas na BD ou usa outro critério.
     */
    public function index(): View
    {
        $categories = Category::query()
            ->with([
                'services' => function ($q) {
                    $q->orderBy('sort_order')
                        ->with(['options' => function ($oq) {
                            $oq->orderBy('sort_order');
                        }]);
                },
            ])
            ->whereHas('services')
            ->orderBy('sort_order')
            ->get();

        return view('booking.index', [
            'categories' => $categories,
            'businessName' => config('app.name'),
        ]);
    }

    /**
     * Passo seguinte no fluxo (data / hora — a completar no servidor).
     */
    public function datetime(): View
    {
        return view('booking.datetime', [
            'businessName' => config('app.name'),
        ]);
    }

    /**
     * Horários livres (JSON) para o passo data/hora: loja 9h–20h se "qualquer staff";
     * caso contrário, interseção loja ∩ horário semanal do agente, excluindo eventos na agenda.
     *
     * Query: date=Y-m-d, agent_id=any|<agente id>, duration=minutos (soma dos serviços no carrinho).
     */
    public function availability(Request $request): JsonResponse
    {
        $dateStr = (string) $request->query('date', '');
        $agentKey = $request->query('agent_id', 'any');
        $duration = (int) $request->query('duration', 30);
        $duration = max(5, min(24 * 60, $duration));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['slots' => []], 422);
        }

        /** Fuso da loja / cliente — ver config/booking.php (não altera APP_TIMEZONE do CRM). */
        $tz = (string) config('booking.business_timezone');
        try {
            $day = Carbon::parse($dateStr, $tz)->startOfDay()->timezone($tz);
        } catch (\Throwable) {
            return response()->json(['slots' => []], 422);
        }

        if ($day->lt(now($tz)->startOfDay())) {
            return response()->json(['slots' => []]);
        }

        $storeStart = Agent::timeStringToMinutes(self::STORE_OPEN);
        $storeEnd = Agent::timeStringToMinutes(self::STORE_CLOSE);

        if ($agentKey === 'any' || $agentKey === '' || $agentKey === null) {
            $slots = $this->buildAvailableSlots($storeStart, $storeEnd, $duration, []);

            return response()->json(['slots' => $slots]);
        }

        if (! ctype_digit((string) $agentKey)) {
            return response()->json(['slots' => []], 422);
        }

        $agent = Agent::query()
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', function ($q): void {
                $q->whereIn('role', [
                    User::ROLE_PRESTADOR,
                    User::ROLE_TECNICO,
                ]);
            })
            ->with('user:id')
            ->find((int) $agentKey);

        if (! $agent || ! $agent->user_id) {
            return response()->json(['slots' => []], 404);
        }

        $dowKey = $this->carbonToWeekdayKey($day);
        $window = $this->resolveAgentDayWindow($agent->weekly_schedule, $dowKey, $storeStart, $storeEnd);
        if ($window === null) {
            return response()->json(['slots' => []]);
        }

        $busy = $this->busyIntervalsForUserOnDay((int) $agent->user_id, $day);
        $slots = $this->buildAvailableSlots($window[0], $window[1], $duration, $busy);

        return response()->json(['slots' => $slots]);
    }

    /**
     * Escolha de técnica (passo intermédio entre serviços e data/hora).
     */
    public function technician(): View
    {
        $technicians = Agent::query()
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', function ($q): void {
                $q->whereIn('role', [
                    User::ROLE_PRESTADOR,
                    User::ROLE_TECNICO,
                ]);
            })
            ->with(['services:id'])
            ->orderBy('name')
            ->get()
            ->map(function (Agent $agent): array {
                return [
                    'id' => (int) $agent->id,
                    'name' => (string) $agent->name,
                    'specialization' => Agent::specializationLabel($agent->specialization) ?? 'Técnica',
                    'avatar' => $agent->avatar ? asset('storage/'.$agent->avatar) : null,
                    'serviceIds' => $agent->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ];
            })
            ->values();

        return view('booking.technician', [
            'businessName' => config('app.name'),
            'technicians' => $technicians,
        ]);
    }

    /**
     * Passo 3 do fluxo público (placeholder).
     */
    public function step3(): View
    {
        $user = auth()->user();
        $isBookingClient = $user instanceof User && $user->isBookingClient();
        $client = $isBookingClient ? $user->loadMissing('client')->client : null;
        $onlineBookingPaymentRequired = CrmSetting::onlineBookingPaymentRequired();

        return view('booking.step3', [
            'businessName' => config('app.name'),
            'bookingClientUser' => $isBookingClient ? $user : null,
            'bookingClient' => $client,
            'acessoUrl' => route('booking.acesso'),
            'definirPasswordUrl' => $isBookingClient ? route('booking.conta.password.edit') : null,
            'onlineBookingPaymentRequired' => $onlineBookingPaymentRequired,
            'bookingPaymentIntentUrl' => route('booking.payment.intent'),
            'bookingPaymentCompleteUrl' => route('booking.payment.complete'),
            'bookingConfirmWithoutPaymentUrl' => route('booking.confirm.without_payment'),
        ]);
    }

    /**
     * Passo seguinte após escolher serviço (data / técnico — a completar).
     */
    public function showService(Service $service): View
    {
        return view('booking.service', [
            'service' => $service,
            'businessName' => config('app.name'),
        ]);
    }

    /**
     * Confirmação após marcação online criada com sucesso.
     */
    public function confirm(): View
    {
        return view('booking.confirm', [
            'businessName' => config('app.name'),
            'primeiraMarcacao' => request()->boolean('primeira_marcacao'),
        ]);
    }

    /**
     * Página "A minha conta" (cliente de marcação — middleware auth + booking.client).
     */
    public function account(Request $request): View
    {
        return view('booking.conta.index', [
            'businessName' => config('app.name'),
            'user' => $request->user(),
        ]);
    }

    private function carbonToWeekdayKey(Carbon $day): string
    {
        $map = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];

        return $map[$day->dayOfWeekIso] ?? 'mon';
    }

    /**
     * @return array{0: int, 1: int}|null Minutos desde meia-noite [início, fim) da janela útil.
     */
    private function resolveAgentDayWindow(?array $weeklySchedule, string $dayKey, int $storeStartMin, int $storeEndMin): ?array
    {
        $defaultDay = ['enabled' => true, 'start' => self::STORE_OPEN, 'end' => self::STORE_CLOSE];
        if (! is_array($weeklySchedule)) {
            $day = $defaultDay;
        } else {
            $v = $weeklySchedule[$dayKey] ?? null;
            if (! is_array($v)) {
                $day = $defaultDay;
            } elseif (empty($v['enabled'])) {
                return null;
            } else {
                $day = [
                    'start' => is_string($v['start'] ?? null) ? $v['start'] : $defaultDay['start'],
                    'end' => is_string($v['end'] ?? null) ? $v['end'] : $defaultDay['end'],
                ];
            }
        }

        $timePattern = '/^([01]\d|2[0-3]):(00|15|30|45)$/';
        if (! preg_match($timePattern, $day['start']) || ! preg_match($timePattern, $day['end'])) {
            $techStart = $storeStartMin;
            $techEnd = $storeEndMin;
        } else {
            $techStart = Agent::timeStringToMinutes($day['start']);
            $techEnd = Agent::timeStringToMinutes($day['end']);
        }

        $winStart = max($techStart, $storeStartMin);
        $winEnd = min($techEnd, $storeEndMin);
        if ($winStart >= $winEnd) {
            return null;
        }

        return [$winStart, $winEnd];
    }

    /**
     * @return list<array{0: int, 1: int}> Intervalos ocupados em minutos desde meia-noite (dia local), [início, fim).
     */
    private function busyIntervalsForUserOnDay(int $userId, Carbon $day): array
    {
        $tz = (string) config('booking.business_timezone');
        $rangeStart = $day->copy()->timezone($tz)->startOfDay();
        $rangeEnd = $rangeStart->copy()->addDay();

        $events = CalendarEvent::query()
            ->where('user_id', $userId)
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_FALTOU,
                    ]);
            })
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->orderBy('start_at')
            ->get(['start_at', 'end_at']);

        $out = [];
        foreach ($events as $ev) {
            if (! $ev->start_at || ! $ev->end_at) {
                continue;
            }
            $st = $ev->start_at->copy()->timezone($tz);
            $en = $ev->end_at->copy()->timezone($tz);
            $evStart = max($st->timestamp, $rangeStart->timestamp);
            $evEnd = min($en->timestamp, $rangeEnd->timestamp);
            if ($evEnd <= $evStart) {
                continue;
            }
            $sMin = (int) floor(($evStart - $rangeStart->timestamp) / 60);
            $eMin = (int) ceil(($evEnd - $rangeStart->timestamp) / 60);
            if ($eMin > $sMin) {
                $out[] = [$sMin, $eMin];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $busyIntervals
     * @return list<string> Horários HH:MM (incrementos de 15 min).
     */
    private function buildAvailableSlots(int $winStart, int $winEnd, int $durationMinutes, array $busyIntervals): array
    {
        $step = 15;
        $slots = [];
        $first = (int) (ceil($winStart / $step) * $step);
        for ($m = $first; $m + $durationMinutes <= $winEnd; $m += $step) {
            if (! $this->proposalOverlapsBusy($m, $m + $durationMinutes, $busyIntervals)) {
                $h = intdiv($m, 60);
                $min = $m % 60;
                $slots[] = sprintf('%02d:%02d', $h, $min);
            }
        }

        return $slots;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $busyIntervals
     */
    private function proposalOverlapsBusy(int $startMin, int $endMin, array $busyIntervals): bool
    {
        foreach ($busyIntervals as $b) {
            $a = $b[0];
            $c = $b[1];
            if ($startMin < $c && $endMin > $a) {
                return true;
            }
        }

        return false;
    }
}
