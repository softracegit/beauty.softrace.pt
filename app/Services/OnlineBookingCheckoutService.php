<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\BookingMagicLoginToken;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Support\PhoneDisplay;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-side rules for the public /booking flow: slot resolution, pricing, and
 * persisting the CalendarEvent after payment succeeds.
 */
class OnlineBookingCheckoutService
{
    private const STORE_OPEN = '09:00';

    private const STORE_CLOSE = '20:00';

    /**
     * Laravel validation rules shared by the payment-intent and finalize steps.
     *
     * @return array<string, mixed>
     */
    public function bookingRequestRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'agent_id' => ['required', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
        ];
    }

    /**
     * Validate HTTP request input (JSON body from the booking wizard).
     *
     * @return array<string, mixed>
     */
    public function validateBookingRequest(Request $request): array
    {
        return $request->validate($this->bookingRequestRules());
    }

    /**
     * Validate stored payload when finalizing (after Stripe confirms payment).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateStoredPayload(array $payload): array
    {
        return Validator::make($payload, $this->bookingRequestRules())->validate();
    }

    /**
     * Resolve technician, times, and cart totals without touching the database.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *     validated: array<string, mixed>,
     *     orderedServices: Collection<int, Service>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon,
     *     totalPrice: float,
     * }
     */
    public function resolveValidatedBookingPayload(array $validated): array
    {
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

        $totalPrice = round(
            (float) $orderedServices->sum(fn (Service $svc) => $this->bookingPriceForService($svc)),
            2,
        );

        return [
            'validated' => $validated,
            'orderedServices' => $orderedServices,
            'userId' => $userId,
            'startForDb' => $startForDb,
            'endForDb' => $endForDb,
            'totalPrice' => $totalPrice,
        ];
    }

    /**
     * Garante que podemos cobrar o depósito: cliente autenticado válido ou visitante que não precisa
     * de login obrigatório (evita pagamento sem conseguir criar a marcação depois).
     */
    public function assertPayableBookingState(?User $actor, array $validated): void
    {
        if ($actor instanceof User && $actor->isBookingClient()) {
            Client::query()->findOrFail((int) $actor->client_id);

            return;
        }

        $this->assertGuestBookingAllowedBeforePayment(
            $validated['email'],
            $validated['phone'],
        );
    }

    /**
     * @return array{client: Client, created_booking_user: bool}
     */
    public function resolveClientForBooking(array $validated, ?User $actor): array
    {
        $isBookingClient = $actor instanceof User && $actor->isBookingClient();
        $createdBookingUser = false;

        if ($isBookingClient) {
            $client = Client::query()->findOrFail((int) $actor->client_id);
            $this->appendOnlineBookingNotes($client, $validated['notes'] ?? null);

            return ['client' => $client, 'created_booking_user' => false];
        }

        $resolved = $this->resolveGuestBookingClient(
            $validated['name'],
            $validated['email'],
            $validated['phone'],
            $validated['notes'] ?? null,
        );
        $client = $resolved['client'];
        $createdBookingUser = $resolved['created_booking_user'];

        return ['client' => $client, 'created_booking_user' => $createdBookingUser];
    }

    /**
     * @param  array{
     *     validated: array<string, mixed>,
     *     orderedServices: Collection<int, Service>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon,
     * }  $resolved
     */
    public function persistMarcacao(array $resolved, Client $client): CalendarEvent
    {
        $validated = $resolved['validated'];
        $orderedServices = $resolved['orderedServices'];
        $userId = $resolved['userId'];
        $startForDb = $resolved['startForDb'];
        $endForDb = $resolved['endForDb'];

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

        return DB::transaction(function () use ($title, $startForDb, $endForDb, $description, $userId, $client, $firstService, $servicesPayload) {
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
    }

    public function notifyTechnician(CalendarEvent $event, int $userId): void
    {
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
    }

    public function bookingPriceForService(Service $service): float
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

    /**
     * Validação pré-pagamento para visitantes (sem criar ficha nem utilizador).
     */
    private function assertGuestBookingAllowedBeforePayment(string $email, string $phoneRaw): void
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
        if (! $client) {
            $this->assertEmailAvailableForBookingUser($emailNorm);

            return;
        }

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
