<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\BookingMagicLoginToken;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Support\PhoneDisplay;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                    $q->orderBy('sort_order');
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
        $user = Auth::user();
        $isBookingClient = $user instanceof User && $user->isBookingClient();
        $client = $isBookingClient ? $user->loadMissing('client')->client : null;

        return view('booking.step3', [
            'businessName' => config('app.name'),
            'bookingClientUser' => $isBookingClient ? $user : null,
            'bookingClient' => $client,
            'acessoUrl' => route('booking.acesso'),
            'definirPasswordUrl' => $isBookingClient ? route('booking.conta.password.edit') : null,
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
     * Submete marcação pública: cria/atualiza cliente e registo na agenda.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'agent_id' => ['required', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
        ]);

        $agentKey = $validated['agent_id'];
        if ($agentKey !== 'any' && ! ctype_digit((string) $agentKey)) {
            throw ValidationException::withMessages([
                'agent_id' => ['Seleção de técnica inválida.'],
            ]);
        }

        $tz = (string) config('booking.business_timezone');
        $day = Carbon::parse($validated['date'], $tz)->startOfDay()->timezone($tz);
        if ($day->lt(now($tz)->startOfDay())) {
            throw ValidationException::withMessages([
                'date' => ['Data inválida.'],
            ]);
        }

        $serviceIds = array_values(array_map(fn (array $row): int => (int) $row['id'], $validated['services']));
        $services = Service::query()->whereIn('id', $serviceIds)->get()->keyBy('id');
        if ($services->count() !== count(array_unique($serviceIds))) {
            throw ValidationException::withMessages([
                'services' => ['Um ou mais serviços são inválidos.'],
            ]);
        }

        $orderedServices = collect($serviceIds)->map(fn (int $id) => $services->get($id));
        $totalDuration = (int) $orderedServices->sum('duration');
        if ($totalDuration <= 0) {
            throw ValidationException::withMessages([
                'services' => ['Duração total inválida.'],
            ]);
        }

        // Data/hora no fuso da loja (site). Converter para app.timezone antes de gravar — o CRM trata
        // start_at/end_at nesse fuso; com APP_TIMEZONE=UTC evita gravar 18h30 “Lisboa” como 18h30 UTC (+1h na agenda).
        $startLocal = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time'], $tz);
        $endLocal = $startLocal->copy()->addMinutes($totalDuration);
        $tzApp = (string) config('app.timezone');
        $startForDb = $startLocal->copy()->timezone($tzApp);
        $endForDb = $endLocal->copy()->timezone($tzApp);

        $eligible = $this->agentsEligibleForServices($serviceIds);
        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'services' => ['Não há técnica disponível para esta combinação de serviços.'],
            ]);
        }

        $userId = null;
        if ($agentKey !== 'any') {
            $agent = $eligible->firstWhere('id', (int) $agentKey);
            if (! $agent || ! $agent->user_id) {
                throw ValidationException::withMessages([
                    'agent_id' => ['Técnica inválida ou incompatível com o carrinho.'],
                ]);
            }
            if (! $this->slotFitsAgentSchedule($agent, $startLocal, $endLocal)) {
                throw ValidationException::withMessages([
                    'time' => ['Este horário já não está disponível. Escolhe outro.'],
                ]);
            }
            $userId = (int) $agent->user_id;
        } else {
            foreach ($eligible->sortBy('name') as $agent) {
                if ($this->slotFitsAgentSchedule($agent, $startLocal, $endLocal)) {
                    $userId = (int) $agent->user_id;
                    break;
                }
            }
            if ($userId === null) {
                throw ValidationException::withMessages([
                    'time' => ['Não há técnico disponível neste horário. Escolhe outra hora ou data.'],
                ]);
            }
        }

        if (User::find($userId)?->role === User::ROLE_ADMIN) {
            throw ValidationException::withMessages([
                'agent_id' => ['Não foi possível concluir a marcação.'],
            ]);
        }

        $actor = Auth::user();
        $isBookingClient = $actor instanceof User && $actor->isBookingClient();
        $createdBookingUser = false;

        if ($isBookingClient) {
            $client = Client::query()->findOrFail((int) $actor->client_id);
            $this->appendOnlineBookingNotes($client, $validated['notes'] ?? null);
        } else {
            $resolved = $this->resolveGuestBookingClient(
                $validated['name'],
                $validated['email'],
                $validated['phone'],
                $validated['notes'] ?? null,
            );
            $client = $resolved['client'];
            $createdBookingUser = $resolved['created_booking_user'];
        }

        $firstService = $orderedServices->first();
        $servicesPayload = [];
        foreach ($orderedServices->values() as $svc) {
            $price = $this->bookingPriceForService($svc);
            $servicesPayload[] = [
                'service_id' => $svc->id,
                'duration' => (int) $svc->duration,
                'price' => $price,
                'original_price' => (float) $svc->price,
            ];
        }

        $title = $client->name.' - '.$orderedServices->pluck('name')->implode(', ');
        $notesTrim = isset($validated['notes']) ? trim((string) $validated['notes']) : '';
        $description = $notesTrim !== '' ? $notesTrim : null;

        $event = DB::transaction(function () use ($title, $startForDb, $endForDb, $description, $userId, $client, $firstService, $servicesPayload) {
            if ($this->userHasCalendarConflict($userId, $startForDb, $endForDb)) {
                throw ValidationException::withMessages([
                    'time' => ['Este horário acabou de ser ocupado. Escolhe outro.'],
                ]);
            }

            $ev = CalendarEvent::create([
                'title' => $title,
                'start_at' => $startForDb,
                'end_at' => $endForDb,
                'description' => $description,
                'event_type' => CalendarEvent::TYPE_MARCACAO,
                'user_id' => $userId,
                'client_id' => $client->id,
                'service_id' => $firstService->id,
                'status' => CalendarEvent::STATUS_AGENDADO,
            ]);

            foreach ($servicesPayload as $i => $item) {
                $ev->eventServices()->attach((int) $item['service_id'], [
                    'duration' => $item['duration'],
                    'price' => $item['price'],
                    'original_price' => $item['original_price'],
                    'sort_order' => $i,
                ]);
            }

            return $ev;
        });

        try {
            $recipient = User::find($userId);
            if ($recipient) {
                $recipient->notify(new AppointmentNotification($event->id, 'assigned', null, fromPublicBooking: true));
            }
        } catch (\Throwable $e) {
            \Log::warning('Marcação online: falha ao notificar técnica.', [
                'calendar_event_id' => $event->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        $confirmParams = [];
        if ($createdBookingUser) {
            $confirmParams['primeira_marcacao'] = '1';
        }

        return response()->json([
            'success' => true,
            'redirect' => route('booking.confirm', $confirmParams),
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

    private function bookingPriceForService(Service $service): float
    {
        if ($service->online_price !== null) {
            return (float) $service->online_price;
        }

        return (float) $service->price;
    }

    /**
     * @param  list<int>  $serviceIds
     * @return Collection<int, Agent>
     */
    private function agentsEligibleForServices(array $serviceIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $serviceIds)));

        return Agent::query()
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
            ->filter(function (Agent $agent) use ($ids): bool {
                $techIds = $agent->services->pluck('id')->map(fn ($id) => (int) $id)->all();
                foreach ($ids as $sid) {
                    if (! in_array($sid, $techIds, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    private function findClientByPhoneE164(string $e164): ?Client
    {
        $needleNorm = PhoneDisplay::toE164($e164) ?? trim($e164);

        return Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(function (Client $c) use ($needleNorm): bool {
                $ex = PhoneDisplay::toE164($c->phone);
                $needleE164 = PhoneDisplay::toE164($needleNorm);
                if ($ex !== null && $needleE164 !== null) {
                    return $ex === $needleE164;
                }

                return trim((string) $c->phone) === trim((string) $needleNorm);
            });
    }

    /**
     * Resolve cliente e conta de marcação para visitante (cria ficha + User cliente ou liga ficha CRM sem User).
     *
     * @return array{client: Client, created_booking_user: bool}
     */
    private function resolveGuestBookingClient(string $name, string $email, string $phoneRaw, ?string $notes): array
    {
        $phoneE164 = PhoneDisplay::toE164(trim($phoneRaw));
        if ($phoneE164 === null || $phoneE164 === '') {
            throw ValidationException::withMessages([
                'phone' => ['Telemóvel inválido.'],
            ]);
        }

        $emailNorm = strtolower(trim($email));
        if ($emailNorm === '') {
            throw ValidationException::withMessages([
                'email' => ['O email é obrigatório.'],
            ]);
        }

        $byPhone = $this->findClientByPhoneE164($phoneE164);
        $byEmail = Client::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->first();

        if ($byPhone && $byEmail && $byPhone->id !== $byEmail->id) {
            throw ValidationException::withMessages([
                'email' => ['O email e o telemóvel não correspondem à mesma ficha. Contacta a loja.'],
                'phone' => ['O email e o telemóvel não correspondem à mesma ficha. Contacta a loja.'],
            ]);
        }

        if ($byPhone && ! $byEmail) {
            $clientEmail = $byPhone->email !== null && trim((string) $byPhone->email) !== ''
                ? strtolower(trim((string) $byPhone->email))
                : '';
            if ($clientEmail !== '' && $clientEmail !== $emailNorm) {
                throw ValidationException::withMessages([
                    'email' => ['Este email não coincide com o telemóvel indicado na nossa base de dados.'],
                ]);
            }
        }

        if ($byEmail && ! $byPhone) {
            if (! $this->phonesMatchClient($byEmail, $phoneE164)) {
                throw ValidationException::withMessages([
                    'phone' => ['Este telemóvel não coincide com o email na nossa base de dados.'],
                ]);
            }
        }

        $client = $byPhone ?? $byEmail;

        if ($client) {
            $hasBookingLogin = User::query()
                ->where('role', User::ROLE_CLIENTE)
                ->where('client_id', $client->id)
                ->exists();

            if ($hasBookingLogin) {
                $phoneOk = $this->phonesMatchClient($client, $phoneE164);
                $emailOk = strtolower(trim((string) ($client->email ?? ''))) === $emailNorm;
                if (! $phoneOk || ! $emailOk) {
                    throw ValidationException::withMessages([
                        'email' => ['Os dados não coincidem com a conta registada.'],
                    ]);
                }
                $this->throwRequiresBookingLogin();
            }

            $this->attachBookingUserToLegacyClient($client, $name, $emailNorm, $phoneE164, $notes);
            $user = User::query()
                ->where('role', User::ROLE_CLIENTE)
                ->where('client_id', $client->id)
                ->first();
            if ($user) {
                BookingMagicLoginToken::sendFreshLink($user);
            }

            return ['client' => $client->fresh(), 'created_booking_user' => true];
        }

        return $this->createNewClientAndBookingUser($name, $emailNorm, $phoneE164, $notes);
    }

    /**
     * @return array{client: Client, created_booking_user: bool}
     */
    private function createNewClientAndBookingUser(string $name, string $emailNorm, string $phoneE164, ?string $notes): array
    {
        $this->assertEmailAvailableForBookingUser($emailNorm);

        $notesBlock = ($notes !== null && trim($notes) !== '')
            ? '[Marcação online] '.trim($notes)
            : null;

        $client = Client::create([
            'name' => $name,
            'email' => $emailNorm,
            'phone' => $phoneE164,
            'preferences_notes' => $notesBlock,
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $user = User::create([
            'name' => $name,
            'email' => $emailNorm,
            'password' => Hash::make(Str::random(64)),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
            'must_set_password' => true,
        ]);

        BookingMagicLoginToken::sendFreshLink($user);

        return ['client' => $client, 'created_booking_user' => true];
    }

    private function attachBookingUserToLegacyClient(Client $client, string $name, string $emailNorm, string $phoneE164, ?string $notes): void
    {
        $this->assertEmailAvailableForBookingUser($emailNorm);

        $notesBlock = ($notes !== null && trim($notes) !== '')
            ? '[Marcação online] '.trim($notes)
            : null;

        if ($notesBlock) {
            $prev = (string) ($client->preferences_notes ?? '');
            $client->preferences_notes = trim($prev !== '' ? $prev."\n\n".$notesBlock : $notesBlock);
        }

        $client->name = $name;
        $client->phone = $phoneE164;
        if ($client->email === null || trim((string) $client->email) === '') {
            $client->email = $emailNorm;
        }
        $client->save();

        User::create([
            'name' => $name,
            'email' => $emailNorm,
            'password' => Hash::make(Str::random(64)),
            'role' => User::ROLE_CLIENTE,
            'client_id' => $client->id,
            'must_set_password' => true,
        ]);
    }

    private function assertEmailAvailableForBookingUser(string $emailNorm): void
    {
        $exists = User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a uma conta. Usa o acesso por link ou inicia sessão.'],
            ]);
        }
    }

    private function appendOnlineBookingNotes(Client $client, ?string $notes): void
    {
        $notesTrim = isset($notes) ? trim((string) $notes) : '';
        if ($notesTrim === '') {
            return;
        }

        $notesBlock = '[Marcação online] '.$notesTrim;
        $prev = (string) ($client->preferences_notes ?? '');
        $client->preferences_notes = trim($prev !== '' ? $prev."\n\n".$notesBlock : $notesBlock);
        $client->save();
    }

    private function phonesMatchClient(Client $client, string $phoneE164): bool
    {
        $raw = trim((string) ($client->phone ?? ''));
        if ($raw === '') {
            return false;
        }

        $ex = PhoneDisplay::toE164($raw);
        $n = PhoneDisplay::toE164($phoneE164);
        if ($ex !== null && $n !== null) {
            return $ex === $n;
        }

        return $raw === trim($phoneE164);
    }

    private function throwRequiresBookingLogin(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Já tens conta de marcação com estes dados.',
            'errors' => [
                'email' => ['Inicia sessão com o link enviado por email ou pede um novo link na página de acesso.'],
            ],
            'requires_login' => true,
        ], 422));
    }

    private function slotFitsAgentSchedule(Agent $agent, Carbon $start, Carbon $end): bool
    {
        if (! $agent->user_id) {
            return false;
        }

        $tz = (string) config('booking.business_timezone');
        $day = $start->copy()->timezone($tz)->startOfDay();
        $dowKey = $this->carbonToWeekdayKey($day);
        $storeStart = Agent::timeStringToMinutes(self::STORE_OPEN);
        $storeEnd = Agent::timeStringToMinutes(self::STORE_CLOSE);
        $window = $this->resolveAgentDayWindow($agent->weekly_schedule, $dowKey, $storeStart, $storeEnd);
        if ($window === null) {
            return false;
        }

        $sMin = ($start->hour * 60) + $start->minute;
        $duration = (int) $start->diffInMinutes($end);
        if ($duration <= 0 || $sMin < $window[0] || $sMin + $duration > $window[1]) {
            return false;
        }

        $tzApp = (string) config('app.timezone');

        return ! $this->userHasCalendarConflict(
            (int) $agent->user_id,
            $start->copy()->timezone($tzApp),
            $end->copy()->timezone($tzApp),
        );
    }

    private function userHasCalendarConflict(int $userId, Carbon $start, Carbon $end): bool
    {
        return CalendarEvent::query()
            ->where('user_id', $userId)
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_FALTOU,
                    ]);
            })
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }
}
