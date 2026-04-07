<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\PersonalTimeType;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\ClientAppointmentCancelledNotification;
use App\Notifications\ClientAppointmentRescheduledNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    /**
     * Display the calendar (agenda) view.
     */
    public function index()
    {
        $eventTypes = CalendarEvent::eventTypes();
        $personalTimeTypes = PersonalTimeType::where('is_active', true)->orderBy('sort_order')->get();
        // Mostrar apenas users com agent ativo; excluir Administradores da agenda (select de membros)
        $users = User::whereHas('agent', function ($query) {
            $query->where('status', Agent::STATUS_ACTIVE);
        })
            ->with('agent')
            ->whereNotIn('role', [User::ROLE_ADMIN])
            ->orderBy('name')
            ->get();

        $today = now();
        $nationalHolidaysPt = $this->ptNationalHolidayDatesBetweenYears((int) $today->format('Y') - 1, (int) $today->format('Y') + 2);

        return view('agenda.index', compact('eventTypes', 'users', 'personalTimeTypes', 'nationalHolidaysPt'));
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
        // Mostrar apenas agents ativos como recursos; excluir Administradores da vista Por Consultor
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
            ->with('user')
            ->orderBy('name')
            ->get();

        $result = $agents->map(function ($agent) {
            $avatarNum = ($agent->id % 9) + 1;
            $avatarUrl = $agent->avatar
                ? asset('storage/'.$agent->avatar)
                : asset("template/img/avatars/avatar-{$avatarNum}.webp");

            return [
                'id' => (string) $agent->user_id, // Usar user_id como ID do recurso
                'title' => $agent->name,
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
            ->with(['user.agent', 'service', 'client', 'eventServices', 'eventable', 'personalTimeType', 'sale'])
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU]);
            });

        // Verificar se o utilizador pode ver todos os eventos (admin ou diretor)
        $canViewAll = auth()->user()->canManageAgents();

        $activeAgentUserIds = Agent::where('status', Agent::STATUS_ACTIVE)
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

        // Carregar extras associados aos serviços para poderem ser mostrados no quickview
        $events->each(function (CalendarEvent $event) {
            $event->eventServices->each(fn ($s) => $s->pivot->load(['extras', 'extras.extra']));
        });

        // Na vista de recursos, apenas users com agents ativos são válidos (excluir Administradores)
        $validUserIds = $forResources
            ? collect($activeAgentUserIds)->map(fn ($id) => (string) $id)->flip()->all()
            : [];

        $result = $events->map(function (CalendarEvent $event) use ($forResources, $validUserIds) {
            $classMap = CalendarEvent::typeClassMap();
            $className = $classMap[$event->event_type] ?? 'bg-secondary';
            $agentColor = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL ? null : ($event->user?->agent?->color);

            $isTempoPessoal = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL;
            $statusLabel = $isTempoPessoal ? 'Tempo pessoal' : (CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado');
            $statusIcon = $isTempoPessoal ? null : $event->status_icon;
            $hasInvoice = $event->sale && $event->sale->status !== Sale::STATUS_ANULADO;
            $statusLocked = ! $isTempoPessoal && $event->isMarcacaoStatusLocked();

            $item = [
                'id' => (string) $event->id,
                'title' => $event->title,
                'start' => $event->start_at->toIso8601String(),
                'end' => $event->end_at->toIso8601String(),
                'className' => $className,
                'backgroundColor' => $agentColor ?: ($isTempoPessoal ? '#dee2e6' : null),
                'editable' => ! $hasInvoice && ! $statusLocked,
                'extendedProps' => [
                    'client_name' => $event->client?->name,
                    'client_avatar_url' => $event->client?->avatar ? asset('storage/'.$event->client->avatar) : null,
                    'client_phone' => $event->client?->phone,
                    'client_formatted_phone' => $event->client?->formatted_phone,
                    'description' => $event->description,
                    'event_type' => $event->event_type,
                    'event_type_label' => CalendarEvent::eventTypes()[$event->event_type] ?? $event->event_type,
                    'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                    'status_label' => $statusLabel,
                    'status_icon' => $statusIcon,
                    'user_id' => $event->user_id,
                    'user_name' => $event->user?->name,
                    'service_id' => $event->service_id,
                    'service_name' => $event->eventServices->isNotEmpty()
                        ? $event->eventServices->pluck('name')->join(', ')
                        : ($event->service?->name ?? null),
                    'event_services' => $event->eventServices->map(function ($s) {
                        $dur = ($s->pivot->duration ?? $s->duration);

                        return [
                            'id' => $s->id,
                            'name' => $s->name,
                            'duration' => $dur,
                            'price' => (float) ($s->pivot->price ?? $s->price),
                            'original_price' => $s->pivot->original_price !== null ? (float) $s->pivot->original_price : (float) ($s->pivot->price ?? $s->price),
                            'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.').' €' : $s->formatted_price,
                            'formatted_duration' => $this->formatDurationMinutes((int) $dur),
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
                    })->values()->all(),
                    'is_source_editable' => $event->isSourceEditable(),
                    'is_deletable' => $event->isDeletableFromCalendar(),
                    'is_time_editable' => $event->isTimeEditable(),
                    'eventable_type' => $event->eventable_type,
                    'eventable_id' => $event->eventable_id,
                    'personal_time_type_id' => $event->personal_time_type_id,
                    'personal_time_type' => $event->personalTimeType ? [
                        'id' => $event->personalTimeType->id,
                        'name' => $event->personalTimeType->name,
                        'icon' => $event->personalTimeType->icon,
                        'duration' => $event->personalTimeType->duration,
                        'formatted_duration' => $event->personalTimeType->formatted_duration,
                    ] : null,
                    'has_invoice' => $hasInvoice,
                    'client_id' => $event->client_id,
                    'client_has_email' => (bool) ($event->client_id && $event->client?->email && filter_var($event->client->email, FILTER_VALIDATE_EMAIL)),
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
        $agent = $user->agent;
        if (! $agent) {
            return response()->json(['categories' => []]);
        }

        $services = $agent->services()
            ->with([
                'category',
                'extras' => fn ($q) => $q->orderBy('extras.sort_order'),
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

        $categoryNames = \App\Models\Category::whereIn('id', $byCategory->keys()->filter(fn ($id) => $id !== 0))->pluck('name', 'id');
        $categoryColors = \App\Models\Category::whereIn('id', $byCategory->keys()->filter(fn ($id) => $id !== 0))->pluck('color', 'id');

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
                    'extras' => $s->extras->map(fn ($e) => [
                        'id' => $e->id,
                        'name' => $e->name,
                        'duration' => $e->duration,
                        'price' => (float) $e->price,
                        'formatted_duration' => $e->formatted_duration,
                        'formatted_price' => $e->formatted_price,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        }

        return response()->json(['categories' => $categories]);
    }

    /**
     * Search clients for Nova Marcação modal (JSON).
     */
    public function clients(\Illuminate\Http\Request $request)
    {
        $search = $request->get('q', '');
        $clientId = $request->get('client_id');

        if ($clientId) {
            $client = \App\Models\Client::find($clientId);
            if ($client) {
                $arr = $client->only(['id', 'name', 'email', 'phone']);
                $arr['formatted_phone'] = $client->formatted_phone;
                $arr['avatar_url'] = $client->avatar ? asset('storage/'.$client->avatar) : null;

                return response()->json([$arr]);
            }

            return response()->json([]);
        }

        $query = \App\Models\Client::query()->orderBy('name')->limit(50);

        if (strlen($search) >= 1) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->get(['id', 'name', 'email', 'phone', 'avatar']);
        $result = $clients->map(function ($c) {
            $arr = $c->only(['id', 'name', 'email', 'phone']);
            $arr['formatted_phone'] = $c->formatted_phone;
            $arr['avatar_url'] = $c->avatar ? asset('storage/'.$c->avatar) : null;

            return $arr;
        });

        return response()->json($result);
    }

    /**
     * Create a new client from the agenda (quick create).
     * Returns JSON in the same format as clients() search.
     */
    public function storeClient(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')],
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $client = Client::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
        ]);

        $result = [
            'id' => (string) $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'formatted_phone' => $client->formatted_phone,
            'avatar_url' => $client->avatar ? asset('storage/'.$client->avatar) : null,
        ];

        return response()->json($result);
    }

    /**
     * Show a single event (for modal/details).
     * In resource view the user may view events of other consultants (permission check can be added).
     */
    public function show(CalendarEvent $calendarEvent)
    {
        try {
            $calendarEvent->load(['user', 'service', 'client', 'eventServices.category', 'eventable', 'personalTimeType', 'sale']);
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
                'service_name' => $calendarEvent->service?->name,
                'client_id' => $calendarEvent->client_id,
                'client_name' => $calendarEvent->client?->name,
                'client_has_email' => (bool) ($calendarEvent->client_id && $calendarEvent->client?->email && filter_var($calendarEvent->client->email, FILTER_VALIDATE_EMAIL)),
                'client_email' => $calendarEvent->client?->email,
                'client_phone' => $calendarEvent->client?->phone,
                'client_formatted_phone' => $calendarEvent->client?->formatted_phone,
                'client_avatar_url' => $calendarEvent->client?->avatar ? asset('storage/'.$calendarEvent->client->avatar) : null,
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
                        'name' => $s->name,
                        'duration' => $duration,
                        'price' => $price,
                        'original_price' => $s->pivot->original_price !== null ? (float) $s->pivot->original_price : $price,
                        'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.').' €' : $s->formatted_price,
                        'formatted_duration' => $this->formatDurationMinutes((int) $duration),
                        'color' => $color,
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
            ];

            $sale = $calendarEvent->sale;
            if ($sale && $sale->status !== Sale::STATUS_ANULADO) {
                $payload['existing_sale'] = [
                    'id' => $sale->id,
                    'numero_fatura' => $sale->numero_fatura,
                    'pdf_url' => route('sales.pdf', $sale),
                ];
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

            return response()->json($payload);
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

    /**
     * Store a new manual (or outro) event.
     */
    public function store(Request $request)
    {
        $rules = [
            'title' => ['required_without:personal_time_type_id', 'nullable', 'string', 'max:255'],
            'personal_time_type_id' => ['nullable', 'exists:personal_time_types,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'description' => ['nullable', 'string'],
            'event_type' => ['required', 'in:manual,outro,marcacao,tempo_pessoal'],
            'user_id' => ['nullable', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => ['nullable', 'exists:extras,id'],
            'services.*.extras.*.duration' => ['nullable', 'integer', 'min:0'],
            'services.*.extras.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
        $validated = $request->validate($rules);

        if (($validated['event_type'] ?? '') === CalendarEvent::TYPE_TEMPO_PESSOAL) {
            $personalTypeId = $validated['personal_time_type_id'] ?? null;
            if ($personalTypeId) {
                $type = PersonalTimeType::find($personalTypeId);
                $validated['title'] = $type?->name ?? $validated['title'] ?? 'Tempo pessoal';
                $validated['personal_time_type_id'] = (int) $personalTypeId;
            } else {
                $validated['title'] = $validated['title'] ?? 'Tempo pessoal';
            }
        }

        $servicesPayload = $request->input('services', []);
        if (($validated['event_type'] ?? '') === CalendarEvent::TYPE_MARCACAO) {
            if (! empty($servicesPayload)) {
                $validated['service_id'] = (int) $servicesPayload[0]['service_id'];
            } else {
                $request->validate(['service_id' => ['required', 'exists:services,id']]);
                $validated['service_id'] = $request->input('service_id');
            }
        } else {
            $validated['service_id'] = null;
        }

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();
        $validated['client_id'] = $request->input('client_id');
        if ($validated['user_id'] && User::find($validated['user_id'])?->role === User::ROLE_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível atribuir o evento a um Administrador.',
            ], 422);
        }
        $validated['status'] = $validated['status'] ?? CalendarEvent::STATUS_AGENDADO;

        $event = CalendarEvent::create($validated);

        if (! empty($servicesPayload)) {
            foreach ($servicesPayload as $i => $item) {
                $event->eventServices()->attach((int) $item['service_id'], [
                    'duration' => isset($item['duration']) ? (int) $item['duration'] : null,
                    'price' => isset($item['price']) ? (float) $item['price'] : null,
                    'original_price' => isset($item['original_price']) ? (float) $item['original_price'] : (isset($item['price']) ? (float) $item['price'] : null),
                    'sort_order' => $i,
                ]);
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
                activity()
                    ->performedOn($event)
                    ->causedBy(auth()->user())
                    ->event('updated')
                    ->withProperties([
                        'servicos' => $event->eventServices->pluck('name')->implode(', '),
                    ])
                    ->log('Serviços associados à nova marcação');
            }
        }

        if ($event->event_type === CalendarEvent::TYPE_MARCACAO && $event->user_id) {
            $event->load(['client', 'service', 'eventServices']);
            $this->notifyMarcacaoRecipient((int) $event->user_id, $event, 'assigned');
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
        $sale = $calendarEvent->sale;
        if ($sale && $sale->status !== Sale::STATUS_ANULADO) {
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
            'user_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:agendado,confirmado,chegou,iniciado,terminado,faltou,cancelado'],
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'cancellation_type' => ['nullable', 'string', 'in:faltou,cancelado'],
            'refund_reserva' => ['nullable', 'boolean'],
            'avisou_dentro_prazo' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'services.*.extras' => ['nullable', 'array'],
            'services.*.extras.*.extra_id' => ['nullable', 'exists:extras,id'],
            'services.*.extras.*.duration' => ['nullable', 'integer', 'min:0'],
            'services.*.extras.*.price' => ['nullable', 'numeric', 'min:0'],
            'notify_client' => ['sometimes', 'boolean'],
        ];

        if ($calendarEvent->isSourceEditable()) {
            $rules['title'] = ['sometimes', 'string', 'max:255'];
            $rules['personal_time_type_id'] = ['nullable', 'exists:personal_time_types,id'];
            $rules['description'] = ['nullable', 'string'];
            $rules['event_type'] = ['sometimes', 'in:manual,outro,marcacao,tempo_pessoal'];
            $rules['service_id'] = ['nullable', 'exists:services,id'];
        }

        $validated = $request->validate($rules);

        $servicesPayload = $request->input('services', []);

        $prevStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        $timeChanged = false;
        $userIdChanged = false;
        $newAssigneeUserId = null;
        $statusChangedInUpdate = false;

        if (isset($validated['event_type']) && $validated['event_type'] === CalendarEvent::TYPE_MARCACAO && $calendarEvent->isSourceEditable()) {
            if (! empty($servicesPayload)) {
                $validated['service_id'] = (int) $servicesPayload[0]['service_id'];
            } elseif (! array_key_exists('service_id', $validated)) {
                $request->validate(['service_id' => ['required', 'exists:services,id']]);
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
            if (in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO], true)) {
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
                $type = PersonalTimeType::find($toUpdate['personal_time_type_id']);
                if ($type) {
                    $toUpdate['title'] = $type->name;
                }
            }
            $toUpdate = $this->filterCalendarEventScalarChanges($calendarEvent, $toUpdate);
            if ($toUpdate !== []) {
                $calendarEvent->update($toUpdate);
            }

            if (! empty($servicesPayload) && $calendarEvent->event_type === CalendarEvent::TYPE_MARCACAO) {
                $beforeSnapshot = $this->marcacaoServicesSnapshotFromModel($calendarEvent);
                $afterSnapshot = $this->marcacaoServicesSnapshotFromPayload($servicesPayload);
                if (json_encode($beforeSnapshot) !== json_encode($afterSnapshot)) {
                    $calendarEvent->eventServices()->detach();
                    foreach ($servicesPayload as $i => $item) {
                        $calendarEvent->eventServices()->attach((int) $item['service_id'], [
                            'duration' => isset($item['duration']) ? (int) $item['duration'] : null,
                            'price' => isset($item['price']) ? (float) $item['price'] : null,
                            'original_price' => isset($item['original_price']) ? (float) $item['original_price'] : (isset($item['price']) ? (float) $item['price'] : null),
                            'sort_order' => $i,
                        ]);
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
                    activity()
                        ->performedOn($calendarEvent)
                        ->causedBy(auth()->user())
                        ->event('updated')
                        ->withProperties([
                            'servicos' => $calendarEvent->eventServices->pluck('name')->implode(', '),
                        ])
                        ->log('Serviços ou extras da marcação alterados');
                }
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
            && $freshEvent->client_id
        ) {
            $clientEmail = $freshEvent->client?->email;
            if ($clientEmail && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    Notification::route('mail', $this->resolveClientNotificationRecipientEmail($clientEmail))->notify(new ClientAppointmentRescheduledNotification(
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
        $sale = $calendarEvent->sale;
        if ($sale && $sale->status !== Sale::STATUS_ANULADO) {
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
            'status' => ['required', 'string', 'in:agendado,confirmado,chegou,iniciado,terminado,faltou,cancelado,completo'],
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'cancellation_type' => ['nullable', 'string', 'in:faltou,cancelado'],
            'refund_reserva' => ['nullable', 'boolean'],
            'avisou_dentro_prazo' => ['nullable', 'boolean'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $newStatus = $validated['status'];

        // Verificar se a transição é válida
        $currentStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        if (! $calendarEvent->canTransitionTo($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível alterar o estado de "'.CalendarEvent::statuses()[$currentStatus].'" para "'.CalendarEvent::statuses()[$newStatus].'".',
            ], 422);
        }

        $update = ['status' => $newStatus];
        if (in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO], true)) {
            $update['cancellation_reason'] = isset($validated['cancellation_reason']) ? trim($validated['cancellation_reason']) ?: null : null;
            $update['cancellation_type'] = $validated['cancellation_type'] ?? $newStatus;
            $update['refund_reserva'] = isset($validated['refund_reserva']) ? (bool) $validated['refund_reserva'] : null;
            $update['avisou_dentro_prazo'] = isset($validated['avisou_dentro_prazo']) ? (bool) $validated['avisou_dentro_prazo'] : null;
        } else {
            $update['cancellation_reason'] = null;
            $update['cancellation_type'] = null;
            $update['refund_reserva'] = null;
            $update['avisou_dentro_prazo'] = null;
        }
        $marcacao = $calendarEvent->event_type === CalendarEvent::TYPE_MARCACAO;
        $previousStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        $statusUpdateApplied = false;

        if ($this->calendarEventStatusPayloadDiffers($calendarEvent, $update)) {
            $calendarEvent->update($update);
            $statusUpdateApplied = true;
        }

        if ($statusUpdateApplied && $marcacao) {
            $ev = $calendarEvent->fresh(['client', 'service', 'eventServices']);
            if ($ev && $ev->event_type === CalendarEvent::TYPE_MARCACAO && $ev->user_id) {
                $this->notifyMarcacaoRecipient((int) $ev->user_id, $ev, 'status_changed', $previousStatus);
            }
            $notifyClient = (bool) ($validated['notify_client'] ?? false);
            if (
                $notifyClient
                && in_array($newStatus, [CalendarEvent::STATUS_FALTOU, CalendarEvent::STATUS_CANCELADO], true)
            ) {
                $clientEv = $calendarEvent->fresh(['client']);
                $email = $clientEv?->client?->email;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        Notification::route('mail', $this->resolveClientNotificationRecipientEmail($email))
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

        return response()->json([
            'success' => true,
            'message' => 'Estado atualizado com sucesso.',
            'event' => $this->formatEventForCalendar($calendarEvent->fresh()),
            'status' => $newStatus,
            'status_label' => CalendarEvent::statuses()[$newStatus],
            'status_icon' => $calendarEvent->fresh()->status_icon,
        ]);
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
     * @return list<array{service_id: int, duration: int, price: float, original_price: float, extras: list<array{extra_id: int, duration: int, price: float}>}>
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
     * @return list<array{service_id: int, duration: int, price: float, original_price: float, extras: list<array{extra_id: int, duration: int, price: float}>}>
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

        $event->loadMissing(['client', 'eventServices', 'user.agent', 'personalTimeType', 'sale']);
        $event->eventServices->each(fn ($s) => $s->pivot->load(['extras', 'extras.extra']));
        $isTempoPessoal = $event->event_type === CalendarEvent::TYPE_TEMPO_PESSOAL;
        $agentColor = $isTempoPessoal ? null : ($event->user?->agent?->color);
        $eventServicesData = $event->eventServices->isNotEmpty()
            ? $event->eventServices->map(function ($s) {
                $dur = ($s->pivot->duration ?? $s->duration);

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'duration' => $dur,
                    'price' => (float) ($s->pivot->price ?? $s->price),
                    'original_price' => $s->pivot->original_price !== null ? (float) $s->pivot->original_price : (float) ($s->pivot->price ?? $s->price),
                    'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.').' €' : $s->formatted_price,
                    'formatted_duration' => $this->formatDurationMinutes((int) $dur),
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
            ? $event->eventServices->pluck('name')->join(', ')
            : ($event->service?->name ?? null);

        $statusLabel = $isTempoPessoal ? 'Tempo pessoal' : (CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado');
        $statusIcon = $isTempoPessoal ? null : $event->status_icon;
        $bgColor = $agentColor ?: ($isTempoPessoal ? '#dee2e6' : null);
        if ($isTempoPessoal) {
            $className = 'agenda-event-tempo-pessoal';
        }

        $hasInvoice = $event->sale && $event->sale->status !== Sale::STATUS_ANULADO;
        $arr = [
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $event->start_at->toIso8601String(),
            'end' => $event->end_at->toIso8601String(),
            'className' => $className,
            'backgroundColor' => $bgColor,
            'editable' => ! $hasInvoice,
            'extendedProps' => [
                'client_id' => $event->client_id,
                'client_name' => $event->client?->name,
                'client_phone' => $event->client?->phone,
                'client_formatted_phone' => $event->client?->formatted_phone,
                'client_has_email' => (bool) ($event->client_id && $event->client?->email && filter_var($event->client->email, FILTER_VALIDATE_EMAIL)),
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
            ],
        ];
        if ($withResourceId) {
            $arr['resourceId'] = $event->user_id ? (string) $event->user_id : 'unassigned';
        }

        return $arr;
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
        if ($event->event_type !== CalendarEvent::TYPE_MARCACAO) {
            return;
        }
        $user = User::find($userId);
        if (! $user || auth()->id() === $user->id) {
            return;
        }
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
}
