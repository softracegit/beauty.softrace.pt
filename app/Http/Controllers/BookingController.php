<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\BookingSavedCard;
use App\Models\BookingSlotHold;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\WeeklyScheduleWindow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $storeId = $this->bookingStoreId();
        $categories = Category::query()
            ->where('store_id', $storeId)
            ->with([
                'services' => function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)
                        ->orderBy('sort_order')
                        ->with(['options' => function ($oq) {
                            $oq->orderBy('sort_order');
                        }]);
                },
            ])
            ->whereHas('services', fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('sort_order')
            ->get();

        return view('booking.index', [
            'categories' => $categories,
            'businessName' => $this->bookingBusinessName(),
        ]);
    }

    /**
     * Passo seguinte no fluxo (data / hora — a completar no servidor).
     */
    public function datetime(): View
    {
        $storeId = $this->bookingStoreId();
        $validAgentIds = Agent::query()
            ->where('store_id', $storeId)
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', function ($q): void {
                $q->eligibleForPublicBooking();
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('booking.datetime', [
            'businessName' => $this->bookingBusinessName(),
            'bookingValidAgentIds' => $validAgentIds,
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
        $serviceIdsRaw = trim((string) $request->query('service_ids', ''));
        $serviceIds = $serviceIdsRaw !== ''
            ? array_values(array_unique(array_filter(array_map('intval', explode(',', $serviceIdsRaw)))))
            : [];
        $serviceIds = $this->serviceIdsBelongingToBookingStore($serviceIds);
        $holdSessionToken = trim((string) $request->query('hold_session_token', ''));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['slots' => []]);
        }

        /** Fuso da loja / cliente — ver config/booking.php (não altera APP_TIMEZONE do CRM). */
        $tz = (string) config('booking.business_timezone');
        try {
            $day = Carbon::parse($dateStr, $tz)->startOfDay()->timezone($tz);
        } catch (\Throwable) {
            return response()->json(['slots' => []]);
        }

        if ($day->lt(now($tz)->startOfDay())) {
            return response()->json(['slots' => []]);
        }

        $storeStart = Agent::timeStringToMinutes(self::STORE_OPEN);
        $storeEnd = Agent::timeStringToMinutes(self::STORE_CLOSE);
        $minLeadMinutes = max(0, (int) config('booking.min_lead_minutes', 30));
        $nowLocal = now($tz);
        $isToday = $day->isSameDay($nowLocal);
        $minLeadStart = null;
        if ($isToday) {
            $leadMoment = $nowLocal->copy()->addMinutes($minLeadMinutes);
            $minLeadStart = ((int) $leadMoment->hour * 60) + (int) $leadMoment->minute;
        }

        if ($agentKey === 'any' || $agentKey === '' || $agentKey === null) {
            $winStart = $storeStart;
            if ($minLeadStart !== null) {
                $winStart = max($winStart, (int) $minLeadStart);
            }
            if ($winStart >= $storeEnd) {
                return response()->json(['slots' => []]);
            }

            $eligibleAgents = $this->bookingEligibleAgentsForServices($serviceIds, $this->bookingStoreId());
            if ($eligibleAgents->isEmpty()) {
                return response()->json(['slots' => []]);
            }

            $candidateSlots = $this->buildAvailableSlots($winStart, $storeEnd, $duration, []);
            $slots = array_values(array_filter($candidateSlots, function (string $time) use ($eligibleAgents, $day, $duration, $storeStart, $storeEnd, $holdSessionToken): bool {
                [$h, $m] = array_map('intval', explode(':', $time));
                $slotStartMin = $h * 60 + $m;
                $slotEndMin = $slotStartMin + $duration;
                $dayKey = $this->carbonToWeekdayKey($day);
                foreach ($eligibleAgents as $agent) {
                    $window = WeeklyScheduleWindow::resolveMinutesWindow($agent->weekly_schedule, $dayKey, $storeStart, $storeEnd);
                    if ($window === null) {
                        continue;
                    }
                    if ($slotStartMin < (int) $window[0] || $slotEndMin > (int) $window[1]) {
                        continue;
                    }
                    $busy = $this->busyIntervalsForUserOnDay((int) $agent->user_id, $day, $holdSessionToken !== '' ? $holdSessionToken : null, $this->bookingStoreId());
                    if (! $this->proposalOverlapsBusy($slotStartMin, $slotEndMin, $busy)) {
                        return true;
                    }
                }

                return false;
            }));

            return response()->json(['slots' => $slots]);
        }

        if (! ctype_digit((string) $agentKey)) {
            return response()->json(['slots' => []]);
        }

        $agent = Agent::query()
            ->where('store_id', $this->bookingStoreId())
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', function ($q): void {
                $q->eligibleForPublicBooking();
            })
            ->with('user:id')
            ->find((int) $agentKey);

        if (! $agent || ! $agent->user_id) {
            return response()->json(['slots' => []]);
        }

        $dowKey = $this->carbonToWeekdayKey($day);
        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent->weekly_schedule, $dowKey, $storeStart, $storeEnd);
        if ($window === null) {
            return response()->json(['slots' => []]);
        }
        $winStart = (int) $window[0];
        $winEnd = (int) $window[1];
        if ($minLeadStart !== null) {
            $winStart = max($winStart, (int) $minLeadStart);
        }
        if ($winStart >= $winEnd) {
            return response()->json(['slots' => []]);
        }

        $busy = $this->busyIntervalsForUserOnDay((int) $agent->user_id, $day, $holdSessionToken !== '' ? $holdSessionToken : null, $this->bookingStoreId());
        $slots = $this->buildAvailableSlots($winStart, $winEnd, $duration, $busy);

        return response()->json(['slots' => $slots]);
    }

    /**
     * Escolha de técnica (passo intermédio entre serviços e data/hora).
     */
    public function technician(): View
    {
        $technicians = Agent::query()
            ->where('store_id', $this->bookingStoreId())
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', function ($q): void {
                $q->eligibleForPublicBooking();
            })
            ->withServicesForStore($this->bookingStoreId())
            ->orderBy('agenda_order')
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
            'businessName' => $this->bookingBusinessName(),
            'technicians' => $technicians,
        ]);
    }

    /**
     * Passo 3 do fluxo público (placeholder).
     */
    public function step3(): View|RedirectResponse
    {
        $user = auth()->user();
        $isBookingClient = $user instanceof User && $user->isBookingClient();
        if (! $isBookingClient) {
            return redirect()->route('booking.index', ['store' => $this->bookingStoreSlug()]);
        }

        $client = $isBookingClient ? $user->loadMissing('client')->client : null;
        if ($client && (int) $client->store_id !== $this->bookingStoreId()) {
            return redirect()->route('booking.index', ['store' => $this->bookingStoreSlug()]);
        }

        $savedCards = collect();
        if ($client) {
            $savedCards = BookingSavedCard::query()
                ->where('client_id', $client->id)
                ->whereNull('detached_at')
                ->orderByDesc('is_default')
                ->orderByDesc('updated_at')
                ->get();
        }
        $onlineBookingPaymentRequired = CrmSetting::onlineBookingPaymentRequired($this->bookingStoreId());

        return view('booking.step3', [
            'businessName' => $this->bookingBusinessName(),
            'bookingClientUser' => $isBookingClient ? $user : null,
            'bookingClient' => $client,
            'savedCards' => $savedCards,
            'onlineBookingPaymentRequired' => $onlineBookingPaymentRequired,
            'bookingPaymentIntentUrl' => route('booking.payment.intent', ['store' => $this->bookingStoreSlug()]),
            'bookingPaymentCompleteUrl' => route('booking.payment.complete', ['store' => $this->bookingStoreSlug()]),
            'bookingConfirmWithoutPaymentUrl' => route('booking.confirm.without_payment', ['store' => $this->bookingStoreSlug()]),
        ]);
    }

    /**
     * Passo seguinte após escolher serviço (data / técnico — a completar).
     */
    public function showService(Store $store, Service $service): View
    {
        abort_unless((int) $store->id === (int) $service->store_id, 404);

        return view('booking.service', [
            'service' => $service,
            'businessName' => $this->bookingBusinessName(),
        ]);
    }

    /**
     * Confirmação após marcação online criada com sucesso.
     * Clientes autenticados são enviados para a listagem de marcações com o mesmo resumo visual.
     */
    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->isBookingClient()) {
            $query = ['marcacao_confirmada' => '1'];
            if ($request->boolean('primeira_marcacao')) {
                $query['primeira_marcacao'] = '1';
            }

            return redirect()->route('booking.conta.marcacoes', array_merge(['store' => $this->bookingStoreSlug()], $query));
        }

        return view('booking.confirm', [
            'businessName' => $this->bookingBusinessName(),
            'primeiraMarcacao' => $request->boolean('primeira_marcacao'),
        ]);
    }

    /**
     * Página "A minha conta" (cliente de marcação — middleware auth + booking.client).
     */
    public function account(Request $request): View
    {
        $user = $request->user();
        $client = $user?->loadMissing('client')->client;

        if ($client && (int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        return view('booking.conta.index', [
            'businessName' => $this->bookingBusinessName(),
            'user' => $user,
            'client' => $client,
        ]);
    }

    public function appointments(Request $request): View
    {
        $user = $request->user();
        $client = $user?->loadMissing('client')->client;
        if ($client && (int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        $marcacoes = $this->loadClientMarcacoes($client?->id);

        return view('booking.conta.marcacoes', [
            'businessName' => $this->bookingBusinessName(),
            'user' => $user,
            'client' => $client,
            'marcacoes' => $marcacoes,
        ]);
    }

    public function settings(Request $request): View
    {
        $user = $request->user();
        $client = $user?->loadMissing('client')->client;
        if ($client && (int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        $cards = collect();
        if ($client) {
            $cards = BookingSavedCard::query()
                ->where('client_id', $client->id)
                ->whereNull('detached_at')
                ->orderByDesc('is_default')
                ->orderByDesc('updated_at')
                ->get();
        }

        return view('booking.conta.settings', [
            'businessName' => $this->bookingBusinessName(),
            'user' => $user,
            'client' => $client,
            'savedCards' => $cards,
            'stripePublishableKey' => (string) config('stripe.key'),
        ]);
    }

    private function loadClientMarcacoes(?int $clientId)
    {
        if (! $clientId) {
            return collect();
        }

        return CalendarEvent::query()
            ->where('store_id', $this->bookingStoreId())
            ->where('client_id', $clientId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->with([
                'user:id,name',
                'service:id,name,category_id',
                'service.category:id,name',
                'eventServiceItems.service:id,name,category_id',
                'eventServiceItems.service.category:id,name',
                'onlineBooking',
                'sale',
            ])
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();
    }

    /**
     * Atualiza nome, género e data de nascimento (ficha de cliente da marcação online).
     */
    public function updateProfilePersonal(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $client = $user?->client;

        if (! $client instanceof Client) {
            abort(404);
        }

        if ((int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:M,F,O'],
            'birth_date' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
        ]);

        $client->name = $validated['name'];
        $client->gender = $validated['gender'];
        $client->birth_date = $validated['birth_date'];
        $client->save();

        $trimmedName = trim($validated['name']);
        if ($trimmedName !== '' && $user->name !== $trimmedName) {
            $user->name = $trimmedName;
            $user->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Dados pessoais guardados.',
            ]);
        }

        return redirect()
            ->route('booking.conta.index', ['store' => $this->bookingStoreSlug()])
            ->with('success', 'Dados pessoais guardados.');
    }

    /**
     * Atualiza preferências de notificações do cliente de marcação.
     */
    public function updateNotificationPreferences(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $client = $user?->client;

        if (! $client instanceof Client) {
            abort(404);
        }

        if ((int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        $request->validate([
            'notify_email_booking_updates' => ['nullable', 'boolean'],
            'notify_email_booking_reminders' => ['nullable', 'boolean'],
            'notify_sms_booking_reminders' => ['nullable', 'boolean'],
        ]);

        $client->forceFill([
            'notify_email_booking_updates' => $request->boolean('notify_email_booking_updates'),
            'notify_email_booking_reminders' => $request->boolean('notify_email_booking_reminders'),
            'notify_sms_booking_reminders' => $request->boolean('notify_sms_booking_reminders'),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Preferências de notificações guardadas.',
            ]);
        }

        return redirect()
            ->route('booking.conta.settings', ['store' => $this->bookingStoreSlug()])
            ->with('success', 'Preferências de notificações guardadas.');
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
     * @return list<array{0: int, 1: int}> Intervalos ocupados em minutos desde meia-noite (dia local), [início, fim).
     */
    private function busyIntervalsForUserOnDay(int $userId, Carbon $day, ?string $excludeHoldSessionToken = null, ?int $storeId = null): array
    {
        $tz = (string) config('booking.business_timezone');
        $rangeStart = $day->copy()->timezone($tz)->startOfDay();
        $rangeEnd = $rangeStart->copy()->addDay();

        $events = CalendarEvent::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->where('user_id', $userId)
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_ANULADO,
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

        $holdQuery = BookingSlotHold::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->active()
            ->where('selected_user_id', $userId)
            ->where('slot_start_at', '<', $rangeEnd)
            ->where('slot_end_at', '>', $rangeStart);
        if (is_string($excludeHoldSessionToken) && $excludeHoldSessionToken !== '') {
            $holdQuery->where('session_token', '!=', $excludeHoldSessionToken);
        }
        foreach ($holdQuery->get(['slot_start_at', 'slot_end_at']) as $hold) {
            if (! $hold->slot_start_at || ! $hold->slot_end_at) {
                continue;
            }
            $st = $hold->slot_start_at->copy()->timezone($tz);
            $en = $hold->slot_end_at->copy()->timezone($tz);
            $holdStart = max($st->timestamp, $rangeStart->timestamp);
            $holdEnd = min($en->timestamp, $rangeEnd->timestamp);
            if ($holdEnd <= $holdStart) {
                continue;
            }
            $sMin = (int) floor(($holdStart - $rangeStart->timestamp) / 60);
            $eMin = (int) ceil(($holdEnd - $rangeStart->timestamp) / 60);
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

    /**
     * @param  list<int>  $serviceIds
     * @return \Illuminate\Support\Collection<int, Agent>
     */
    private function bookingEligibleAgentsForServices(array $serviceIds, int $storeId)
    {
        $query = Agent::query()
            ->where('store_id', $storeId)
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', function ($q): void {
                $q->eligibleForPublicBooking();
            })
            ->withServicesForStore($storeId)
            ->orderBy('agenda_order')
            ->orderBy('name');

        $agents = $query->get();
        if ($serviceIds === []) {
            return $agents;
        }

        return $agents->filter(function (Agent $agent) use ($serviceIds): bool {
            $techIds = $agent->services->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($serviceIds as $sid) {
                if (! in_array((int) $sid, $techIds, true)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function bookingStoreId(): int
    {
        return app(CurrentStore::class)->id();
    }

    private function bookingStoreSlug(): string
    {
        return (string) app(CurrentStore::class)->get()->slug;
    }

    private function bookingBusinessName(): string
    {
        return (string) app(CurrentStore::class)->get()->name;
    }

    /**
     * Ignora IDs do carrinho que não pertencem à loja da URL (ex.: localStorage partilhado entre lojas).
     *
     * @param  list<int>  $serviceIds
     * @return list<int>
     */
    private function serviceIdsBelongingToBookingStore(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $storeId = $this->bookingStoreId();

        return Service::query()
            ->where('store_id', $storeId)
            ->whereIn('id', $serviceIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Mantém a sessão web ativa e devolve o token CSRF atual (para keep-alive no browser).
     */
    public function sessionPing(Request $request): JsonResponse
    {
        $request->session()->put('_booking_keepalive_at', now()->toIso8601String());

        return response()->json([
            'ok' => true,
            'csrf_token' => csrf_token(),
        ]);
    }
}
