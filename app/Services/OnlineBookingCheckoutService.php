<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Support\ActivityLogMarcacaoOrigin;
use App\Models\CalendarEventServiceExtra;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Services\ReceptionBookingNotifier;
use App\Support\ApplicableFees;
use App\Support\CurrentStore;
use App\Support\PhoneDisplay;
use App\Support\PortugueseNationalHolidays;
use App\Support\WeeklyScheduleWindow;
use App\Rules\ClientFullName;
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
    public function __construct(
        private ReceptionBookingNotifier $receptionBookingNotifier,
    ) {}
    /**
     * Laravel validation rules shared by the payment-intent and finalize steps.
     *
     * @return array<string, mixed>
     */
    public function bookingRequestRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new ClientFullName(__('booking.validation.name_full_required'))],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'agent_id' => ['required', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
            'services.*.service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => ['required', 'integer', 'exists:extras,id'],
            'send_invoice_email' => ['sometimes', 'boolean'],
            'want_invoice_with_nif' => ['sometimes', 'boolean'],
            'invoice_email' => ['nullable', 'string', 'email', 'max:255'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
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
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => ['required', 'integer', 'exists:extras,id'],
        ];
    }

    /**
     * Validate HTTP request input (JSON body from the booking wizard).
     *
     * @return array<string, mixed>
     */
    public function validateBookingRequest(Request $request): array
    {
        $paymentRequired = $this->onlinePaymentRequiredForServices($request->input('services', []));

        if ($paymentRequired) {
            $request->merge($this->mergeInvoiceEmailIntoPayload($request->all()));
        }

        $validated = $request->validate($this->bookingRequestRules());

        if ($paymentRequired) {
            $this->enforceBookingInvoiceRules($validated);
            if ($this->truthy($validated['want_invoice_with_nif'] ?? null)) {
                $validated['billing_nif'] = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
            }
        } else {
            $validated = $this->stripBookingInvoiceOptions($validated);
        }

        return $validated;
    }

    /**
     * Validate stored payload when finalizing (after Stripe confirms payment).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateStoredPayload(array $payload): array
    {
        $payload = $this->mergeInvoiceEmailIntoPayload($payload);
        $validated = Validator::make($payload, $this->bookingRequestRules())->validate();
        $this->enforceBookingInvoiceRules($validated);
        if ($this->truthy($validated['want_invoice_with_nif'] ?? null)) {
            $validated['billing_nif'] = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeInvoiceEmailIntoPayload(array $data): array
    {
        $main = strtolower(trim((string) ($data['email'] ?? '')));
        $inv = strtolower(trim((string) ($data['invoice_email'] ?? '')));
        if ($main === '' && $inv !== '' && filter_var($inv, FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $inv;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function enforceBookingInvoiceRules(array $validated): void
    {
        if (! $this->truthy($validated['want_invoice_with_nif'] ?? null)) {
            return;
        }
        $digits = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
        if (strlen($digits) !== 9) {
            throw ValidationException::withMessages([
                'billing_nif' => [__('booking.validation.billing_nif_digits')],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $servicesInput
     */
    private function onlinePaymentRequiredForServices(mixed $servicesInput): bool
    {
        if (! is_array($servicesInput) || $servicesInput === []) {
            return true;
        }

        try {
            return CrmSetting::onlineBookingPaymentRequired($this->storeIdFromBookingServices($servicesInput));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function stripBookingInvoiceOptions(array $validated): array
    {
        unset(
            $validated['send_invoice_email'],
            $validated['want_invoice_with_nif'],
            $validated['invoice_email'],
            $validated['billing_nif'],
        );

        return $validated;
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
                'services' => [__('booking.validation.services_different_stores')],
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
                'services' => [__('booking.validation.services_wrong_store')],
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
     *     servicesSubtotal: float,
     *     catalogFees: list<array{fee_id: int, name: string, price: float, formatted_price: string}>,
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

        $servicesSubtotal = round(
            (float) array_sum(array_map(fn (array $line): float => $this->bookingLineTotalPrice($line), $bookingLines)),
            2,
        );
        $storeId = (int) ($bookingLines[0]['service']->store_id ?? 0);
        $catalogFees = ApplicableFees::forServiceIds(
            array_map(fn (array $line): int => (int) $line['service']->id, $bookingLines),
            $storeId > 0 ? $storeId : null,
        );
        $totalPrice = round($servicesSubtotal + ApplicableFees::sumPrices($catalogFees), 2);

        return [
            'validated' => $validated,
            'bookingLines' => $bookingLines,
            'userId' => $userId,
            'startForDb' => $startForDb,
            'endForDb' => $endForDb,
            'servicesSubtotal' => $servicesSubtotal,
            'catalogFees' => $catalogFees,
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
                'agent_id' => [__('booking.validation.agent_invalid')],
            ]);
        }

        $tz = (string) config('booking.business_timezone');
        $day = Carbon::parse($validated['date'], $tz)->startOfDay()->timezone($tz);
        if ($day->lt(now($tz)->startOfDay())) {
            throw ValidationException::withMessages([
                'date' => [__('booking.validation.date_invalid')],
            ]);
        }
        if (PortugueseNationalHolidays::isHoliday($day)) {
            throw ValidationException::withMessages([
                'date' => [__('booking.validation.date_holiday')],
            ]);
        }

        $bookingLines = $this->resolveOnlineBookingServiceLines($validated['services']);
        $serviceIds = array_values(array_unique(array_map(
            fn (array $line): int => (int) $line['service']->id,
            $bookingLines,
        )));
        $totalDuration = (int) array_sum(array_map(fn (array $line): int => $this->bookingLineTotalDuration($line), $bookingLines));
        if ($totalDuration <= 0) {
            throw ValidationException::withMessages([
                'services' => [__('booking.validation.duration_invalid')],
            ]);
        }

        $storeId = (int) $bookingLines[0]['service']->store_id;

        $startLocal = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time'], $tz);
        $endLocal = $startLocal->copy()->addMinutes($totalDuration);
        $minLeadMinutes = max(0, (int) config('booking.min_lead_minutes', 30));
        $leadLimit = now($tz)->addMinutes($minLeadMinutes);
        if ($startLocal->lt($leadLimit)) {
            throw ValidationException::withMessages([
                'time' => [__('booking.validation.min_lead_time', ['minutes' => $minLeadMinutes])],
            ]);
        }
        $tzApp = (string) config('app.timezone');
        $startForDb = $startLocal->copy()->timezone($tzApp);
        $endForDb = $endLocal->copy()->timezone($tzApp);

        $eligible = $this->agentsEligibleForServices($serviceIds);
        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'services' => [__('booking.validation.no_technician_for_services')],
            ]);
        }

        $userId = null;
        if ($agentKey !== 'any') {
            $agent = $eligible->firstWhere('id', (int) $agentKey);
            if (! $agent || ! $agent->user_id) {
                throw ValidationException::withMessages([
                    'agent_id' => [__('booking.validation.technician_invalid')],
                ]);
            }
            if (! $this->slotFitsAgentSchedule($agent, $startLocal, $endLocal)) {
                throw ValidationException::withMessages([
                    'time' => [__('booking.validation.slot_unavailable')],
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
                    'time' => [__('booking.validation.no_technician_at_time')],
                ]);
            }
        }

        if (User::find($userId)?->role === User::ROLE_ADMIN) {
            throw ValidationException::withMessages([
                'agent_id' => [__('booking.validation.booking_failed')],
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
            ->with([
                'category:id,hidden_from_booking',
                'options' => fn ($q) => $q->orderBy('sort_order'),
                'extras' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages([
                'services' => [__('booking.validation.services_invalid')],
            ]);
        }

        foreach ($services as $service) {
            if (! $service->isBookableOnline()) {
                throw ValidationException::withMessages([
                    'services' => [__('booking.validation.services_invalid')],
                ]);
            }
        }

        $lines = [];
        foreach ($servicesInput as $idx => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'services' => [__('booking.validation.request_invalid')],
                ]);
            }

            $sid = (int) ($row['id'] ?? 0);
            $service = $services->get($sid);
            if (! $service) {
                throw ValidationException::withMessages([
                    "services.{$idx}.id" => [__('booking.validation.service_invalid')],
                ]);
            }

            $hasVariants = $service->options->isNotEmpty();
            $optRaw = $row['service_option_id'] ?? null;
            $optId = ($optRaw !== null && $optRaw !== '') ? (int) $optRaw : null;

            if ($hasVariants) {
                if (! $optId) {
                    throw ValidationException::withMessages([
                        "services.{$idx}.service_option_id" => [__('booking.validation.option_required')],
                    ]);
                }
                if (! $service->options->contains('id', $optId)) {
                    throw ValidationException::withMessages([
                        "services.{$idx}.service_option_id" => [__('booking.validation.option_wrong_service')],
                    ]);
                }
            } elseif ($optId) {
                throw ValidationException::withMessages([
                    "services.{$idx}.service_option_id" => [__('booking.validation.service_no_options')],
                ]);
            }

            /** @var ServiceOption|null $option */
            $option = $optId ? $service->options->firstWhere('id', $optId) : null;

            $duration = $option ? (int) $option->duration : (int) $service->duration;
            if ($duration <= 0) {
                throw ValidationException::withMessages([
                    "services.{$idx}.id" => [__('booking.validation.line_duration_invalid')],
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
                'extras' => $this->resolveOnlineBookingExtrasForServiceLine($service, $row['extras'] ?? [], $idx),
            ];
        }

        return $lines;
    }

    /**
     * @param  list<mixed>  $extrasInput
     * @return list<array{extra_id: int, duration: int, price: float}>
     */
    private function resolveOnlineBookingExtrasForServiceLine(Service $service, array $extrasInput, int $serviceIdx): array
    {
        if ($extrasInput === []) {
            return [];
        }

        $resolved = [];
        $seen = [];
        foreach ($extrasInput as $exIdx => $exRow) {
            if (! is_array($exRow)) {
                throw ValidationException::withMessages([
                    "services.{$serviceIdx}.extras.{$exIdx}" => [__('booking.validation.extra_invalid')],
                ]);
            }

            $extraId = (int) ($exRow['extra_id'] ?? 0);
            if ($extraId <= 0) {
                throw ValidationException::withMessages([
                    "services.{$serviceIdx}.extras.{$exIdx}.extra_id" => [__('booking.validation.extra_invalid')],
                ]);
            }

            if (isset($seen[$extraId])) {
                continue;
            }

            if (! $service->extras->contains('id', $extraId)) {
                throw ValidationException::withMessages([
                    "services.{$serviceIdx}.extras.{$exIdx}.extra_id" => [__('booking.validation.extra_unavailable')],
                ]);
            }

            /** @var \App\Models\Extra $extra */
            $extra = $service->extras->firstWhere('id', $extraId);
            $resolved[] = [
                'extra_id' => $extraId,
                'duration' => max(0, (int) $extra->duration),
                'price' => round((float) $extra->price, 2),
            ];
            $seen[$extraId] = true;
        }

        return $resolved;
    }

    private function bookingLineTotalDuration(array $line): int
    {
        $base = (int) ($line['duration'] ?? 0);
        $extraMinutes = array_sum(array_map(
            fn (array $extra): int => (int) ($extra['duration'] ?? 0),
            $line['extras'] ?? [],
        ));

        return $base + $extraMinutes;
    }

    private function bookingLineTotalPrice(array $line): float
    {
        $base = (float) ($line['price'] ?? 0);
        $extraPrice = array_sum(array_map(
            fn (array $extra): float => (float) ($extra['price'] ?? 0),
            $line['extras'] ?? [],
        ));

        return round($base + $extraPrice, 2);
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
                    'phone' => [__('booking.validation.phone_invalid')],
                ]);
            }
            if ($name !== '') {
                $client->name = $name;
                $actor->name = $name;
            }
            $invEmail = strtolower(trim((string) ($validated['invoice_email'] ?? '')));
            if ($invEmail !== '' && filter_var($invEmail, FILTER_VALIDATE_EMAIL) && trim((string) ($client->email ?? '')) === '') {
                $client->email = $invEmail;
            }
            if ($emailNorm !== '') {
                $client->email = $emailNorm;
            }
            $client->phone = $phoneE164;
            $nifDigits = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
            if (trim((string) ($client->nif ?? '')) === '' && strlen($nifDigits) === 9) {
                $client->nif = $nifDigits;
            }
            $this->mergeOnlineBookingNotes($client, $validated['notes'] ?? null);
            $client->save();
            $actor->save();

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
        $nifDigits = preg_replace('/\D/', '', (string) ($validated['billing_nif'] ?? ''));
        if (trim((string) ($client->nif ?? '')) === '' && strlen($nifDigits) === 9) {
            $client->nif = $nifDigits;
            $client->save();
        }

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
                'extras' => $line['extras'] ?? [],
            ];
        }

        $title = $client->name.' - '.collect($bookingLines)->pluck('display_name')->implode(', ');
        $notesTrim = isset($validated['notes']) ? trim((string) $validated['notes']) : '';
        $description = $notesTrim !== '' ? $notesTrim : null;

        return DB::transaction(function () use ($title, $startForDb, $endForDb, $description, $userId, $client, $firstService, $servicesPayload, $bookingLines) {
            if ($this->userHasCalendarConflict($userId, $startForDb, $endForDb)) {
                throw ValidationException::withMessages([
                    'time' => [__('booking.validation.slot_taken_short')],
                ]);
            }

            $ev = CalendarEvent::create([
                'store_id' => (int) $client->store_id,
                'title' => $title,
                'start_at' => $startForDb,
                'end_at' => $endForDb,
                'description' => $description,
                'event_type' => CalendarEvent::TYPE_MARCACAO,
                'user_id' => $userId,
                'client_id' => $client->id,
                'service_id' => $firstService->id,
                'status' => CalendarEvent::STATUS_AGENDADO,
                'marcacao_source' => ActivityLogMarcacaoOrigin::ONLINE,
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

            $ev->load('eventServices');
            $ordered = $ev->eventServices->sortBy(fn ($s) => $s->pivot->sort_order)->values();
            foreach ($ordered as $i => $svc) {
                $extras = $bookingLines[$i]['extras'] ?? [];
                foreach ($extras as $j => $ex) {
                    CalendarEventServiceExtra::create([
                        'calendar_event_service_id' => $svc->pivot->id,
                        'extra_id' => (int) ($ex['extra_id'] ?? 0),
                        'duration' => isset($ex['duration']) ? (int) $ex['duration'] : null,
                        'price' => isset($ex['price']) ? (float) $ex['price'] : null,
                        'sort_order' => $j,
                    ]);
                }
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

        // Sininho da receção independente do email da técnica (falhas SMTP não devem engolir o CRM).
        try {
            $this->receptionBookingNotifier->notify($event, 'assigned', null, fromPublicBooking: true);
        } catch (\Throwable $e) {
            \Log::warning('Marcação online: falha ao notificar receção.', [
                'calendar_event_id' => $event->id,
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
                'phone' => [__('booking.validation.phone_invalid')],
            ]);
        }

        $emailNorm = strtolower(trim($email));
        if ($emailNorm === '') {
            throw ValidationException::withMessages([
                'email' => [__('booking.validation.email_required')],
            ]);
        }

        $byPhone = $this->findClientByPhoneE164($phoneE164);
        $byEmail = Client::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->first();

        if ($byPhone && $byEmail && $byPhone->id !== $byEmail->id) {
            throw ValidationException::withMessages([
                'email' => [__('booking.validation.email_phone_mismatch')],
                'phone' => [__('booking.validation.email_phone_mismatch')],
            ]);
        }

        if ($byPhone && ! $byEmail) {
            $clientEmail = $byPhone->email !== null && trim((string) $byPhone->email) !== ''
                ? strtolower(trim((string) $byPhone->email))
                : '';
            if ($clientEmail !== '' && $clientEmail !== $emailNorm) {
                throw ValidationException::withMessages([
                    'email' => [__('booking.validation.email_db_mismatch')],
                ]);
            }
        }

        if ($byEmail && ! $byPhone) {
            if (! $this->phonesMatchClient($byEmail, $phoneE164)) {
                throw ValidationException::withMessages([
                    'phone' => [__('booking.validation.phone_db_mismatch')],
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
                    'email' => [__('booking.validation.account_data_mismatch')],
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
                'phone' => [__('booking.validation.phone_invalid')],
            ]);
        }

        $emailNorm = strtolower(trim($email));
        if ($emailNorm === '') {
            throw ValidationException::withMessages([
                'email' => [__('booking.validation.email_required')],
            ]);
        }

        $byPhone = $this->findClientByPhoneE164($phoneE164);
        $byEmail = Client::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->first();

        if ($byPhone && $byEmail && $byPhone->id !== $byEmail->id) {
            throw ValidationException::withMessages([
                'email' => [__('booking.validation.email_phone_mismatch')],
                'phone' => [__('booking.validation.email_phone_mismatch')],
            ]);
        }

        if ($byPhone && ! $byEmail) {
            $clientEmail = $byPhone->email !== null && trim((string) $byPhone->email) !== ''
                ? strtolower(trim((string) $byPhone->email))
                : '';
            if ($clientEmail !== '' && $clientEmail !== $emailNorm) {
                throw ValidationException::withMessages([
                    'email' => [__('booking.validation.email_db_mismatch')],
                ]);
            }
        }

        if ($byEmail && ! $byPhone) {
            if (! $this->phonesMatchClient($byEmail, $phoneE164)) {
                throw ValidationException::withMessages([
                    'phone' => [__('booking.validation.phone_db_mismatch')],
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
                        'email' => [__('booking.validation.account_data_mismatch')],
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
                'email' => [__('booking.validation.duplicate_save')],
            ]);
        }
    }

    private function assertEmailAvailableForBookingUser(string $emailNorm): void
    {
        $exists = User::query()->whereRaw('LOWER(email) = ?', [$emailNorm])->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => [__('booking.validation.email_already_registered')],
            ]);
        }
    }

    private function mergeOnlineBookingNotes(Client $client, ?string $notes): void
    {
        $notesTrim = isset($notes) ? trim((string) $notes) : '';
        if ($notesTrim === '') {
            return;
        }

        $notesBlock = '[Marcação online] '.$notesTrim;
        $prev = (string) ($client->preferences_notes ?? '');
        $client->preferences_notes = trim($prev !== '' ? $prev."\n\n".$notesBlock : $notesBlock);
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
            'message' => __('booking.validation.requires_login'),
            'errors' => [
                'email' => [__('booking.validation.requires_login_hint')],
            ],
            'requires_login' => true,
        ], 422));
    }

    private function carbonToWeekdayKey(Carbon $day): string
    {
        return WeeklyScheduleWindow::carbonIsoToWeekdayKey($day->dayOfWeekIso);
    }

    private function slotFitsAgentSchedule(Agent $agent, Carbon $start, Carbon $end): bool
    {
        if (! $agent->user_id) {
            return false;
        }

        $tz = (string) config('booking.business_timezone');
        $day = $start->copy()->timezone($tz)->startOfDay();
        $dowKey = $this->carbonToWeekdayKey($day);
        $storeSchedule = app(CurrentStore::class)->get()->normalizedWeeklySchedule();
        $window = WeeklyScheduleWindow::resolveMinutesWindow($agent->weekly_schedule, $dowKey, $storeSchedule);
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
