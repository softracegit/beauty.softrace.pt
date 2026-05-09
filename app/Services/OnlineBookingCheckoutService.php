<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Support\CurrentStore;
use App\Support\PhoneDisplay;
use App\Support\WeeklyScheduleWindow;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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
            'services.*.service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
        ];
    }

    /**
     * Validation rules for temporary slot hold (no checkout contact fields yet).
     *
     * @return array<string, mixed>
     */
    public function slotHoldRules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'agent_id' => ['required', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
            'services.*.service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
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
     * @param  list<array<string, mixed>>  $servicesInput
     */
    public function storeIdFromBookingServices(array $servicesInput): int
    {
        $ids = [];
        foreach ($servicesInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return Store::defaultPublicBookingStoreId();
        }

        $storeIds = Service::query()->whereIn('id', $ids)->pluck('store_id')->unique()->values();
        if ($storeIds->isEmpty()) {
            return Store::defaultPublicBookingStoreId();
        }
        if ($storeIds->count() > 1) {
            throw ValidationException::withMessages([
                'services' => ['Os serviços não pertencem à mesma loja.'],
            ]);
        }

        return (int) $storeIds->first();
    }

    /**
     * Garante que os serviços do pedido pertencem à loja do segmento /booking/{store}.
     */
    public function assertPublicBookingServicesBelongToUrlStore(array $servicesInput, ?Request $request = null): void
    {
        $request ??= request();
        $store = $request->route('store');
        if (! $store instanceof Store) {
            return;
        }

        $expected = (int) $store->id;
        $fromServices = $this->storeIdFromBookingServices($servicesInput);
        if ($fromServices !== $expected) {
            throw ValidationException::withMessages([
                'services' => ['Os serviços não pertencem a esta loja.'],
            ]);
        }
    }

    /**
     * Resolve technician, times, and cart totals without touching the database.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *     validated: array<string, mixed>,
     *     bookingLines: list<array{
     *         service: Service,
     *         option: ?ServiceOption,
     *         duration: int,
     *         price: float,
     *         original_price: float,
     *         display_name: string,
     *     }>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon,
     *     totalPrice: float,
     * }
     */
    public function resolveValidatedBookingPayload(array $validated): array
    {
        $slot = $this->resolveSlotCandidateCore($validated);
        $bookingLines = $slot['bookingLines'];
        $userId = $slot['userId'];
        $startForDb = $slot['startForDb'];
        $endForDb = $slot['endForDb'];

        $totalPrice = round(
            (float) array_sum(array_map(fn (array $line): float => (float) $line['price'], $bookingLines)),
            2,
        );

        return [
            'validated' => $validated,
            'bookingLines' => $bookingLines,
            'userId' => $userId,
            'startForDb' => $startForDb,
            'endForDb' => $endForDb,
            'totalPrice' => $totalPrice,
        ];
    }

    /**
     * Resolve candidate technician and slot window for temporary hold.
     *
     * @param  array<string, mixed>  $validated
     * @return array{
     *     bookingLines: list<array{service: Service, option: ?ServiceOption, duration: int, price: float, original_price: float, display_name: string}>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon
     * }
     */
    public function resolveSlotCandidateForHold(array $validated): array
    {
        return $this->resolveSlotCandidateCore($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     bookingLines: list<array{service: Service, option: ?ServiceOption, duration: int, price: float, original_price: float, display_name: string}>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon
     * }
     */
    private function resolveSlotCandidateCore(array $validated): array
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

        $bookingLines = $this->resolveOnlineBookingServiceLines($validated['services']);
        $serviceIds = array_values(array_unique(array_map(
            fn (array $line): int => (int) $line['service']->id,
            $bookingLines,
        )));
        $totalDuration = (int) array_sum(array_map(fn (array $line): int => (int) $line['duration'], $bookingLines));
        if ($totalDuration <= 0) {
            throw ValidationException::withMessages([
                'services' => ['Duração total inválida.'],
            ]);
        }

        $storeId = (int) $bookingLines[0]['service']->store_id;

        $startLocal = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time'], $tz);
        $endLocal = $startLocal->copy()->addMinutes($totalDuration);
        $minLeadMinutes = max(0, (int) config('booking.min_lead_minutes', 30));
        $leadLimit = now($tz)->addMinutes($minLeadMinutes);
        if ($startLocal->lt($leadLimit)) {
            throw ValidationException::withMessages([
                'time' => ['Esta marcação deve ser feita com pelo menos '.$minLeadMinutes.' minutos de antecedência.'],
            ]);
        }
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
            foreach ($this->rankAnyStaffCandidates($eligible, $startLocal, $storeId) as $agent) {
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

        return [
            'bookingLines' => $bookingLines,
            'userId' => $userId,
            'startForDb' => $startForDb,
            'endForDb' => $endForDb,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $servicesInput
     * @return list<array{
     *     service: Service,
     *     option: ?ServiceOption,
     *     duration: int,
     *     price: float,
     *     original_price: float,
     *     display_name: string,
     * }>
     */
    private function resolveOnlineBookingServiceLines(array $servicesInput): array
    {
        $serviceIds = array_values(array_unique(array_map(
            fn ($row): int => is_array($row) ? (int) ($row['id'] ?? 0) : 0,
            $servicesInput,
        )));
        $serviceIds = array_values(array_filter($serviceIds, fn (int $id) => $id > 0));

        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages([
                'services' => ['Um ou mais serviços são inválidos.'],
            ]);
        }

        $lines = [];
        foreach ($servicesInput as $idx => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'services' => ['Pedido inválido.'],
                ]);
            }

            $sid = (int) ($row['id'] ?? 0);
            $service = $services->get($sid);
            if (! $service) {
                throw ValidationException::withMessages([
                    "services.{$idx}.id" => ['Serviço inválido.'],
                ]);
            }

            $hasVariants = $service->options->isNotEmpty();
            $optRaw = $row['service_option_id'] ?? null;
            $optId = ($optRaw !== null && $optRaw !== '') ? (int) $optRaw : null;

            if ($hasVariants) {
                if (! $optId) {
                    throw ValidationException::withMessages([
                        "services.{$idx}.service_option_id" => ['Selecione a variante do serviço.'],
                    ]);
                }
                if (! $service->options->contains('id', $optId)) {
                    throw ValidationException::withMessages([
                        "services.{$idx}.service_option_id" => ['A variante não pertence a este serviço.'],
                    ]);
                }
            } elseif ($optId) {
                throw ValidationException::withMessages([
                    "services.{$idx}.service_option_id" => ['Este serviço não tem variantes.'],
                ]);
            }

            /** @var ServiceOption|null $option */
            $option = $optId ? $service->options->firstWhere('id', $optId) : null;

            $duration = $option ? (int) $option->duration : (int) $service->duration;
            if ($duration <= 0) {
                throw ValidationException::withMessages([
                    "services.{$idx}.id" => ['Duração inválida.'],
                ]);
            }

            $price = $option
                ? $this->bookingPriceForOption($option)
                : $this->bookingPriceForService($service);
            $originalPrice = $option ? (float) $option->price : (float) $service->price;
            $displayName = $option ? (string) $option->name : (string) $service->name;

            $lines[] = [
                'service' => $service,
                'option' => $option,
                'duration' => $duration,
                'price' => $price,
                'original_price' => $originalPrice,
                'display_name' => $displayName,
            ];
        }

        return $lines;
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
            $name = trim((string) ($validated['name'] ?? ''));
            $emailNorm = strtolower(trim((string) ($validated['email'] ?? '')));
            $phoneE164 = PhoneDisplay::toE164(trim((string) ($validated['phone'] ?? '')));
            if ($phoneE164 === null || $phoneE164 === '') {
                throw ValidationException::withMessages([
                    'phone' => ['Telemóvel inválido.'],
                ]);
            }
            if ($name !== '') {
                $client->name = $name;
                $actor->name = $name;
            }
            if ($emailNorm !== '') {
                $client->email = $emailNorm;
            }
            $client->phone = $phoneE164;
            $client->save();
            $actor->save();
            $this->appendOnlineBookingNotes($client, $validated['notes'] ?? null);

            return ['client' => $client, 'created_booking_user' => false];
        }

        $resolved = $this->resolveGuestBookingClient(
            $validated['name'],
            $validated['email'],
            $validated['phone'],
            $validated['notes'] ?? null,
            $this->storeIdFromBookingServices($validated['services']),
        );
        $client = $resolved['client'];
        $createdBookingUser = $resolved['created_booking_user'];

        return ['client' => $client, 'created_booking_user' => $createdBookingUser];
    }

    /**
     * @param  array{
     *     validated: array<string, mixed>,
     *     bookingLines: list<array{service: Service, option: ?ServiceOption, duration: int, price: float, original_price: float, display_name: string}>,
     *     userId: int,
     *     startForDb: Carbon,
     *     endForDb: Carbon,
     * }  $resolved
     */
    public function persistMarcacao(array $resolved, Client $client): CalendarEvent
    {
        $validated = $resolved['validated'];
        /** @var list<array{service: Service, option: ?ServiceOption, duration: int, price: float, original_price: float, display_name: string}> $bookingLines */
        $bookingLines = $resolved['bookingLines'];
        $userId = $resolved['userId'];
        $startForDb = $resolved['startForDb'];
        $endForDb = $resolved['endForDb'];

        $firstService = $bookingLines[0]['service'];
        $servicesPayload = [];
        foreach ($bookingLines as $line) {
            $svc = $line['service'];
            $opt = $line['option'];
            $servicesPayload[] = [
                'service_id' => $svc->id,
                'service_option_id' => $opt?->id,
                'option_name' => $opt?->name,
                'option_duration' => $opt ? (int) $opt->duration : null,
                'option_price' => $opt ? (float) $opt->price : null,
                'option_online_price' => $opt && $opt->online_price !== null ? (float) $opt->online_price : null,
                'duration' => (int) $line['duration'],
                'price' => (float) $line['price'],
                'original_price' => (float) $line['original_price'],
            ];
        }

        $title = $client->name.' - '.collect($bookingLines)->pluck('display_name')->implode(', ');
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
                    'service_option_id' => $item['service_option_id'] ?? null,
                    'option_name' => $item['option_name'] ?? null,
                    'option_duration' => $item['option_duration'] ?? null,
                    'option_price' => $item['option_price'] ?? null,
                    'option_online_price' => $item['option_online_price'] ?? null,
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

    public function bookingPriceForOption(ServiceOption $option): float
    {
        if ($option->online_price !== null) {
            return (float) $option->online_price;
        }

        return (float) $option->price;
    }

    /**
     * @param  list<int>  $serviceIds
     * @return Collection<int, Agent>
     */
    private function agentsEligibleForServices(array $serviceIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $serviceIds)));

        $storeId = app(CurrentStore::class)->tryId();

        $query = Agent::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', function ($q): void {
                $q->eligibleForPublicBooking();
            });

        if ($storeId !== null) {
            $query->withServicesForStore($storeId);
        } else {
            $query->with('services');
        }

        return $query
            ->orderBy('agenda_order')
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
     * @param  Collection<int, Agent>  $eligible
     * @return Collection<int, Agent>
     */
    private function rankAnyStaffCandidates(Collection $eligible, Carbon $startLocal, int $storeId): Collection
    {
        if ($eligible->isEmpty()) {
            return $eligible;
        }

        $rule = CrmSetting::bookingAnyStaffRule($storeId);
        $userIds = $eligible
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($userIds === []) {
            return $eligible->sortBy('agenda_order')->sortBy('name')->values();
        }

        $tz = (string) config('booking.business_timezone');
        $dayStart = $startLocal->copy()->timezone($tz)->startOfDay();
        $dayEnd = $startLocal->copy()->timezone($tz)->endOfDay();
        $monthStart = $startLocal->copy()->timezone($tz)->startOfMonth();
        $monthEnd = $startLocal->copy()->timezone($tz)->endOfMonth();

        $dayLoads = CalendarEvent::query()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO])
            ->whereIn('user_id', $userIds)
            ->whereBetween('start_at', [$dayStart->copy()->timezone(config('app.timezone')), $dayEnd->copy()->timezone(config('app.timezone'))])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $monthLoads = CalendarEvent::query()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO])
            ->whereIn('user_id', $userIds)
            ->whereBetween('start_at', [$monthStart->copy()->timezone(config('app.timezone')), $monthEnd->copy()->timezone(config('app.timezone'))])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $ordered = $eligible->sort(function (Agent $a, Agent $b) use ($rule, $dayLoads, $monthLoads): int {
            $aUid = (int) $a->user_id;
            $bUid = (int) $b->user_id;
            $aAgendaOrder = (int) ($a->agenda_order ?? 0);
            $bAgendaOrder = (int) ($b->agenda_order ?? 0);
            $aDay = (int) ($dayLoads[$aUid] ?? 0);
            $bDay = (int) ($dayLoads[$bUid] ?? 0);
            $aMonth = (int) ($monthLoads[$aUid] ?? 0);
            $bMonth = (int) ($monthLoads[$bUid] ?? 0);

            $cmp = 0;
            switch ($rule) {
                case CrmSetting::BOOKING_ANY_STAFF_RULE_B:
                    $cmp = $aAgendaOrder <=> $bAgendaOrder;
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = $aDay <=> $bDay;
                    break;

                case CrmSetting::BOOKING_ANY_STAFF_RULE_C:
                    $cmp = $aMonth <=> $bMonth;
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = $aAgendaOrder <=> $bAgendaOrder;
                    break;

                case CrmSetting::BOOKING_ANY_STAFF_RULE_D:
                    $cmp = $aAgendaOrder <=> $bAgendaOrder;
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = $aMonth <=> $bMonth;
                    break;

                case CrmSetting::BOOKING_ANY_STAFF_RULE_A:
                default:
                    $cmp = $aDay <=> $bDay;
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = $aAgendaOrder <=> $bAgendaOrder;
                    break;
            }

            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) $a->name, (string) $b->name);
        });

        return $ordered->values();
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
    private function resolveGuestBookingClient(string $name, string $email, string $phoneRaw, ?string $notes, int $storeId): array
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

            return ['client' => $client->fresh(), 'created_booking_user' => true];
        }

        return $this->createNewClientAndBookingUser($name, $emailNorm, $phoneE164, $notes, $storeId);
    }

    /**
     * @return array{client: Client, created_booking_user: bool}
     */
    private function createNewClientAndBookingUser(string $name, string $emailNorm, string $phoneE164, ?string $notes, int $storeId): array
    {
        $this->assertEmailAvailableForBookingUser($emailNorm);

        $notesBlock = ($notes !== null && trim($notes) !== '')
            ? '[Marcação online] '.trim($notes)
            : null;

        $organizationId = Store::query()->whereKey($storeId)->value('organization_id');

        try {
            $client = Client::create([
                'store_id' => $storeId,
                'name' => $name,
                'email' => $emailNorm,
                'phone' => $phoneE164,
                'preferences_notes' => $notesBlock,
                'type' => Client::TYPE_POTENCIAL_CLIENTE,
            ]);
        } catch (QueryException $e) {
            $this->throwFriendlyBookingDuplicateIntegrity($e);
            throw $e;
        }

        try {
            User::create([
                'name' => $name,
                'email' => $emailNorm,
                'password' => Hash::make(Str::random(64)),
                'role' => User::ROLE_CLIENTE,
                'organization_id' => $organizationId,
                'client_id' => $client->id,
                'must_set_password' => false,
            ]);
        } catch (QueryException $e) {
            $this->throwFriendlyBookingDuplicateIntegrity($e);
            throw $e;
        }

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

        try {
            $client->save();
        } catch (QueryException $e) {
            $this->throwFriendlyBookingDuplicateIntegrity($e);
            throw $e;
        }

        $organizationId = $client->store_id
            ? Store::query()->whereKey($client->store_id)->value('organization_id')
            : null;

        try {
            User::create([
                'name' => $name,
                'email' => $emailNorm,
                'password' => Hash::make(Str::random(64)),
                'role' => User::ROLE_CLIENTE,
                'organization_id' => $organizationId,
                'client_id' => $client->id,
                'must_set_password' => false,
            ]);
        } catch (QueryException $e) {
            $this->throwFriendlyBookingDuplicateIntegrity($e);
            throw $e;
        }
    }

    private function throwFriendlyBookingDuplicateIntegrity(QueryException $e): void
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($sqlState === '23000' && $driverCode === 1062) {
            throw ValidationException::withMessages([
                'email' => ['Não foi possível guardar os dados. Se o email ou telemóvel já estiverem registados, inicie sessão ou contacte a loja.'],
            ]);
        }
    }

    private function assertEmailAvailableForBookingUser(string $emailNorm): void
    {
        $exists = User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['Este email já está associado a uma conta. Inicia sessão para continuar.'],
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
        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent->weekly_schedule, $dowKey, $storeStart, $storeEnd);
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
                        CalendarEvent::STATUS_ANULADO,
                        CalendarEvent::STATUS_FALTOU,
                    ]);
            })
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }
}
