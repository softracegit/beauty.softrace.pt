<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingSavedCard;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\ExtraCategory;
use App\Models\PersonalTimeType;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Exceptions\AppointmentCancellationException;
use App\Models\ClientWalletTransaction;
use App\Notifications\ClientAppointmentCancelledNotification;
use App\Notifications\ClientAppointmentCreatedNotification;
use App\Notifications\ClientAppointmentRescheduledNotification;
use App\Services\AgendaDepositService;
use App\Services\AgendaSameDayPayableService;
use App\Services\AppointmentCancellationService;
use App\Services\CancellationPolicyService;
use App\Services\CashRegisterService;
use App\Services\ClientWalletService;
use App\Services\MarcacaoServicesActivityLogger;
use App\Services\ReceptionBookingNotifier;
use App\Support\ApplicableFees;
use App\Support\BookingLocale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function __construct(
        private AppointmentCancellationService $cancellationService,
        private CancellationPolicyService $policyService,
        private ClientWalletService $walletService,
        private AgendaDepositService $agendaDepositService,
        private AgendaSameDayPayableService $sameDayPayableService,
        private CashRegisterService $cashRegisterService,
        private MarcacaoServicesActivityLogger $servicesActivityLogger,
        private ReceptionBookingNotifier $receptionBookingNotifier,
    ) {}

    /**
     * Pré-visualização da política de cancelamento (agenda).
     */
    public function cancellationPreview(CalendarEvent $calendarEvent): JsonResponse
    {
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['message' => 'Evento inválido.'], 404);
        }

        if ((int) $calendarEvent->store_id !== (int) current_store_id()) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'preview' => $this->policyService->previewPayload($calendarEvent),
            'policy_notice' => CrmSetting::bookingCancellationPolicyNoticeText((int) $calendarEvent->store_id),
        ]);
    }

    /**
     * Display the calendar (agenda) view.
     */
    public function index()
    {
        $eventTypes = CalendarEvent::eventTypes();
        $personalTimeTypes = PersonalTimeType::forStore(current_store_id())->where('is_active', true)->orderBy('sort_order')->get();
        // Mostrar apenas users com agent ativo e visível na agenda; excluir Administradores.
        $users = User::whereHas('agent', function ($query) {
            $query->where('store_id', current_store_id())
                ->where('status', Agent::STATUS_ACTIVE)
                ->where('visible_in_agenda', true);
        })
            ->with('agent')
            ->whereNotIn('role', [User::ROLE_ADMIN])
            ->orderByRaw('(select agenda_order from agents where agents.user_id = users.id limit 1)')
            ->orderBy('users.name')
            ->get();

        if (auth()->user()->isPrestador()) {
            $users = $users->where('id', auth()->id())->values();
        }

        $today = now();
        $nationalHolidaysPt = $this->ptNationalHolidayDatesBetweenYears((int) $today->format('Y') - 1, (int) $today->format('Y') + 2);
        $posGorjetaEnabled = CrmSetting::posGorjetaEnabled(current_store_id());
        $onlineBookingPaymentRequired = CrmSetting::onlineBookingPaymentRequired(current_store_id());

        $store = current_store()->get();
        $storeWeeklySchedule = $store->normalizedWeeklySchedule();
        [$agendaSlotMin, $agendaSlotMax] = $store->agendaSlotRange();
        $storeHoursLabel = $store->hoursDisplayLabel();
        $cashRegisterOpen = $this->cashRegisterIsOpen();

        return view('agenda.index', compact(
            'eventTypes',
            'users',
            'personalTimeTypes',
            'nationalHolidaysPt',
            'posGorjetaEnabled',
            'onlineBookingPaymentRequired',
            'storeWeeklySchedule',
            'agendaSlotMin',
            'agendaSlotMax',
            'storeHoursLabel',
            'cashRegisterOpen',
        ));
    }

    /**
     * @return array<int, string> Datas Y-m-d dos feriados nacionais de Portugal no intervalo de anos.
     */
    private function ptNationalHolidayDatesBetweenYears(int $yearStart, int $yearEnd): array
    {
        if ($yearEnd < $yearStart) {
            return [];
        }

        $dates = [];
        for ($y = $yearStart; $y <= $yearEnd; $y++) {
            $dates = array_merge($dates, $this->ptNationalHolidayDatesForYear($y));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * @return array<int, string> Datas Y-m-d dos feriados nacionais de Portugal para o ano indicado.
     */
    private function ptNationalHolidayDatesForYear(int $year): array
    {
        // Páscoa (algoritmo de Gauss para calendário gregoriano).
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $easter = Carbon::createMidnightDate($year, $month, $day);

        return [
            Carbon::createMidnightDate($year, 1, 1)->toDateString(),   // Ano Novo
            Carbon::createMidnightDate($year, 4, 25)->toDateString(),  // Dia da Liberdade
            Carbon::createMidnightDate($year, 5, 1)->toDateString(),   // Dia do Trabalhador
            Carbon::createMidnightDate($year, 6, 10)->toDateString(),  // Dia de Portugal
            Carbon::createMidnightDate($year, 8, 15)->toDateString(),  // Assunção
            Carbon::createMidnightDate($year, 10, 5)->toDateString(),  // Implantação da República
            Carbon::createMidnightDate($year, 11, 1)->toDateString(),  // Todos-os-Santos
            Carbon::createMidnightDate($year, 12, 1)->toDateString(),  // Restauração da Independência
            Carbon::createMidnightDate($year, 12, 8)->toDateString(),  // Imaculada Conceição
            Carbon::createMidnightDate($year, 12, 25)->toDateString(), // Natal
            $easter->copy()->subDays(2)->toDateString(),               // Sexta-Feira Santa
            $easter->copy()->addDays(60)->toDateString(),              // Corpo de Deus
        ];
    }

    /**
     * Get consultant resources for the resource view (users the current user can see).
     */
    public function resources(Request $request)
    {
        // Mostrar apenas agents ativos e visíveis na agenda como recursos.
        $agents = Agent::forStore(current_store_id())->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_agenda', true)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->with('user')
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        if (auth()->user()->isPrestador()) {
            $agents = $agents->filter(fn (Agent $agent): bool => (int) $agent->user_id === (int) auth()->id())->values();
        }

        $result = $agents->map(function ($agent) {
            $avatarNum = ($agent->id % 9) + 1;
            $avatarUrl = $agent->avatar
                ? asset('storage/'.$agent->avatar)
                : asset("template/img/avatars/avatar-{$avatarNum}.webp");

            return [
                'id' => (string) $agent->user_id, // Usar user_id como ID do recurso
                'title' => $agent->name,
                'agenda_order' => (int) ($agent->agenda_order ?? 0),
                'extendedProps' => [
                    'avatarUrl' => $avatarUrl,
                    'color' => $agent->color ?? '#6c757d',
                ],
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * Get events in FullCalendar format (JSON).
     * With for_resources=1 returns events for all consultants (for resource view).
     * Optional filter_user_ids: comma-separated user ids (active agents only) to restrict events (e.g. Semana / 3 dias).
     */
    public function events(Request $request)
    {
        $start = $request->get('start');
        $end = $request->get('end');
        $forResources = $request->boolean('for_resources');

        $query = CalendarEvent::query()
            ->forStore(current_store_id())
            ->with([
                'user.agent',
                'service',
                'client',
                'eventServiceItems.service.fees',
                'eventServiceItems.serviceOption',
                'eventServiceItems.extras.extra',
                'eventable',
                'personalTimeType',
                'sales',
                'onlineBooking',
            ])
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO, CalendarEvent::STATUS_FALTOU]);
            });

        // Verificar se o utilizador pode ver todos os eventos (admin ou receção)
        $canViewAll = auth()->user()->canViewAllAgenda();

        $activeAgentUserIds = Agent::forStore(current_store_id())->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_agenda', true)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // Filtro opcional (vistas Semana / 3 dias): filter_user_ids=1,2,3 — só agents ativos; sem permissão total, só o próprio id
        $filterUserIdsRaw = $request->get('filter_user_ids');
        $requestedFilterIds = [];
        if (is_string($filterUserIdsRaw) && $filterUserIdsRaw !== '') {
            $requestedFilterIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $filterUserIdsRaw)))));
        }
        $allowedFilterIds = array_values(array_intersect($requestedFilterIds, $activeAgentUserIds));
        if (! $canViewAll) {
            $allowedFilterIds = array_values(array_intersect($allowedFilterIds, [(int) auth()->id()]));
        }
        $hasConsultantFilter = count($allowedFilterIds) > 0;

        if ($hasConsultantFilter) {
            $query->whereIn('user_id', $allowedFilterIds);
        } elseif (! $forResources) {
            // Se não pode ver todos, mostrar apenas eventos do utilizador atual
            // Se pode ver todos (admin/diretor), mostrar todos os eventos
            if (! $canViewAll) {
                $query->where('user_id', auth()->id());
            } else {
                $query->whereIn('user_id', $activeAgentUserIds);
            }
        } else {
            // Na vista por recurso (dia), só eventos de consultores ativos (excluir Administradores)
            $query->whereIn('user_id', $activeAgentUserIds);
        }

        if ($start) {
            $query->where('end_at', '>=', $start);
        }
        if ($end) {
            $query->where('start_at', '<=', $end);
        }

        $events = $query->get();

        $sameDayPayableCounts = [];
        foreach ($events as $candidate) {
            if (($candidate->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO || ! $candidate->client_id || $candidate->isMarcacaoStatusLocked()) {
                continue;
            }
            $serviceItems = $candidate->eventServiceItems ?? collect();
            $subtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceItems);
            $amountDue = ApplicableFees::amountDueCashFromEventId((int) $candidate->id, $subtotal);
            if ($amountDue <= 0.00001) {
                continue;
            }
            $key = ((int) $candidate->client_id).'|'.optional($candidate->start_at)->format('Y-m-d');
            $sameDayPayableCounts[$key] = (int) (($sameDayPayableCounts[$key] ?? 0) + 1);
        }

        // Na vista de recursos, apenas users com agents ativos são válidos (excluir Administradores)
        $validUserIds = $forResources
            ? collect($activeAgentUserIds)->map(fn ($id) => (string) $id)->flip()->all()
            : [];

        $result = $events->map(function (CalendarEvent $event) use ($forResources, $validUserIds, $sameDayPayableCounts) {
            $classMap = CalendarEvent::typeClassMap();
            $className = $classMap[$event->event_type] ?? 'bg-secondary';
            $agentColor = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL ? null : ($event->user?->agent?->color);
            $serviceItems = $event->eventServiceItems ?? collect();
            $serviceName = $serviceItems->isNotEmpty()
                ? $serviceItems->map(fn ($item) => self::marcacaoServiceLineLabel($item))->filter()->join(', ')
                : ($event->service?->name ?? null);

            $isTempoPessoal = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL;
            $statusIcon = $isTempoPessoal ? null : $event->status_icon;
            $activeSales = $this->activeSalesForEvent((int) $event->id);
            $hasInvoice = $activeSales->isNotEmpty();
            $isCompleted = ($event->status ?? CalendarEvent::STATUS_AGENDADO) === CalendarEvent::STATUS_COMPLETO;
            $includeCatalogFees = ApplicableFees::includeCatalogFeesForCalendarEvent($event);
            $subtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $serviceItems);
            $bookingPaidAmount = ApplicableFees::marcacaoBookingPaidAmountForEvent((int) $event->id);
            $amountDue = self::marcacaoAmountDueFromTotals($subtotal, $activeSales, (int) $event->id);
            $hasActiveCaixaSale = $activeSales->contains(fn (Sale $s) => $s->scope === Sale::SCOPE_CAIXA_LIQUIDACAO);
            $servicesSubtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceItems);
            $invoiceSettled = $this->isMarcacaoFullySettled($event);
            $pendingFinalInvoice = $isCompleted && $servicesSubtotal > 0.00001 && ! $hasActiveCaixaSale && $amountDue <= 0.00001;
            $catalogFees = $includeCatalogFees
                ? ApplicableFees::forServiceIds($serviceItems->pluck('service_id'), (int) $event->store_id)
                : [];
            $paymentMethodLabels = Sale::paymentMethods();
            $paymentLines = $activeSales->map(function (Sale $s) use ($paymentMethodLabels) {
                $scopeLabel = match ($s->scope) {
                    Sale::SCOPE_BOOKING_RESERVA => 'Pré-pagamento',
                    Sale::SCOPE_CAIXA_LIQUIDACAO => 'Pagamento final',
                    default => 'Pagamento',
                };

                return [
                    'label' => $scopeLabel,
                    'amount' => round((float) $s->effectiveAmountPaid(), 2),
                    'method' => $paymentMethodLabels[$s->payment_method] ?? 'Outro',
                ];
            })->values()->all();
            $statusLocked = ! $isTempoPessoal && $event->isMarcacaoStatusLocked();

            $item = [
                'id' => (string) $event->id,
                'title' => $event->title,
                'start' => $event->start_at->toIso8601String(),
                'end' => $event->end_at->toIso8601String(),
                'className' => $className,
                'backgroundColor' => $agentColor ?: ($isTempoPessoal ? '#dee2e6' : null),
                'editable' => ! $invoiceSettled && ! $statusLocked && ! $isCompleted,
                'startEditable' => ! $invoiceSettled && ! $statusLocked && ! $isCompleted,
                'durationEditable' => ! $invoiceSettled && ! $statusLocked && ! $isCompleted,
                'resourceEditable' => ! $invoiceSettled && ! $statusLocked && ! $isCompleted,
                'extendedProps' => [
                    'client_name' => $event->client?->name,
                    'client_avatar_url' => $event->client?->avatar ? asset('storage/'.$event->client->avatar) : null,
                    'client_phone' => $event->client?->phone,
                    'client_formatted_phone' => $event->client?->formatted_phone,
                    'event_type' => $event->event_type,
                    'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                    'status_icon' => $statusIcon,
                    'user_id' => $event->user_id,
                    'user_name' => $event->user?->name,
                    'service_name' => $serviceName,
                    'event_services' => $serviceItems->map(function ($item) {
                        $service = $item->service;
                        if (! $service) {
                            return null;
                        }
                        $dur = ($item->duration ?? $service->duration);
                        $price = (float) ($item->price ?? $service->price);

                        return [
                            'service_id' => (int) $item->service_id,
                            'name' => self::marcacaoServiceLineLabel($item),
                            'duration' => $dur,
                            'price' => $price,
                            'fees' => ($item->service?->fees ?? collect())->map(fn ($f) => [
                                'fee_id' => (int) $f->id,
                                'name' => $f->name,
                                'price' => (float) $f->price,
                                'formatted_price' => $f->formatted_price,
                            ])->values()->all(),
                            'extras' => $item->extras->map(function ($pe) {
                                $extraDuration = $pe->duration ?? $pe->extra?->duration ?? 0;
                                $extraPrice = (float) ($pe->price ?? $pe->extra?->price ?? 0);

                                return [
                                    'name' => $pe->extra?->name ?? '',
                                    'duration' => $extraDuration,
                                    'price' => $extraPrice,
                                ];
                            })->values()->all(),
                        ];
                    })->filter()->values()->all(),
                    'is_time_editable' => $event->isTimeEditable(),
                    'personal_time_type' => $event->personalTimeType ? [
                        'name' => $event->personalTimeType->name,
                        'icon' => $event->personalTimeType->icon,
                        'formatted_duration' => $event->personalTimeType->formatted_duration,
                    ] : null,
                    'has_invoice' => $hasInvoice,
                    'invoice_settled' => $invoiceSettled,
                    'total_amount' => round($subtotal, 2),
                    'apply_catalog_fees' => $includeCatalogFees,
                    'catalog_fees' => $catalogFees,
                    'charged_fees' => $includeCatalogFees
                        ? []
                        : ApplicableFees::chargedFeesForCalendarEvent((int) $event->id),
                    'booking_paid_amount' => $bookingPaidAmount,
                    'amount_due' => $amountDue,
                    'pending_final_invoice' => $pendingFinalInvoice,
                    'same_day_payable_count' => (int) ($sameDayPayableCounts[(int) ($event->client_id ?? 0).'|'.optional($event->start_at)->format('Y-m-d')] ?? 0),
                    'payment_lines' => $paymentLines,
                    'client_id' => $event->client_id,
                    'client_has_email' => (bool) ($event->client_id && $event->client?->email && filter_var($event->client->email, FILTER_VALIDATE_EMAIL)),
                    ...$this->clientBirthdayMetaForEvent($event->client, $event->start_at, (int) $event->store_id),
                ],
            ];
            if ($forResources && $event->user_id) {
                $uid = (string) $event->user_id;
                if (isset($validUserIds[$uid])) {
                    $item['resourceId'] = $uid;
                }
            }

            return $item;
        })->filter(function ($item) use ($forResources) {
            // Filtrar eventos sem resourceId válido na vista de consultor
            if ($forResources && ! isset($item['resourceId'])) {
                return false;
            }

            return true;
        })->values();

        return response()->json($result);
    }

    /**
     * Get services for a member (user with agent), grouped by category (for event type "Marcação").
     */
    public function memberServices(User $user)
    {
        $agent = Agent::query()
            ->forStore(current_store_id())
            ->where('user_id', $user->id)
            ->first();
        if (! $agent) {
            return response()->json(['categories' => []]);
        }

        $services = $agent->services()
            ->with([
                'category',
                'extras' => fn ($q) => $q->orderBy('extras.sort_order'),
                'fees' => fn ($q) => $q->orderBy('fees.sort_order'),
                'options' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->get()
            ->sortBy(function ($s) {
                $catOrder = $s->category?->sort_order ?? 999999;

                return sprintf('%010d-%010d', $catOrder, $s->sort_order);
            })
            ->values();

        $byCategory = $services->groupBy(function ($s) {
            return $s->category_id ?: 0;
        });

        $categoryNames = \App\Models\Category::forStore(current_store_id())->whereIn('id', $byCategory->keys()->filter(fn ($id) => $id !== 0))->pluck('name', 'id');
        $categoryColors = \App\Models\Category::forStore(current_store_id())->whereIn('id', $byCategory->keys()->filter(fn ($id) => $id !== 0))->pluck('color', 'id');

        $categories = [];
        foreach ($byCategory as $categoryId => $items) {
            $categories[] = [
                'id' => $categoryId ?: null,
                'name' => $categoryId ? ($categoryNames[$categoryId] ?? 'Outros') : 'Sem categoria',
                'color' => $categoryId ? ($categoryColors[$categoryId] ?? '#6c757d') : '#6c757d',
                'services' => $items->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'duration' => $s->duration,
                    'formatted_duration' => $s->formatted_duration,
                    'price' => (float) $s->price,
                    'formatted_price' => $s->formatted_price,
                    'has_options' => $s->options->isNotEmpty(),
                    'options' => $s->options->map(fn ($o) => [
                        'id' => $o->id,
                        'name' => $o->name,
                        'duration' => $o->duration,
                        'price' => (float) $o->price,
                        'online_price' => (float) $o->online_price,
                        'is_baseline' => (bool) $o->is_baseline,
                        'sort_order' => $o->sort_order,
                        'formatted_duration' => $o->formatted_duration,
                        'formatted_price' => $o->formatted_price,
                    ])->values()->all(),
                    'extras' => $s->extras->map(fn ($e) => [
                        'id' => $e->id,
                        'name' => $e->name,
                        'duration' => $e->duration,
                        'price' => (float) $e->price,
                        'formatted_duration' => $e->formatted_duration,
                        'formatted_price' => $e->formatted_price,
                    ])->values()->all(),
                    'fees' => $s->fees->map(fn ($f) => [
                        'fee_id' => (int) $f->id,
                        'name' => $f->name,
                        'price' => (float) $f->price,
                        'formatted_price' => $f->formatted_price,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        }

        return response()->json(['categories' => $categories]);
    }

    /**
     * Saldo da carteira de créditos do cliente (caixa na agenda).
     */
    public function clientWallet(Client $client): JsonResponse
    {
        if (auth()->user()->isPrestador()) {
            return response()->json(['message' => 'Sem permissão.'], 403);
        }

        if ((int) $client->store_id !== (int) current_store_id()) {
            abort(404);
        }

        $balanceCents = $this->walletService->getBalanceCents($client);

        return response()->json([
            'success' => true,
            'balance_cents' => $balanceCents,
            'balance_formatted' => number_format($balanceCents / 100, 2, ',', ' ').' €',
        ]);
    }

    /**
     * Cartões guardados do cliente (cobrança de reserva na receção).
     */
    public function clientSavedCards(Client $client): JsonResponse
    {
        if (auth()->user()->isPrestador()) {
            return response()->json(['message' => 'Sem permissão.'], 403);
        }

        if ((int) $client->store_id !== (int) current_store_id()) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'cards' => BookingSavedCard::payloadListForClient($client),
        ]);
    }

    /**
     * Search clients for Nova Marcação modal (JSON).
     */
    public function clients(\Illuminate\Http\Request $request)
    {
        $search = $request->get('q', '');
        $clientId = $request->get('client_id');

        if ($clientId) {
            $client = \App\Models\Client::forStore(current_store_id())->whereKey($clientId)->first();
            if ($client) {
                $arr = $client->only(['id', 'name', 'email', 'phone', 'nif']);
                $arr['formatted_phone'] = $client->formatted_phone;
                $arr['avatar_url'] = $client->avatar ? asset('storage/'.$client->avatar) : null;
                $arr['birth_date'] = $client->birth_date?->format('Y-m-d');

                return response()->json([$this->sanitizeClientPayloadForUser($arr)]);
            }

            return response()->json([]);
        }

        $query = \App\Models\Client::query()->forStore(current_store_id())->orderBy('name')->limit(50);

        if (strlen($search) >= 1) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                if (auth()->user()->canViewClientContactDetails()) {
                    $q->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }
            });
        }

        $clients = $query->get(['id', 'name', 'email', 'phone', 'nif', 'avatar']);
        $result = $clients->map(function ($c) {
            $arr = $c->only(['id', 'name', 'email', 'phone', 'nif']);
            $arr['formatted_phone'] = $c->formatted_phone;
            $arr['avatar_url'] = $c->avatar ? asset('storage/'.$c->avatar) : null;

            return $this->sanitizeClientPayloadForUser($arr);
        });

        return response()->json($result);
    }

    /**
     * Create a new client from the agenda (quick create).
     * Returns JSON in the same format as clients() search.
     */
    public function storeClient(Request $request)
    {
        if ($request->input('email') === '') {
            $request->merge(['email' => null]);
        }

        // Ordem: telemóvel (obrigatório) primeiro; só depois email (opcional).
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        if (Client::existsWithSamePhoneAs($validated['phone'])) {
            throw ValidationException::withMessages([
                'phone' => ['Este número de telemóvel já está associado a um cliente.'],
            ]);
        }

        $validated = array_merge($validated, $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->where(fn ($q) => $q->where('store_id', current_store_id()))],
        ], [
            'email.unique' => 'Este email já está associado a um cliente.',
        ]));

        $client = Client::create([
            'store_id' => current_store_id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
        ]);

        $result = [
            'id' => (string) $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'nif' => $client->nif,
            'formatted_phone' => $client->formatted_phone,
            'avatar_url' => $client->avatar ? asset('storage/'.$client->avatar) : null,
        ];

        return response()->json($this->sanitizeClientPayloadForUser($result));
    }

    public function updateClientNif(Request $request, Client $client)
    {
        if (auth()->user()->isPrestador()) {
            return response()->json(['message' => 'Sem permissão.'], 403);
        }
        $validated = $request->validate([
            'nif' => ['required', 'digits:9'],
        ], [
            'nif.required' => 'Indique o NIF do cliente.',
            'nif.digits' => 'O NIF deve ter 9 dígitos.',
        ]);

        $client->nif = $validated['nif'];
        $client->save();

        return response()->json([
            'id' => (string) $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'nif' => $client->nif,
            'formatted_phone' => $client->formatted_phone,
            'avatar_url' => $client->avatar ? asset('storage/'.$client->avatar) : null,
        ]);
    }

    /**
     * Show a single event (for modal/details).
     * In resource view the user may view events of other consultants (permission check can be added).
     */
    public function show(CalendarEvent $calendarEvent)
    {
        $this->assertCanAccessCalendarEvent($calendarEvent);

        try {
            $calendarEvent->load(['user', 'service', 'client', 'eventServices.category', 'eventServices.fees', 'eventable', 'personalTimeType', 'sales', 'sale']);
            $calendarEvent->eventServices->each(fn ($s) => $s->pivot->load(['extras', 'extras.extra']));

            $userAvatarUrl = null;
            try {
                if ($calendarEvent->user) {
                    $calendarEvent->user->load('agent');
                    if ($calendarEvent->user->agent) {
                        $agent = $calendarEvent->user->agent;
                        $avatarNum = ($agent->id % 9) + 1;
                        $userAvatarUrl = $agent->avatar
                            ? asset('storage/'.$agent->avatar)
                            : asset("template/img/avatars/avatar-{$avatarNum}.webp");
                    }
                }
            } catch (\Exception $e) {
                // Se houver erro ao carregar agent, continua sem avatar
                $userAvatarUrl = null;
            }

            $isTempoPessoal = ($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_TEMPO_PESSOAL;
            $payload = [
                'id' => $calendarEvent->id,
                'title' => $calendarEvent->title ?? '',
                'start_at' => $calendarEvent->start_at ? $calendarEvent->start_at->toIso8601String() : null,
                'end_at' => $calendarEvent->end_at ? $calendarEvent->end_at->toIso8601String() : null,
                'description' => $calendarEvent->description,
                'event_type' => $calendarEvent->event_type ?? 'manual',
                'event_type_label' => CalendarEvent::eventTypes()[$calendarEvent->event_type] ?? ($calendarEvent->event_type ?? 'Manual'),
                'status' => $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO,
                'status_label' => $isTempoPessoal ? 'Tempo pessoal' : (CalendarEvent::statuses()[$calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado'),
                'status_icon' => $isTempoPessoal ? null : $calendarEvent->status_icon,
                'cancellation_reason' => $calendarEvent->cancellation_reason,
                'user_id' => $calendarEvent->user_id,
                'user_name' => $calendarEvent->user?->name,
                'user_avatar_url' => $userAvatarUrl,
                'service_id' => $calendarEvent->service_id,
                'service_name' => $calendarEvent->eventServices->isNotEmpty()
                    ? $calendarEvent->eventServices->map(fn ($s) => self::marcacaoServiceLineLabel($s->pivot))->join(', ')
                    : ($calendarEvent->service?->name ?? null),
                'client_id' => $calendarEvent->client_id,
                'client_name' => $calendarEvent->client?->name,
                'client_has_email' => (bool) ($calendarEvent->client_id && $calendarEvent->client?->email && filter_var($calendarEvent->client->email, FILTER_VALIDATE_EMAIL)),
                'client_email' => $calendarEvent->client?->email,
                'client_phone' => $calendarEvent->client?->phone,
                'client_nif' => $calendarEvent->client?->nif,
                'client_formatted_phone' => $calendarEvent->client?->formatted_phone,
                'client_avatar_url' => $calendarEvent->client?->avatar ? asset('storage/'.$calendarEvent->client->avatar) : null,
                'client_birth_date' => $calendarEvent->client?->birth_date?->format('Y-m-d'),
                ...$this->clientBirthdayMetaForEvent($calendarEvent->client, $calendarEvent->start_at, (int) $calendarEvent->store_id),
                'event_services' => $calendarEvent->eventServices->map(function ($s) {
                    $cat = $s->category;
                    $color = $cat?->color ?? '#6c757d';
                    $duration = $s->pivot->duration ?? $s->duration;
                    $price = (float) ($s->pivot->price ?? $s->price);
                    $extras = $s->pivot->extras->map(fn ($pe) => [
                        'extra_id' => $pe->extra_id,
                        'name' => $pe->extra?->name ?? '',
                        'duration' => $pe->duration ?? $pe->extra?->duration ?? 0,
                        'price' => (float) ($pe->price ?? $pe->extra?->price ?? 0),
                        'formatted_duration' => $this->formatDurationMinutes((int) ($pe->duration ?? $pe->extra?->duration ?? 0)),
                        'formatted_price' => number_format((float) ($pe->price ?? $pe->extra?->price ?? 0), 2, ',', '.').' €',
                    ])->values()->all();

                    return [
                        'id' => $s->id,
                        'service_id' => (int) $s->id,
                        'name' => self::marcacaoServiceLineLabel($s->pivot),
                        'fees' => $s->fees->map(fn ($f) => [
                            'fee_id' => (int) $f->id,
                            'name' => $f->name,
                            'price' => (float) $f->price,
                            'formatted_price' => $f->formatted_price,
                        ])->values()->all(),
                        'service_option_id' => $s->pivot->service_option_id,
                        'option_name' => $s->pivot->option_name,
                        'duration' => $duration,
                        'price' => $price,
                        'original_price' => $s->pivot->original_price !== null ? (float) $s->pivot->original_price : $price,
                        'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.').' €' : $s->formatted_price,
                        'formatted_duration' => $this->formatDurationMinutes((int) $duration),
                        'color' => $color,
                        'category_name' => $cat?->name ?? '',
                        'extras' => $extras,
                    ];
                })->values()->all(),
                'is_source_editable' => $calendarEvent->isSourceEditable(),
                'is_deletable' => $calendarEvent->isDeletableFromCalendar(),
                'is_time_editable' => $calendarEvent->isTimeEditable(),
                'personal_time_type_id' => $calendarEvent->personal_time_type_id,
                'personal_time_type' => $calendarEvent->personalTimeType ? [
                    'id' => $calendarEvent->personalTimeType->id,
                    'name' => $calendarEvent->personalTimeType->name,
                    'icon' => $calendarEvent->personalTimeType->icon,
                    'duration' => $calendarEvent->personalTimeType->duration,
                    'formatted_duration' => $calendarEvent->personalTimeType->formatted_duration,
                ] : null,
                'existing_sale' => null,
                'sales_invoices' => [],
                'booking_paid_amount' => 0.0,
                'invoice_settled' => false,
            ];

            $servicesSubtotal = (float) collect($payload['event_services'] ?? [])->sum(function (array $row): float {
                $base = (float) ($row['price'] ?? 0);
                $extras = collect($row['extras'] ?? [])->sum(fn (array $ex): float => (float) ($ex['price'] ?? 0));

                return $base + $extras;
            });
            $includeCatalogFees = ApplicableFees::includeCatalogFeesForCalendarEvent($calendarEvent);
            $catalogFees = $includeCatalogFees
                ? ApplicableFees::forEventServicesPayload($payload['event_services'], (int) $calendarEvent->store_id)
                : [];
            $subtotalForDue = $includeCatalogFees
                ? round($servicesSubtotal + ApplicableFees::sumPrices($catalogFees), 2)
                : $servicesSubtotal;
            $payload['apply_catalog_fees'] = $includeCatalogFees;
            $payload['catalog_fees'] = $catalogFees;
            $payload['charged_fees'] = $includeCatalogFees
                ? []
                : ApplicableFees::chargedFeesForCalendarEvent((int) $calendarEvent->id);
            $isCancelledEvent = in_array(($calendarEvent->status ?? ''), [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true);
            $salesPaidHistorical = (float) Sale::query()
                ->where('calendar_event_id', $calendarEvent->id)
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)'));
            $bookingPaid = (float) Booking::query()
                ->where('calendar_event_id', $calendarEvent->id)
                ->where('payment_status', Booking::PAYMENT_PAID)
                ->orderByDesc('id')
                ->value('paid_amount');
            $activeSalesForPaid = $calendarEvent->sales->filter(fn (Sale $s) => $s->status !== Sale::STATUS_ANULADO);
            $bookingReservaPaid = round((float) $activeSalesForPaid
                ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
                ->sum(fn (Sale $s): float => (float) $s->effectiveAmountPaid()), 2);
            $historicalPaid = round(max($salesPaidHistorical, $bookingPaid, $bookingReservaPaid, 0.0), 2);
            $payload['booking_paid_amount'] = ApplicableFees::marcacaoBookingPaidAmountForEvent((int) $calendarEvent->id);
            $payload['invoice_settled'] = $this->isMarcacaoFullySettled($calendarEvent);
            if ($isCancelledEvent && $historicalPaid > 0.00001 && $servicesSubtotal > 0.00001) {
                // Em marcações anuladas preservamos o histórico de pagamentos no resumo do offcanvas.
                $payload['invoice_settled'] = ($historicalPaid + 0.00001) >= $servicesSubtotal;
            }
            $amountDue = ApplicableFees::amountDueCashFromEventId((int) $calendarEvent->id, $subtotalForDue);
            $payload['amount_due'] = $amountDue;
            $pivotSaleIds = \Illuminate\Support\Facades\DB::table('sale_calendar_events')
                ->where('calendar_event_id', (int) $calendarEvent->id)
                ->pluck('sale_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $activeCaixaSaleRecord = Sale::query()
                ->where(function ($q) use ($calendarEvent, $pivotSaleIds): void {
                    $q->where('calendar_event_id', $calendarEvent->id);
                    if ($pivotSaleIds !== []) {
                        $q->orWhereIn('id', $pivotSaleIds);
                    }
                })
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->orderByDesc('id')
                ->first();
            $payload['active_caixa_sale'] = $activeCaixaSaleRecord ? [
                'id' => $activeCaixaSaleRecord->id,
                'numero_fatura' => $activeCaixaSaleRecord->numero_fatura,
                'pdf_url' => route('sales.pdf', $activeCaixaSaleRecord),
                'vendus_url' => $activeCaixaSaleRecord->vendus_document_id ? route('sales.vendus.pdf', $activeCaixaSaleRecord) : null,
                'scope' => $activeCaixaSaleRecord->scope,
                'invoice_status' => $activeCaixaSaleRecord->invoice_status ?? Sale::INVOICE_STATUS_FATURADO,
                'amount' => round($activeCaixaSaleRecord->effectiveAmountPaid(), 2),
            ] : null;
            $payload['pending_final_invoice'] = ($calendarEvent->status === CalendarEvent::STATUS_COMPLETO)
                && $servicesSubtotal > 0.00001
                && $activeCaixaSaleRecord === null
                && $amountDue <= 0.00001;
            // Fatura final anulada: guarda o valor original (para re-emitir) e, se houver, o link da nota de crédito.
            $cancelledCaixaSaleRecord = Sale::query()
                ->where(function ($q) use ($calendarEvent, $pivotSaleIds): void {
                    $q->where('calendar_event_id', $calendarEvent->id);
                    if ($pivotSaleIds !== []) {
                        $q->orWhereIn('id', $pivotSaleIds);
                    }
                })
                ->where('status', Sale::STATUS_ANULADO)
                ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->orderByDesc('id')
                ->first();
            $payload['cancelled_final_invoice'] = $cancelledCaixaSaleRecord ? [
                'id' => $cancelledCaixaSaleRecord->id,
                'numero_fatura' => $cancelledCaixaSaleRecord->numero_fatura,
                'credit_note_pdf_url' => $cancelledCaixaSaleRecord->hasCreditNote()
                    ? route('sales.credit-note.pdf', $cancelledCaixaSaleRecord)
                    : null,
                'amount' => round($cancelledCaixaSaleRecord->effectiveAmountPaid(), 2),
            ] : null;
            $payload['event_detail_nif_only_editable'] = ($calendarEvent->status === CalendarEvent::STATUS_COMPLETO)
                && $this->isMarcacaoFullySettled($calendarEvent)
                && $activeCaixaSaleRecord === null;
            $isPartial = $payload['booking_paid_amount'] > 0.00001 && $amountDue > 0.00001;

            $sale = Sale::query()
                ->where(function ($q) use ($calendarEvent, $pivotSaleIds): void {
                    $q->where('calendar_event_id', $calendarEvent->id);
                    if ($pivotSaleIds !== []) {
                        $q->orWhereIn('id', $pivotSaleIds);
                    }
                })
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->latest('id')
                ->first();
            if ($sale && $sale->status !== Sale::STATUS_ANULADO) {
                $payload['existing_sale'] = [
                    'id' => $sale->id,
                    'numero_fatura' => $sale->numero_fatura,
                    'amount_due' => $amountDue,
                    'is_partial' => $isPartial,
                    'pdf_url' => route('sales.pdf', $sale),
                ];
            }

            $payload['sales_invoices'] = $this->salesInvoicesForCalendarEvent($calendarEvent->id);

            if (($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO) {
                $payload = array_merge($payload, $this->marcacaoDepositPayloadForEvent($calendarEvent, $servicesSubtotal));
                $payload['prepayment_wallet_only'] = $this->marcacaoPrepaymentWalletOnly(
                    $calendarEvent,
                    (float) ($payload['booking_paid_amount'] ?? 0),
                );
                $payload['same_day_payable'] = $this->sameDayPayableService->summaryForEvent($calendarEvent);
            }

            if ($calendarEvent->eventable_type === \App\Models\Visit::class && $calendarEvent->eventable_id && $calendarEvent->eventable) {
                try {
                    $visit = $calendarEvent->eventable->load(['opportunity.client', 'property']);
                    $payload['visit'] = [
                        'id' => $visit->id,
                        'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
                        'status' => $visit->status,
                        'opportunity_id' => $visit->opportunity_id,
                        'opportunity_reference' => $visit->opportunity?->reference ?? null,
                        'property_id' => $visit->property_id,
                        'property_title' => $visit->property?->title ?? null,
                        'property_reference' => $visit->property?->reference ?? null,
                        'client_name' => $visit->opportunity?->client?->name ?? null,
                    ];
                } catch (\Exception $e) {
                    // Se houver erro ao carregar visit, continua sem detalhes
                }
            }

            if ($calendarEvent->eventable_type === \App\Models\Lead::class && $calendarEvent->eventable_id && $calendarEvent->eventable) {
                try {
                    $lead = $calendarEvent->eventable;
                    $payload['lead'] = [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'phone' => $lead->phone,
                        'formatted_phone' => $lead->formatted_phone,
                        'status' => $lead->status,
                    ];
                } catch (\Exception $e) {
                    // Se houver erro ao carregar lead, continua sem detalhes
                }
            }

            $payload['cash_register_open'] = $this->cashRegisterIsOpen();

            return response()->json($this->sanitizeEventPayloadForUser($payload));
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar evento: '.$e->getMessage(), [
                'event_id' => $calendarEvent->id,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Erro ao carregar detalhes do evento.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sameDayPayable(CalendarEvent $calendarEvent): JsonResponse
    {
        if ((int) $calendarEvent->store_id !== (int) current_store_id()) {
            abort(404);
        }

        $this->assertCanAccessCalendarEvent($calendarEvent);

        if (auth()->user()->isPrestador()) {
            return response()->json([
                'count' => 0,
                'total_due' => 0.0,
                'rows' => [],
            ]);
        }

        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json([
                'count' => 0,
                'total_due' => 0.0,
                'rows' => [],
            ]);
        }

        return response()->json($this->sameDayPayableService->summaryForEvent($calendarEvent));
    }

    /**
     * Store a new manual (or outro) event.
     */
    public function store(Request $request)
    {
        $rules = [
            'title' => ['required_without:personal_time_type_id', 'nullable', 'string', 'max:255'],
            'personal_time_type_id' => ['nullable', Rule::exists('personal_time_types', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'description' => ['nullable', 'string'],
            'event_type' => ['required', 'in:manual,outro,marcacao,tempo_pessoal'],
            'user_id' => ['nullable', Rule::exists('agents', 'user_id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'services.*.service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
            'services.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => [
                'nullable',
                Rule::exists('extras', 'id')->where(fn ($q) => $q->whereIn(
                    'extra_category_id',
                    ExtraCategory::query()->forStore(current_store_id())->select('id')
                )),
            ],
            'services.*.extras.*.duration' => ['nullable', 'integer', 'min:0'],
            'services.*.extras.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
        $validated = $request->validate($rules);

        $servicesPayload = $request->input('services', []);
        if (is_array($servicesPayload) && $servicesPayload !== []) {
            $this->assertMarcacaoServicesOptionsValid($servicesPayload);
        }

        if (($validated['event_type'] ?? '') === CalendarEvent::TYPE_TEMPO_PESSOAL) {
            $personalTypeId = $validated['personal_time_type_id'] ?? null;
            if ($personalTypeId) {
                $type = PersonalTimeType::forStore(current_store_id())->find($personalTypeId);
                $validated['title'] = $type?->name ?? $validated['title'] ?? 'Tempo pessoal';
                $validated['personal_time_type_id'] = (int) $personalTypeId;
            } else {
                $validated['title'] = $validated['title'] ?? 'Tempo pessoal';
            }
        }

        if (($validated['event_type'] ?? '') === CalendarEvent::TYPE_MARCACAO) {
            $this->assertMarcacaoClientRequired($request);
            if (! empty($servicesPayload)) {
                $validated['service_id'] = (int) $servicesPayload[0]['service_id'];
            } else {
                $request->validate(['service_id' => ['required', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))]]);
                $validated['service_id'] = $request->input('service_id');
            }
        } else {
            $validated['service_id'] = null;
        }

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();
        if (auth()->user()->isPrestador()) {
            $validated['user_id'] = auth()->id();
        }
        $validated['client_id'] = $request->input('client_id');
        if ($validated['user_id'] && User::find($validated['user_id'])?->role === User::ROLE_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível atribuir o evento a um Administrador.',
            ], 422);
        }
        $validated['status'] = $validated['status'] ?? CalendarEvent::STATUS_AGENDADO;
        $validated['store_id'] = current_store_id();

        $event = CalendarEvent::create($validated);

        if (! empty($servicesPayload)) {
            $servicesById = Service::query()
                ->forStore(current_store_id())
                ->whereIn('id', array_values(array_unique(array_map(fn ($row) => (int) ($row['service_id'] ?? 0), $servicesPayload))))
                ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
                ->get()
                ->keyBy('id');
            foreach ($servicesPayload as $i => $item) {
                $pivot = $this->pivotAttributesForMarcacaoServiceLine($item, $i, $servicesById);
                $event->eventServices()->attach((int) $item['service_id'], $pivot);
            }
            $event->load('eventServices');
            $ordered = $event->eventServices->sortBy(fn ($s) => $s->pivot->sort_order)->values();
            foreach ($ordered as $i => $svc) {
                $extras = $servicesPayload[$i]['extras'] ?? [];
                foreach ($extras as $j => $ex) {
                    \App\Models\CalendarEventServiceExtra::create([
                        'calendar_event_service_id' => $svc->pivot->id,
                        'extra_id' => (int) ($ex['extra_id'] ?? 0),
                        'duration' => isset($ex['duration']) ? (int) $ex['duration'] : null,
                        'price' => isset($ex['price']) ? (float) $ex['price'] : null,
                        'sort_order' => $j,
                    ]);
                }
            }
            if ($event->event_type === CalendarEvent::TYPE_MARCACAO) {
                $this->servicesActivityLogger->logAssociated(
                    $event,
                    $this->marcacaoServicesSnapshotFromPayload($servicesPayload),
                    auth()->user(),
                );
            }
        }

        if ($event->event_type === CalendarEvent::TYPE_MARCACAO && $event->user_id) {
            $event->load(['client', 'service', 'eventServices']);
            $this->notifyMarcacaoRecipient((int) $event->user_id, $event, 'assigned');
            $this->notifyClientAppointmentCreated($event);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento criado com sucesso.',
            'event' => $this->formatEventForCalendar($event),
        ]);
    }

    /**
     * Update an event. For source-linked events only start_at/end_at can be updated.
     * user_id can be updated when reassigning consultant (e.g. drag to another column in resource view).
     */
    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $this->assertCanAccessCalendarEvent($calendarEvent);

        if ($denied = $this->denyPrestadorRestrictedMarcacaoUpdate($request, $calendarEvent)) {
            return $denied;
        }

        if ($this->isMarcacaoFullySettled($calendarEvent)) {
            return response()->json([
                'success' => false,
                'message' => 'Marcação faturada; reverta a venda para editar.',
            ], 422);
        }

        if (($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO && $calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Marcações com estado «Faltou» ou «Cancelado» não podem ser alteradas.',
            ], 422);
        }

        $rules = [
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'user_id' => ['nullable', Rule::exists('agents', 'user_id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'status' => ['sometimes', 'string', 'in:agendado,notificado,confirmado,chegou,iniciado,terminado,faltou,cancelado'],
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'cancellation_type' => ['nullable', 'string', 'in:faltou,cancelado'],
            'refund_reserva' => ['nullable', 'boolean'],
            'avisou_dentro_prazo' => ['nullable', 'boolean'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'services.*.service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
            'services.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => [
                'nullable',
                Rule::exists('extras', 'id')->where(fn ($q) => $q->whereIn(
                    'extra_category_id',
                    ExtraCategory::query()->forStore(current_store_id())->select('id')
                )),
            ],
            'services.*.extras.*.duration' => ['nullable', 'integer', 'min:0'],
            'services.*.extras.*.price' => ['nullable', 'numeric', 'min:0'],
            'notify_client' => ['sometimes', 'boolean'],
        ];

        if ($calendarEvent->isSourceEditable()) {
            $rules['title'] = ['sometimes', 'string', 'max:255'];
            $rules['personal_time_type_id'] = ['nullable', Rule::exists('personal_time_types', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))];
            $rules['description'] = ['nullable', 'string'];
            $rules['event_type'] = ['sometimes', 'in:manual,outro,marcacao,tempo_pessoal'];
            $rules['service_id'] = ['nullable', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))];
        }

        $validated = $request->validate($rules);

        if (($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO && $request->has('client_id')) {
            $this->assertMarcacaoClientRequired($request);
        }

        $servicesPayload = $request->input('services', []);
        if (is_array($servicesPayload) && $servicesPayload !== []) {
            $this->assertMarcacaoServicesOptionsValid($servicesPayload);
        }

        $prevStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        $timeChanged = false;
        $userIdChanged = false;
        $newAssigneeUserId = null;
        $statusChangedInUpdate = false;

        if (isset($validated['event_type']) && $validated['event_type'] === CalendarEvent::TYPE_MARCACAO && $calendarEvent->isSourceEditable()) {
            if (! empty($servicesPayload)) {
                $validated['service_id'] = (int) $servicesPayload[0]['service_id'];
            } elseif (! array_key_exists('service_id', $validated)) {
                $request->validate(['service_id' => ['required', Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))]]);
                $validated['service_id'] = $request->input('service_id');
            }
        } elseif (isset($validated['event_type']) && $validated['event_type'] !== CalendarEvent::TYPE_MARCACAO) {
            $validated['service_id'] = null;
        }

        $tz = config('app.timezone');

        $notifyClientPrevStart = null;
        $notifyClientPrevEnd = null;
        if ($calendarEvent->isTimeEditable() && (isset($validated['start_at']) || isset($validated['end_at']))) {
            $notifyClientPrevStart = $calendarEvent->start_at?->copy();
            $notifyClientPrevEnd = $calendarEvent->end_at?->copy();
        }

        if ($calendarEvent->isTimeEditable() && (isset($validated['start_at']) || isset($validated['end_at']))) {
            $updates = [];
            if (isset($validated['start_at'])) {
                $newStart = Carbon::parse($validated['start_at'])->timezone($tz)->format('Y-m-d H:i:s');
                $oldStart = $calendarEvent->start_at?->copy()->timezone($tz)->format('Y-m-d H:i:s');
                if ($newStart !== $oldStart) {
                    $updates['start_at'] = $validated['start_at'];
                }
            }
            if (isset($validated['end_at'])) {
                $newEnd = Carbon::parse($validated['end_at'])->timezone($tz)->format('Y-m-d H:i:s');
                $oldEnd = $calendarEvent->end_at?->copy()->timezone($tz)->format('Y-m-d H:i:s');
                if ($newEnd !== $oldEnd) {
                    $updates['end_at'] = $validated['end_at'];
                }
            }
            if ($updates !== []) {
                $timeChanged = true;
                $calendarEvent->update($updates);

                if ($calendarEvent->eventable_type === \App\Models\Visit::class && $calendarEvent->eventable) {
                    $calendarEvent->eventable->update([
                        'scheduled_at' => $calendarEvent->start_at,
                    ]);
                }
                if ($calendarEvent->eventable_type === \App\Models\Lead::class && $calendarEvent->eventable) {
                    $calendarEvent->eventable->update([
                        'scheduled_at' => $calendarEvent->start_at,
                    ]);
                }
            }
        }

        if (array_key_exists('user_id', $validated)) {
            $newUserId = $validated['user_id'] ? (int) $validated['user_id'] : null;
            if ($newUserId && User::find($newUserId)?->role === User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível atribuir o evento a um Administrador.',
                ], 422);
            }
            $currentUserId = $calendarEvent->user_id ? (int) $calendarEvent->user_id : null;
            if ($currentUserId !== $newUserId) {
                $userIdChanged = true;
                $newAssigneeUserId = $newUserId;
                $calendarEvent->user_id = $newUserId;
                $calendarEvent->save();
            }
        }

        if (isset($validated['status'])) {
            $newStatus = $validated['status'];
            $update = ['status' => $newStatus];
            if (in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true)) {
                $update['cancellation_reason'] = isset($validated['cancellation_reason']) ? trim($validated['cancellation_reason']) ?: null : null;
                $update['cancellation_type'] = $validated['cancellation_type'] ?? $newStatus;
                $update['refund_reserva'] = array_key_exists('refund_reserva', $validated) ? (bool) $validated['refund_reserva'] : null;
                $update['avisou_dentro_prazo'] = array_key_exists('avisou_dentro_prazo', $validated) ? (bool) $validated['avisou_dentro_prazo'] : null;
            } else {
                $update['cancellation_reason'] = null;
                $update['cancellation_type'] = null;
                $update['refund_reserva'] = null;
                $update['avisou_dentro_prazo'] = null;
            }
            if ($this->calendarEventStatusPayloadDiffers($calendarEvent, $update)) {
                $calendarEvent->update($update);
                $statusChangedInUpdate = true;
            }
        }

        if ($calendarEvent->isSourceEditable()) {
            $allowed = ['title', 'description', 'event_type', 'service_id', 'client_id', 'personal_time_type_id'];
            $toUpdate = array_filter($validated, fn ($k) => in_array($k, $allowed, true), ARRAY_FILTER_USE_KEY);
            if (isset($toUpdate['personal_time_type_id']) && $calendarEvent->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL) {
                $type = PersonalTimeType::forStore(current_store_id())->find($toUpdate['personal_time_type_id']);
                if ($type) {
                    $toUpdate['title'] = $type->name;
                }
            }
            $toUpdate = $this->filterCalendarEventScalarChanges($calendarEvent, $toUpdate);

            $servicesWillChange = false;
            $beforeServicesSnapshot = [];
            $afterServicesSnapshot = [];
            if (! empty($servicesPayload) && $calendarEvent->event_type === CalendarEvent::TYPE_MARCACAO) {
                $beforeServicesSnapshot = $this->marcacaoServicesSnapshotFromModel($calendarEvent);
                $afterServicesSnapshot = $this->marcacaoServicesSnapshotFromPayload($servicesPayload);
                $servicesWillChange = json_encode($beforeServicesSnapshot) !== json_encode($afterServicesSnapshot);
            }

            $pendingTitleForServices = null;
            if ($servicesWillChange && array_key_exists('title', $toUpdate)) {
                $pendingTitleForServices = $toUpdate['title'];
                unset($toUpdate['title']);
            }

            if ($toUpdate !== []) {
                $calendarEvent->update($toUpdate);
            }

            if ($servicesWillChange) {
                $calendarEvent->eventServices()->detach();
                $servicesById = Service::query()
                    ->forStore(current_store_id())
                    ->whereIn('id', array_values(array_unique(array_map(fn ($row) => (int) ($row['service_id'] ?? 0), $servicesPayload))))
                    ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
                    ->get()
                    ->keyBy('id');
                foreach ($servicesPayload as $i => $item) {
                    $pivot = $this->pivotAttributesForMarcacaoServiceLine($item, $i, $servicesById);
                    $calendarEvent->eventServices()->attach((int) $item['service_id'], $pivot);
                }
                $calendarEvent->load('eventServices');
                $ordered = $calendarEvent->eventServices->sortBy(fn ($s) => $s->pivot->sort_order)->values();
                foreach ($ordered as $i => $svc) {
                    $extras = $servicesPayload[$i]['extras'] ?? [];
                    foreach ($extras as $j => $ex) {
                        \App\Models\CalendarEventServiceExtra::create([
                            'calendar_event_service_id' => $svc->pivot->id,
                            'extra_id' => (int) ($ex['extra_id'] ?? 0),
                            'duration' => isset($ex['duration']) ? (int) $ex['duration'] : null,
                            'price' => isset($ex['price']) ? (float) $ex['price'] : null,
                            'sort_order' => $j,
                        ]);
                    }
                }
                $calendarEvent->refresh();

                if ($pendingTitleForServices !== null) {
                    $calendarEvent->disableLogging();
                    $calendarEvent->update(['title' => $pendingTitleForServices]);
                    $calendarEvent->enableLogging();
                }

                $this->servicesActivityLogger->logChanged(
                    $calendarEvent,
                    $beforeServicesSnapshot,
                    $afterServicesSnapshot,
                    auth()->user(),
                );
            }
        }

        $freshEvent = $calendarEvent->fresh(['client', 'service', 'eventServices']);
        if ($freshEvent && $freshEvent->event_type === CalendarEvent::TYPE_MARCACAO) {
            if ($userIdChanged && $newAssigneeUserId !== null) {
                $this->notifyMarcacaoRecipient($newAssigneeUserId, $freshEvent, 'reassigned');
            } elseif ($timeChanged && $freshEvent->user_id) {
                $this->notifyMarcacaoRecipient((int) $freshEvent->user_id, $freshEvent, 'rescheduled');
            }
            if ($statusChangedInUpdate && $freshEvent->user_id) {
                $this->notifyMarcacaoRecipient((int) $freshEvent->user_id, $freshEvent, 'status_changed', $prevStatus);
            }
        }

        $notifyClientWanted = $request->boolean('notify_client');
        if (
            $notifyClientWanted
            && $timeChanged
            && $freshEvent
            && $freshEvent->event_type === CalendarEvent::TYPE_MARCACAO
            && $freshEvent->shouldSendBookingNotifications()
            && $freshEvent->client_id
        ) {
            $clientEmail = $freshEvent->client?->email;
            if (
                $this->clientAllowsEmailBookingUpdates($freshEvent->client)
                && $clientEmail
                && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)
            ) {
                try {
                    Notification::locale(BookingLocale::emailLocale())
                        ->route('mail', $this->resolveClientNotificationRecipientEmail($clientEmail))
                        ->notify(new ClientAppointmentRescheduledNotification(
                        (int) $freshEvent->id,
                        $notifyClientPrevStart?->toIso8601String(),
                        $notifyClientPrevEnd?->toIso8601String()
                    ));
                } catch (\Throwable $e) {
                    \Log::warning('Falha ao enviar email de remarcação ao cliente.', [
                        'calendar_event_id' => $freshEvent->id,
                        'client_email' => $clientEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento atualizado com sucesso.',
            'event' => $this->formatEventForCalendar($calendarEvent->fresh(['eventServices'])),
        ]);
    }

    /**
     * Delete an event. Only manual/outro events can be deleted from calendar.
     * Only the responsible user can delete, except for tempo_pessoal: admins can delete any.
     */
    public function destroy(CalendarEvent $calendarEvent)
    {
        $this->assertCanAccessCalendarEvent($calendarEvent);

        $isOwner = $calendarEvent->user_id === null || $calendarEvent->user_id === auth()->id();
        $adminCanDeleteTempoPessoal = $calendarEvent->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL && auth()->user()->canManageAgents();
        if (! $isOwner && ! $adminCanDeleteTempoPessoal) {
            abort(403, 'Apenas o consultor responsável pode eliminar este evento.');
        }
        if (! $calendarEvent->isDeletableFromCalendar()) {
            return response()->json([
                'success' => false,
                'message' => 'Este evento não pode ser eliminado pela agenda. Elimine-o na origem (visita ou lead).',
            ], 422);
        }

        $calendarEvent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminado com sucesso.',
        ]);
    }

    /**
     * Update the status of an event.
     */
    public function updateStatus(Request $request, CalendarEvent $calendarEvent)
    {
        $this->assertCanAccessCalendarEvent($calendarEvent);

        $user = auth()->user();
        if ($denied = $this->denyPrestadorMarcacaoStatusChange($user, $calendarEvent, (string) $request->input('status', ''))) {
            return $denied;
        }

        if ($this->isMarcacaoFullySettled($calendarEvent)) {
            return response()->json([
                'success' => false,
                'message' => 'Marcação faturada; reverta a venda para editar.',
            ], 422);
        }

        if (($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO && $calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Marcações com estado «Faltou» ou «Cancelado» não podem ser alteradas.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:agendado,notificado,confirmado,chegou,iniciado,terminado,faltou,cancelado,completo'],
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'cancellation_type' => ['nullable', 'string', 'in:faltou,cancelado'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $newStatus = $validated['status'];
        $marcacao = ($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO;
        $previousStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;

        if ($marcacao && $newStatus === CalendarEvent::STATUS_CANCELADO) {
            return $this->updateStatusViaCancellationService($request, $calendarEvent, $validated, $previousStatus);
        }

        // Verificar se a transição é válida
        $currentStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        if (! $calendarEvent->canTransitionTo($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível alterar o estado de "'.CalendarEvent::statuses()[$currentStatus].'" para "'.CalendarEvent::statuses()[$newStatus].'".',
            ], 422);
        }

        $update = ['status' => $newStatus];
        if (in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true)) {
            $update['cancellation_reason'] = isset($validated['cancellation_reason']) ? trim($validated['cancellation_reason']) ?: null : null;
            $update['cancellation_type'] = $validated['cancellation_type'] ?? $newStatus;
            $update['refund_reserva'] = false;
            $update['avisou_dentro_prazo'] = $newStatus === CalendarEvent::STATUS_FALTOU ? false : null;
        } else {
            $update['cancellation_reason'] = null;
            $update['cancellation_type'] = null;
            $update['refund_reserva'] = null;
            $update['avisou_dentro_prazo'] = null;
        }
        $statusUpdateApplied = false;

        if ($this->calendarEventStatusPayloadDiffers($calendarEvent, $update)) {
            $calendarEvent->update($update);
            $statusUpdateApplied = true;
        }

        if ($statusUpdateApplied && $marcacao) {
            $this->afterMarcacaoStatusChange($calendarEvent, $previousStatus, $newStatus, (bool) ($validated['notify_client'] ?? false));
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado atualizado com sucesso.',
            'event' => $this->formatEventForCalendar($calendarEvent->fresh()),
            'status' => $newStatus,
            'status_label' => CalendarEvent::statuses()[$newStatus],
            'status_icon' => $calendarEvent->fresh()->status_icon,
        ]);
    }

    /**
     * Cancelamento de marcação com política automática e crédito na carteira.
     */
    private function updateStatusViaCancellationService(
        Request $request,
        CalendarEvent $calendarEvent,
        array $validated,
        string $previousStatus,
    ): JsonResponse {
        if (! $calendarEvent->canTransitionTo(CalendarEvent::STATUS_CANCELADO)) {
            $currentStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;

            return response()->json([
                'success' => false,
                'message' => 'Não é possível alterar o estado de "'.CalendarEvent::statuses()[$currentStatus].'" para "'.CalendarEvent::statuses()[CalendarEvent::STATUS_CANCELADO].'".',
            ], 422);
        }

        $reason = isset($validated['cancellation_reason']) ? trim((string) $validated['cancellation_reason']) : '';
        $notifyClient = (bool) ($validated['notify_client'] ?? false);

        try {
            $result = $this->cancellationService->cancel($calendarEvent, [
                'cancellation_reason' => $reason !== '' ? $reason : null,
                'cancellation_type' => $validated['cancellation_type'] ?? CalendarEvent::STATUS_CANCELADO,
                'notify_client' => $notifyClient,
                'created_by_type' => ClientWalletTransaction::CREATED_BY_STAFF,
                'created_by_user_id' => $request->user()?->id,
            ]);
        } catch (AppointmentCancellationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $event = $result->event;
        $this->afterMarcacaoStatusChange($event, $previousStatus, CalendarEvent::STATUS_CANCELADO, false);

        $message = 'Marcação cancelada.';
        if ($result->walletCredited && $result->walletCreditAmountCents > 0) {
            $amount = number_format($result->walletCreditAmountCents / 100, 2, ',', ' ');
            $message .= ' Crédito de '.$amount.' € na carteira do cliente.';
        } elseif (! $result->policy->isWithinNoticePeriod && $result->policy->hasPaidDeposit) {
            $message .= ' Fora do prazo — sinal não convertido em créditos.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'event' => $this->formatEventForCalendar($event),
            'status' => CalendarEvent::STATUS_CANCELADO,
            'status_label' => CalendarEvent::statuses()[CalendarEvent::STATUS_CANCELADO],
            'status_icon' => $event->status_icon,
            'cancellation_preview' => $this->policyService->previewPayload($event),
        ]);
    }

    private function afterMarcacaoStatusChange(
        CalendarEvent $calendarEvent,
        string $previousStatus,
        string $newStatus,
        bool $notifyClient,
    ): void {
        $ev = $calendarEvent->fresh(['client', 'service', 'eventServices']);
        if ($ev && $ev->event_type === CalendarEvent::TYPE_MARCACAO && $ev->user_id) {
            $this->notifyMarcacaoRecipient((int) $ev->user_id, $ev, 'status_changed', $previousStatus);
        }

        if (
            $notifyClient
            && $calendarEvent->shouldSendBookingNotifications()
            && in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO], true)
        ) {
            $clientEv = $calendarEvent->fresh(['client']);
            $email = $clientEv?->client?->email;
            if (
                $this->clientAllowsEmailBookingUpdates($clientEv?->client)
                && $email
                && filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                try {
                    Notification::locale(BookingLocale::emailLocale())
                        ->route('mail', $this->resolveClientNotificationRecipientEmail($email))
                        ->notify(new ClientAppointmentCancelledNotification($calendarEvent->id));
                } catch (\Throwable $e) {
                    \Log::warning('Falha ao enviar email de cancelamento ao cliente.', [
                        'calendar_event_id' => $calendarEvent->id,
                        'client_email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function calendarEventStatusPayloadDiffers(CalendarEvent $event, array $update): bool
    {
        foreach ($update as $key => $val) {
            $cur = $event->getAttribute($key);
            if (in_array($key, ['refund_reserva', 'avisou_dentro_prazo'], true)) {
                if ((bool) $cur !== (bool) $val) {
                    return true;
                }

                continue;
            }
            if ($key === 'cancellation_reason') {
                if (trim((string) ($cur ?? '')) !== trim((string) ($val ?? ''))) {
                    return true;
                }

                continue;
            }
            if ((string) ($cur ?? '') !== (string) ($val ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $validatedSubset
     * @return array<string, mixed>
     */
    private function filterCalendarEventScalarChanges(CalendarEvent $event, array $validatedSubset): array
    {
        $out = [];
        foreach ($validatedSubset as $key => $val) {
            $cur = $event->getAttribute($key);
            switch ($key) {
                case 'client_id':
                case 'service_id':
                case 'personal_time_type_id':
                    if ((int) ($cur ?? 0) !== (int) ($val ?? 0)) {
                        $out[$key] = $val;
                    }
                    break;
                case 'description':
                    if (trim((string) ($cur ?? '')) !== trim((string) ($val ?? ''))) {
                        $out[$key] = $val;
                    }
                    break;
                default:
                    if ($cur != $val) {
                        $out[$key] = $val;
                    }
            }
        }

        return $out;
    }

    /**
     * @return list<array{service_id: int, service_option_id: int|null, duration: int, price: float, original_price: float, extras: list<array{extra_id: int, duration: int, price: float}>}>
     */
    private function marcacaoServicesSnapshotFromModel(CalendarEvent $event): array
    {
        $items = $event->eventServiceItems()->orderBy('sort_order')->with(['extras' => fn ($q) => $q->orderBy('sort_order')])->get();
        $snapshot = [];
        foreach ($items as $ces) {
            $extras = [];
            foreach ($ces->extras as $e) {
                $extras[] = [
                    'extra_id' => (int) $e->extra_id,
                    'duration' => (int) ($e->duration ?? 0),
                    'price' => round((float) ($e->price ?? 0), 4),
                ];
            }
            $snapshot[] = [
                'service_id' => (int) $ces->service_id,
                'service_option_id' => $ces->service_option_id ? (int) $ces->service_option_id : null,
                'duration' => (int) ($ces->duration ?? 0),
                'price' => round((float) ($ces->price ?? 0), 4),
                'original_price' => round((float) ($ces->original_price ?? $ces->price ?? 0), 4),
                'extras' => $extras,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<int, array<string, mixed>>  $servicesPayload
     * @return list<array{service_id: int, service_option_id: int|null, duration: int, price: float, original_price: float, extras: list<array{extra_id: int, duration: int, price: float}>}>
     */
    private function marcacaoServicesSnapshotFromPayload(array $servicesPayload): array
    {
        $snapshot = [];
        foreach ($servicesPayload as $item) {
            $extrasList = [];
            foreach ($item['extras'] ?? [] as $ex) {
                $extrasList[] = [
                    'extra_id' => (int) ($ex['extra_id'] ?? 0),
                    'duration' => (int) ($ex['duration'] ?? 0),
                    'price' => round((float) ($ex['price'] ?? 0), 4),
                ];
            }
            $snapshot[] = [
                'service_id' => (int) ($item['service_id'] ?? 0),
                'service_option_id' => isset($item['service_option_id']) && $item['service_option_id'] !== '' && $item['service_option_id'] !== null
                    ? (int) $item['service_option_id']
                    : null,
                'duration' => (int) ($item['duration'] ?? 0),
                'price' => round((float) ($item['price'] ?? 0), 4),
                'original_price' => round((float) ($item['original_price'] ?? $item['price'] ?? 0), 4),
                'extras' => $extrasList,
            ];
        }

        return $snapshot;
    }

    private function formatEventForCalendar(CalendarEvent $event, bool $withResourceId = false): array
    {
        $classMap = CalendarEvent::typeClassMap();
        $className = $classMap[$event->event_type] ?? 'bg-secondary';

        $event->loadMissing(['client', 'eventServices.category', 'eventServices.fees', 'user.agent', 'personalTimeType', 'sales', 'onlineBooking']);
        $event->eventServices->each(fn ($s) => $s->pivot->load(['extras', 'extras.extra']));
        $isTempoPessoal = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL;
        $agentColor = $isTempoPessoal ? null : ($event->user?->agent?->color);
        $eventServicesData = $event->eventServices->isNotEmpty()
            ? $event->eventServices->map(function ($s) {
                $dur = ($s->pivot->duration ?? $s->duration);
                $cat = $s->category;

                return [
                    'id' => $s->id,
                    'service_id' => (int) $s->id,
                    'name' => self::marcacaoServiceLineLabel($s->pivot),
                    'service_option_id' => $s->pivot->service_option_id,
                    'option_name' => $s->pivot->option_name,
                    'duration' => $dur,
                    'price' => (float) ($s->pivot->price ?? $s->price),
                    'original_price' => $s->pivot->original_price !== null ? (float) $s->pivot->original_price : (float) ($s->pivot->price ?? $s->price),
                    'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.').' €' : $s->formatted_price,
                    'formatted_duration' => $this->formatDurationMinutes((int) $dur),
                    'color' => $cat?->color ?? '#6c757d',
                    'category_name' => $cat?->name ?? '',
                    'fees' => $s->fees->map(fn ($f) => [
                        'fee_id' => (int) $f->id,
                        'name' => $f->name,
                        'price' => (float) $f->price,
                        'formatted_price' => $f->formatted_price,
                    ])->values()->all(),
                    'extras' => $s->pivot->extras->map(function ($pe) {
                        $extraDuration = $pe->duration ?? $pe->extra?->duration ?? 0;
                        $extraPrice = (float) ($pe->price ?? $pe->extra?->price ?? 0);

                        return [
                            'extra_id' => $pe->extra_id,
                            'name' => $pe->extra?->name ?? '',
                            'duration' => $extraDuration,
                            'price' => $extraPrice,
                            'formatted_duration' => $this->formatDurationMinutes((int) $extraDuration),
                            'formatted_price' => number_format($extraPrice, 2, ',', '.').' €',
                        ];
                    })->values()->all(),
                ];
            })->values()->all()
            : [];
        $serviceName = $event->eventServices->isNotEmpty()
            ? $event->eventServices->map(fn ($s) => self::marcacaoServiceLineLabel($s->pivot))->join(', ')
            : ($event->service?->name ?? null);

        $statusLabel = $isTempoPessoal ? 'Tempo pessoal' : (CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado');
        $statusIcon = $isTempoPessoal ? null : $event->status_icon;
        $bgColor = $agentColor ?: ($isTempoPessoal ? '#dee2e6' : null);
        if ($isTempoPessoal) {
            $className = 'agenda-event-tempo-pessoal';
        }
        $isCompleted = ($event->status ?? CalendarEvent::STATUS_AGENDADO) === CalendarEvent::STATUS_COMPLETO;

        $activeSales = $this->activeSalesForEvent((int) $event->id);
        $hasInvoice = $activeSales->isNotEmpty();
        $includeCatalogFees = ApplicableFees::includeCatalogFeesForCalendarEvent($event);
        $servicesSubtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($eventServicesData);
        $subtotal = $includeCatalogFees
            ? round($servicesSubtotal + ApplicableFees::sumPrices(
                ApplicableFees::forEventServicesPayload($eventServicesData, (int) $event->store_id)
            ), 2)
            : $servicesSubtotal;
        $catalogFees = $includeCatalogFees
            ? ApplicableFees::forEventServicesPayload($eventServicesData, (int) $event->store_id)
            : [];
        $bookingPaidAmount = ApplicableFees::marcacaoBookingPaidAmountForEvent((int) $event->id);
        $amountDue = self::marcacaoAmountDueFromTotals($subtotal, $activeSales, (int) $event->id);
        $hasActiveCaixaSale = $activeSales->contains(fn (Sale $s) => $s->scope === Sale::SCOPE_CAIXA_LIQUIDACAO);
        $invoiceSettled = $this->isMarcacaoFullySettled($event);
        $pendingFinalInvoice = $isCompleted && $servicesSubtotal > 0.00001 && ! $hasActiveCaixaSale && $amountDue <= 0.00001;
        $paymentMethodLabels = Sale::paymentMethods();
        $paymentLines = $activeSales->map(function (Sale $s) use ($paymentMethodLabels) {
            $scopeLabel = match ($s->scope) {
                Sale::SCOPE_BOOKING_RESERVA => 'Pré-pagamento',
                Sale::SCOPE_CAIXA_LIQUIDACAO => 'Pagamento final',
                default => 'Pagamento',
            };

            return [
                'label' => $scopeLabel,
                'amount' => round((float) $s->effectiveAmountPaid(), 2),
                'method' => $paymentMethodLabels[$s->payment_method] ?? 'Outro',
            ];
        })->values()->all();
        $arr = [
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $event->start_at->toIso8601String(),
            'end' => $event->end_at->toIso8601String(),
            'className' => $className,
            'backgroundColor' => $bgColor,
            'editable' => ! $invoiceSettled && ! $isCompleted,
            'startEditable' => ! $invoiceSettled && ! $isCompleted,
            'durationEditable' => ! $invoiceSettled && ! $isCompleted,
            'resourceEditable' => ! $invoiceSettled && ! $isCompleted,
            'extendedProps' => [
                'client_id' => $event->client_id,
                'client_name' => $event->client?->name,
                'client_phone' => $event->client?->phone,
                'client_formatted_phone' => $event->client?->formatted_phone,
                'client_has_email' => (bool) ($event->client_id && $event->client?->email && filter_var($event->client->email, FILTER_VALIDATE_EMAIL)),
                'client_birth_date' => $event->client?->birth_date?->format('Y-m-d'),
                ...$this->clientBirthdayMetaForEvent($event->client, $event->start_at, (int) $event->store_id),
                'description' => $event->description,
                'event_type' => $event->event_type,
                'event_type_label' => CalendarEvent::eventTypes()[$event->event_type] ?? $event->event_type,
                'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                'status_label' => $statusLabel,
                'status_icon' => $statusIcon,
                'user_id' => $event->user_id,
                'user_name' => $event->user?->name,
                'service_id' => $event->service_id,
                'service_name' => $serviceName,
                'event_services' => $eventServicesData,
                'is_source_editable' => $event->isSourceEditable(),
                'is_deletable' => $event->isDeletableFromCalendar(),
                'is_time_editable' => $event->isTimeEditable(),
                'personal_time_type_id' => $event->personal_time_type_id,
                'personal_time_type' => $event->personalTimeType ? [
                    'id' => $event->personalTimeType->id,
                    'name' => $event->personalTimeType->name,
                    'icon' => $event->personalTimeType->icon,
                    'duration' => $event->personalTimeType->duration,
                    'formatted_duration' => $event->personalTimeType->formatted_duration,
                ] : null,
                'cancellation_type' => $event->cancellation_type,
                'refund_reserva' => $event->refund_reserva,
                'avisou_dentro_prazo' => $event->avisou_dentro_prazo,
                'has_invoice' => $hasInvoice,
                'invoice_settled' => $invoiceSettled,
                'total_amount' => round($subtotal, 2),
                'apply_catalog_fees' => $includeCatalogFees,
                'catalog_fees' => $catalogFees,
                'charged_fees' => $includeCatalogFees
                    ? []
                    : ApplicableFees::chargedFeesForCalendarEvent((int) $event->id),
                'booking_paid_amount' => $bookingPaidAmount,
                'amount_due' => $amountDue,
                'pending_final_invoice' => $pendingFinalInvoice,
                'payment_lines' => $paymentLines,
            ],
        ];
        if ($withResourceId) {
            $arr['resourceId'] = $event->user_id ? (string) $event->user_id : 'unassigned';
        }

        return $arr;
    }

    /**
     * @return array{client_birthday_today: bool, client_birthday_in_month: bool, client_birthday_age: ?int, client_birthday_tense: ?string}
     */
    private function clientBirthdayMetaForEvent(?Client $client, ?Carbon $startAt, int $storeId): array
    {
        if (! $client || ! $startAt) {
            return [
                'client_birthday_today' => false,
                'client_birthday_in_month' => false,
                'client_birthday_age' => null,
                'client_birthday_tense' => null,
            ];
        }

        $highlight = $client->birthdayHighlight($startAt, $storeId, sameMonthOnly: true);

        return [
            'client_birthday_today' => ($highlight['scope'] ?? null) === 'day',
            'client_birthday_in_month' => $highlight !== null,
            'client_birthday_age' => $highlight['age'] ?? null,
            'client_birthday_tense' => $highlight['tense'] ?? null,
        ];
    }

    private static function marcacaoServiceLineLabel(CalendarEventService $item): string
    {
        $item->loadMissing('service');
        $opt = trim((string) ($item->option_name ?? ''));
        if ($opt !== '') {
            return $opt;
        }

        return (string) ($item->service?->name ?? '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $servicesPayload
     */
    private function assertMarcacaoServicesOptionsValid(array $servicesPayload): void
    {
        $serviceIds = array_values(array_unique(array_map(fn ($row) => (int) ($row['service_id'] ?? 0), $servicesPayload)));
        $serviceIds = array_values(array_filter($serviceIds, fn (int $id) => $id > 0));
        $services = Service::query()
            ->forStore(current_store_id())
            ->whereIn('id', $serviceIds)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->keyBy('id');

        foreach ($servicesPayload as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $sid = (int) ($item['service_id'] ?? 0);
            $service = $services->get($sid);
            if (! $service) {
                throw ValidationException::withMessages([
                    "services.{$idx}.service_id" => ['Serviço inválido.'],
                ]);
            }
            $hasVariants = $service->options->isNotEmpty();
            $optRaw = $item['service_option_id'] ?? null;
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
        }
    }

    private function assertMarcacaoClientRequired(Request $request): void
    {
        if (! $request->input('client_id')) {
            throw ValidationException::withMessages([
                'client_id' => ['A marcação tem de ter um cliente associado.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Service>  $servicesById
     * @return array<string, mixed>
     */
    private function pivotAttributesForMarcacaoServiceLine(array $item, int $sortOrder, Collection $servicesById): array
    {
        $sid = (int) ($item['service_id'] ?? 0);
        $duration = isset($item['duration']) ? (int) $item['duration'] : null;
        $price = isset($item['price']) ? (float) $item['price'] : null;
        $original = isset($item['original_price']) ? (float) $item['original_price'] : $price;

        $row = [
            'duration' => $duration,
            'price' => $price,
            'original_price' => $original,
            'sort_order' => $sortOrder,
            'service_option_id' => null,
            'option_name' => null,
            'option_duration' => null,
            'option_price' => null,
            'option_online_price' => null,
        ];

        $optRaw = $item['service_option_id'] ?? null;
        $optId = ($optRaw !== null && $optRaw !== '') ? (int) $optRaw : null;
        if ($optId > 0) {
            $service = $servicesById->get($sid);
            $opt = $service?->options?->firstWhere('id', $optId);
            if ($opt) {
                $row['service_option_id'] = $opt->id;
                $row['option_name'] = $opt->name;
                $row['option_duration'] = $opt->duration;
                $row['option_price'] = $opt->price;
                $row['option_online_price'] = $opt->online_price;
            }
        }

        return $row;
    }

    private function formatDurationMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return $hours.'h '.$mins.'min';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $mins.'min';
    }

    /**
     * Evita enviar emails de testes para clientes reais.
     * Em produção, mantém o email original; noutros ambientes, redireciona para suporte.
     */
    private function resolveClientNotificationRecipientEmail(?string $originalEmail): string
    {
        $originalEmail = $originalEmail ?? '';
        $supportEmail = env('MAIL_CLIENT_TEST_REDIRECT_TO', 'suporte@softrace.pt');

        if (app()->environment('production')) {
            return $originalEmail;
        }

        return $supportEmail;
    }

    /**
     * Notifica o utilizador responsável pela marcação (sem auto-notificação).
     */
    private function notifyMarcacaoRecipient(int $userId, CalendarEvent $event, string $type, ?string $previousStatus = null): void
    {
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO || ! $event->shouldSendBookingNotifications()) {
            return;
        }
        $user = User::find($userId);
        if ($user && auth()->id() !== $user->id) {
            try {
                $user->notify(new AppointmentNotification($event->id, $type, $previousStatus));
            } catch (\Throwable $e) {
                // Falha de email/notificação não deve bloquear criação/edição da marcação.
                \Log::warning('Falha ao enviar notificação de marcação.', [
                    'calendar_event_id' => $event->id,
                    'recipient_user_id' => $user->id,
                    'type' => $type,
                    'previous_status' => $previousStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (in_array($type, ['assigned', 'reassigned', 'rescheduled'], true)) {
            $this->receptionBookingNotifier->notify($event, $type, $previousStatus);
        }
    }

    private function notifyClientAppointmentCreated(CalendarEvent $event): void
    {
        if (! $event->shouldSendBookingNotifications()) {
            return;
        }

        $client = $event->client;
        if (! $this->clientAllowsEmailBookingUpdates($client)) {
            return;
        }

        $email = trim((string) ($client?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Notification::locale(BookingLocale::emailLocale())
                ->route('mail', $this->resolveClientNotificationRecipientEmail($email))
                ->notify(new ClientAppointmentCreatedNotification((int) $event->id));
        } catch (\Throwable $e) {
            \Log::warning('Falha ao enviar email de marcacao criada ao cliente.', [
                'calendar_event_id' => $event->id,
                'client_email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clientAllowsEmailBookingUpdates(?Client $client): bool
    {
        if (! $client instanceof Client) {
            return false;
        }

        return (bool) ($client->notify_email_booking_updates ?? true);
    }

    /**
     * @return list<array{id: int, label: string, numero_fatura: string, pdf_url: string, vendus_url: string|null, scope: string|null, amount: float, invoice_status: string}>
     */
    private function salesInvoicesForCalendarEvent(int $calendarEventId): array
    {
        $pivotSaleIds = \Illuminate\Support\Facades\DB::table('sale_calendar_events')
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('sale_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Sale::query()
            ->where(function ($q) use ($calendarEventId, $pivotSaleIds): void {
                $q->where('calendar_event_id', $calendarEventId);
                if ($pivotSaleIds !== []) {
                    $q->orWhereIn('id', $pivotSaleIds);
                }
            })
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->orderBy('id')
            ->get()
            ->map(function (Sale $s) {
                return [
                    'id' => $s->id,
                    'label' => $s->invoiceListLabel(),
                    'numero_fatura' => $s->numero_fatura,
                    'pdf_url' => route('sales.pdf', $s),
                    'vendus_url' => $s->vendus_document_id ? route('sales.vendus.pdf', $s) : null,
                    'scope' => $s->scope,
                    'amount' => round($s->effectiveAmountPaid(), 2),
                    'invoice_status' => $s->invoice_status ?? Sale::INVOICE_STATUS_FATURADO,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Sale>
     */
    private function activeSalesForEvent(int $calendarEventId): \Illuminate\Support\Collection
    {
        $pivotSaleIds = \Illuminate\Support\Facades\DB::table('sale_calendar_events')
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('sale_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Sale::query()
            ->where(function ($q) use ($calendarEventId, $pivotSaleIds): void {
                $q->where('calendar_event_id', $calendarEventId);
                if ($pivotSaleIds !== []) {
                    $q->orWhereIn('id', $pivotSaleIds);
                }
            })
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Sale>  $activeSales
     */
    private static function marcacaoAmountDueFromTotals(
        float $subtotal,
        \Illuminate\Support\Collection $activeSales,
        int $calendarEventId,
    ): float {
        $discount = round((float) $activeSales->sum(fn (Sale $s): float => (float) ($s->desconto ?? 0)), 2);
        $netSubtotal = max(0.0, round($subtotal - $discount, 2));
        $moneyTowardSubtotal = ApplicableFees::marcacaoMoneyTowardSubtotal($calendarEventId);

        return max(0.0, round($netSubtotal - $moneyTowardSubtotal, 2));
    }

    private function isMarcacaoFullySettled(CalendarEvent $calendarEvent): bool
    {
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return false;
        }

        $serviceItems = $calendarEvent->eventServiceItems()->with('extras.extra')->get();
        $subtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceItems);

        return ApplicableFees::amountDueCashFromEventId((int) $calendarEvent->id, $subtotal) <= 0.00001;
    }

    /**
     * Campos de reserva/adiantamento para o offcanvas e modal de pagamento (receção).
     *
     * @return array{
     *     deposit_percent: int,
     *     deposit_amount_expected: float,
     *     can_collect_deposit: bool,
     *     has_booking_reserva_sale: bool,
     *     has_saved_cards: bool
     * }
     */
    private function marcacaoPrepaymentWalletOnly(CalendarEvent $calendarEvent, float $bookingPaidAmount): bool
    {
        if ($bookingPaidAmount <= 0.00001) {
            return false;
        }

        $hasReservaSale = Sale::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->exists();
        if ($hasReservaSale) {
            return false;
        }

        $walletAppliedCents = (int) (Booking::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->orderByDesc('id')
            ->value('wallet_applied_cents') ?? 0);

        return $walletAppliedCents > 0;
    }

    private function marcacaoDepositPayloadForEvent(CalendarEvent $calendarEvent, float $subtotal): array
    {
        $calendarEvent->loadMissing('client');

        try {
            $preview = $this->agendaDepositService->preview($calendarEvent);
        } catch (\App\Exceptions\AgendaDepositException) {
            $depositPercent = $this->agendaDepositService->depositPercent();
            $depositAmount = $depositPercent > 0 && $subtotal > 0.00001
                ? round($subtotal * ($depositPercent / 100), 2)
                : 0.0;

            $preview = [
                'deposit_percent' => $depositPercent,
                'deposit_amount' => $depositAmount,
                'can_collect' => false,
                'has_reserva_sale' => $this->agendaDepositService->hasActiveReservaSale((int) $calendarEvent->id),
            ];
        }

        $client = $calendarEvent->client;
        $hasSavedCards = $client instanceof Client
            && BookingSavedCard::query()
                ->where('client_id', $client->id)
                ->whereNull('detached_at')
                ->exists();

        return [
            'deposit_percent' => (int) ($preview['deposit_percent'] ?? $this->agendaDepositService->depositPercent()),
            'deposit_amount_expected' => round((float) ($preview['deposit_amount'] ?? 0), 2),
            'can_collect_deposit' => (bool) ($preview['can_collect'] ?? false),
            'has_booking_reserva_sale' => (bool) ($preview['has_reserva_sale'] ?? false),
            'has_saved_cards' => $hasSavedCards,
        ];
    }

    private function cashRegisterIsOpen(): bool
    {
        return $this->cashRegisterService->getOpenSession((int) current_store_id()) !== null;
    }

    private function assertCanAccessCalendarEvent(CalendarEvent $calendarEvent): void
    {
        if ((int) $calendarEvent->store_id !== (int) current_store_id()) {
            abort(404);
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isPrestador() && (int) $calendarEvent->user_id !== (int) $user->id) {
            abort(403, 'Sem permissão para aceder a esta marcação.');
        }
    }

    private function denyPrestadorRestrictedMarcacaoUpdate(Request $request, CalendarEvent $calendarEvent): ?JsonResponse
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isPrestador()) {
            return null;
        }

        if ($request->has('user_id')) {
            $newUserId = $request->input('user_id') ? (int) $request->input('user_id') : null;
            $currentUserId = $calendarEvent->user_id ? (int) $calendarEvent->user_id : null;
            if ($newUserId !== $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode reatribuir a marcação a outro profissional.',
                ], 403);
            }
        }

        if ($request->has('client_id') && ($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO) {
            $newClientId = $request->input('client_id') ? (int) $request->input('client_id') : null;
            $currentClientId = $calendarEvent->client_id ? (int) $calendarEvent->client_id : null;
            if ($newClientId !== $currentClientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode alterar o cliente desta marcação.',
                ], 403);
            }
        }

        if ($request->has('status')) {
            if ($denied = $this->denyPrestadorMarcacaoStatusChange($user, $calendarEvent, (string) $request->input('status'))) {
                return $denied;
            }
        }

        return null;
    }

    private function denyPrestadorMarcacaoStatusChange(?User $user, CalendarEvent $calendarEvent, string $requestedStatus): ?JsonResponse
    {
        if (! $user instanceof User || ! $user->isPrestador()) {
            return null;
        }

        $currentStatus = (string) ($calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO);

        if ($requestedStatus === $currentStatus) {
            if (! in_array($currentStatus, $user->prestadorEditableMarcacaoStatuses(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode editar marcações neste estado.',
                ], 403);
            }

            return null;
        }

        if (! in_array($currentStatus, $user->prestadorEditableMarcacaoStatuses(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Não pode editar marcações neste estado.',
            ], 403);
        }

        if (! in_array($requestedStatus, $user->prestadorAllowedMarcacaoStatuses(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Sem permissão para alterar para este estado.',
            ], 403);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeEventPayloadForUser(array $payload): array
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isPrestador()) {
            return $payload;
        }

        unset(
            $payload['client_email'],
            $payload['client_phone'],
            $payload['client_nif'],
            $payload['client_formatted_phone'],
            $payload['client_has_email'],
        );

        $payload['existing_sale'] = null;
        $payload['sales_invoices'] = [];
        $payload['active_caixa_sale'] = null;
        $payload['cancelled_final_invoice'] = null;
        $payload['event_detail_nif_only_editable'] = false;
        $payload['booking_paid_amount'] = 0.0;
        $payload['invoice_settled'] = false;
        $payload['pending_final_invoice'] = false;
        $payload['cash_register_open'] = false;
        $payload['can_collect_deposit'] = false;
        $payload['has_booking_reserva_sale'] = false;
        $payload['has_saved_cards'] = false;
        $payload['deposit_percent'] = 0;
        $payload['deposit_amount_expected'] = 0.0;
        $payload['prepayment_wallet_only'] = false;
        $payload['same_day_payable'] = ['count' => 0, 'total_due' => 0.0, 'rows' => []];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeClientPayloadForUser(array $payload): array
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canViewClientContactDetails()) {
            unset($payload['email'], $payload['phone'], $payload['formatted_phone'], $payload['nif']);
        }

        return $payload;
    }
}
