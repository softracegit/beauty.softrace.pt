<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Display the calendar (agenda) view.
     */
    public function index()
    {
        $eventTypes = CalendarEvent::eventTypes();
        // Mostrar apenas users com agent ativo; excluir Administradores da agenda (select de membros)
        $users = User::whereHas('agent', function ($query) {
            $query->where('status', Agent::STATUS_ACTIVE);
        })
            ->whereNotIn('role', [User::ROLE_ADMIN])
            ->orderBy('name')
            ->get();

        return view('agenda.index', compact('eventTypes', 'users'));
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
                ? asset('storage/' . $agent->avatar)
                : asset("assets/images/avatar/avatar-{$avatarNum}.jpg");
            return [
                'id' => (string) $agent->user_id, // Usar user_id como ID do recurso
                'title' => $agent->name,
                'extendedProps' => [
                    'avatarUrl' => $avatarUrl,
                ],
            ];
        })->values();
        
        return response()->json($result);
    }

    /**
     * Get events in FullCalendar format (JSON).
     * With for_resources=1 returns events for all consultants (for resource view).
     */
    public function events(Request $request)
    {
        $start = $request->get('start');
        $end = $request->get('end');
        $forResources = $request->boolean('for_resources');

        $query = CalendarEvent::query()->with(['user', 'service', 'client', 'eventServices', 'eventable']);

        // Verificar se o utilizador pode ver todos os eventos (admin ou diretor)
        $canViewAll = auth()->user()->canManageAgents();

        if (!$forResources) {
            // Se não pode ver todos, mostrar apenas eventos do utilizador atual
            // Se pode ver todos (admin/diretor), mostrar todos os eventos
            if (!$canViewAll) {
                $query->where('user_id', auth()->id());
            }
        } else {
            // Na vista de consultor, só mostrar eventos com consultor atribuído (excluir Administradores)
            $activeAgentUserIds = Agent::where('status', Agent::STATUS_ACTIVE)
                ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
                ->pluck('user_id')
                ->filter()
                ->toArray();
            $query->whereIn('user_id', $activeAgentUserIds);
        }

        if ($start) {
            $query->where('end_at', '>=', $start);
        }
        if ($end) {
            $query->where('start_at', '<=', $end);
        }

        $events = $query->get();

        // Na vista de recursos, apenas users com agents ativos são válidos (excluir Administradores)
        $validUserIds = $forResources 
            ? Agent::where('status', Agent::STATUS_ACTIVE)
                ->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_ADMIN))
                ->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->flip()
                ->all() 
            : [];

        $result = $events->map(function (CalendarEvent $event) use ($forResources, $validUserIds) {
            $classMap = CalendarEvent::typeClassMap();
            $className = $classMap[$event->event_type] ?? 'bg-secondary';

            $item = [
                'id' => (string) $event->id,
                'title' => $event->title,
                'start' => $event->start_at->toIso8601String(),
                'end' => $event->end_at->toIso8601String(),
                'className' => $className,
                'extendedProps' => [
                    'client_name' => $event->client?->name,
                    'description' => $event->description,
                    'event_type' => $event->event_type,
                    'event_type_label' => CalendarEvent::eventTypes()[$event->event_type] ?? $event->event_type,
                    'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                    'status_label' => CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado',
                    'status_icon' => $event->status_icon,
                    'user_id' => $event->user_id,
                    'user_name' => $event->user?->name,
                    'service_id' => $event->service_id,
                    'service_name' => $event->eventServices->isNotEmpty()
                        ? $event->eventServices->pluck('name')->join(', ')
                        : ($event->service?->name ?? null),
                    'event_services' => $event->eventServices->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'duration' => $dur = ($s->pivot->duration ?? $s->duration),
                        'price' => (float) ($s->pivot->price ?? $s->price),
                        'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.') . ' €' : $s->formatted_price,
                        'formatted_duration' => $this->formatDurationMinutes((int) $dur),
                    ])->values()->all(),
                    'is_source_editable' => $event->isSourceEditable(),
                    'is_deletable' => $event->isDeletableFromCalendar(),
                    'is_time_editable' => $event->isTimeEditable(),
                    'eventable_type' => $event->eventable_type,
                    'eventable_id' => $event->eventable_id,
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
            if ($forResources && !isset($item['resourceId'])) {
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
        if (!$agent) {
            return response()->json(['categories' => []]);
        }

        $services = $agent->services()->with('category')->orderBy('name')->get();
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
            $arr['avatar_url'] = $c->avatar ? asset('storage/' . $c->avatar) : null;
            return $arr;
        });
        return response()->json($result);
    }

    /**
     * Show a single event (for modal/details).
     * In resource view the user may view events of other consultants (permission check can be added).
     */
    public function show(CalendarEvent $calendarEvent)
    {
        try {
            $calendarEvent->load(['user', 'service', 'client', 'eventServices.category', 'eventable']);
            
            $userAvatarUrl = null;
            try {
                if ($calendarEvent->user) {
                    $calendarEvent->user->load('agent');
                    if ($calendarEvent->user->agent) {
                        $agent = $calendarEvent->user->agent;
                        $avatarNum = ($agent->id % 9) + 1;
                        $userAvatarUrl = $agent->avatar
                            ? asset('storage/' . $agent->avatar)
                            : asset("assets/images/avatar/avatar-{$avatarNum}.jpg");
                    }
                }
            } catch (\Exception $e) {
                // Se houver erro ao carregar agent, continua sem avatar
                $userAvatarUrl = null;
            }

            $payload = [
                'id' => $calendarEvent->id,
                'title' => $calendarEvent->title ?? '',
                'start_at' => $calendarEvent->start_at ? $calendarEvent->start_at->toIso8601String() : null,
                'end_at' => $calendarEvent->end_at ? $calendarEvent->end_at->toIso8601String() : null,
                'description' => $calendarEvent->description,
                'event_type' => $calendarEvent->event_type ?? 'manual',
                'event_type_label' => CalendarEvent::eventTypes()[$calendarEvent->event_type] ?? ($calendarEvent->event_type ?? 'Manual'),
                'status' => $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO,
                'status_label' => CalendarEvent::statuses()[$calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado',
                'status_icon' => $calendarEvent->status_icon,
                'cancellation_reason' => $calendarEvent->cancellation_reason,
                'user_id' => $calendarEvent->user_id,
                'user_name' => $calendarEvent->user?->name,
                'user_avatar_url' => $userAvatarUrl,
                'service_id' => $calendarEvent->service_id,
                'service_name' => $calendarEvent->service?->name,
                'client_id' => $calendarEvent->client_id,
                'client_name' => $calendarEvent->client?->name,
                'client_email' => $calendarEvent->client?->email,
                'client_avatar_url' => $calendarEvent->client?->avatar ? asset('storage/' . $calendarEvent->client->avatar) : null,
                'event_services' => $calendarEvent->eventServices->map(function ($s) {
                    $cat = $s->category;
                    $color = $cat?->color ?? '#6c757d';
                    $duration = $s->pivot->duration ?? $s->duration;
                    $price = (float) ($s->pivot->price ?? $s->price);
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'duration' => $duration,
                        'price' => $price,
                        'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.') . ' €' : $s->formatted_price,
                        'formatted_duration' => $this->formatDurationMinutes((int) $duration),
                        'color' => $color,
                    ];
                })->values()->all(),
                'is_source_editable' => $calendarEvent->isSourceEditable(),
                'is_deletable' => $calendarEvent->isDeletableFromCalendar(),
                'is_time_editable' => $calendarEvent->isTimeEditable(),
            ];

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
                        'status' => $lead->status,
                    ];
                } catch (\Exception $e) {
                    // Se houver erro ao carregar lead, continua sem detalhes
                }
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar evento: ' . $e->getMessage(), [
                'event_id' => $calendarEvent->id,
                'exception' => $e
            ]);
            return response()->json([
                'error' => 'Erro ao carregar detalhes do evento.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new manual (or outro) event.
     */
    public function store(Request $request)
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
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
        ];
        $validated = $request->validate($rules);

        $servicesPayload = $request->input('services', []);
        if (($validated['event_type'] ?? '') === CalendarEvent::TYPE_MARCACAO) {
            if (!empty($servicesPayload)) {
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

        if (!empty($servicesPayload)) {
            foreach ($servicesPayload as $i => $item) {
                $event->eventServices()->attach((int) $item['service_id'], [
                    'duration' => isset($item['duration']) ? (int) $item['duration'] : null,
                    'price' => isset($item['price']) ? (float) $item['price'] : null,
                    'sort_order' => $i,
                ]);
            }
            $event->load('eventServices');
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
        $rules = [
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:agendado,confirmado,chegou,iniciado,faltou,cancelado'],
            'cancellation_reason' => ['nullable', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($calendarEvent->isSourceEditable()) {
            $rules['title'] = ['sometimes', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string'];
            $rules['event_type'] = ['sometimes', 'in:manual,outro,marcacao,tempo_pessoal'];
            $rules['service_id'] = ['nullable', 'exists:services,id'];
        }

        $validated = $request->validate($rules);

        $servicesPayload = $request->input('services', []);

        if (isset($validated['event_type']) && $validated['event_type'] === CalendarEvent::TYPE_MARCACAO && $calendarEvent->isSourceEditable()) {
            if (!empty($servicesPayload)) {
                $validated['service_id'] = (int) $servicesPayload[0]['service_id'];
            } elseif (!array_key_exists('service_id', $validated)) {
                $request->validate(['service_id' => ['required', 'exists:services,id']]);
                $validated['service_id'] = $request->input('service_id');
            }
        } elseif (isset($validated['event_type']) && $validated['event_type'] !== CalendarEvent::TYPE_MARCACAO) {
            $validated['service_id'] = null;
        }

        if ($calendarEvent->isTimeEditable() && (isset($validated['start_at']) || isset($validated['end_at']))) {
            $updates = [];
            if (isset($validated['start_at'])) {
                $updates['start_at'] = $validated['start_at'];
            }
            if (isset($validated['end_at'])) {
                $updates['end_at'] = $validated['end_at'];
            }
            if (!empty($updates)) {
                $calendarEvent->update($updates);
            }

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

        if (array_key_exists('user_id', $validated)) {
            $newUserId = $validated['user_id'] ?: null;
            if ($newUserId && User::find($newUserId)?->role === User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível atribuir o evento a um Administrador.',
                ], 422);
            }
            $calendarEvent->user_id = $newUserId;
            $calendarEvent->save();
        }

        if (isset($validated['status'])) {
            $update = ['status' => $validated['status']];
            if (isset($validated['cancellation_reason'])) {
                $update['cancellation_reason'] = $validated['status'] === 'cancelado' ? $validated['cancellation_reason'] : null;
            } elseif ($validated['status'] !== 'cancelado') {
                $update['cancellation_reason'] = null;
            }
            $calendarEvent->update($update);
        }

        if ($calendarEvent->isSourceEditable()) {
            $allowed = ['title', 'description', 'event_type', 'service_id', 'client_id'];
            $toUpdate = array_filter($validated, fn ($k) => in_array($k, $allowed, true), ARRAY_FILTER_USE_KEY);
            if (!empty($toUpdate)) {
                $calendarEvent->update($toUpdate);
            }

            if (!empty($servicesPayload)) {
                $calendarEvent->eventServices()->detach();
                foreach ($servicesPayload as $i => $item) {
                    $calendarEvent->eventServices()->attach((int) $item['service_id'], [
                        'duration' => isset($item['duration']) ? (int) $item['duration'] : null,
                        'price' => isset($item['price']) ? (float) $item['price'] : null,
                        'sort_order' => $i,
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
     * Only the responsible user can delete (permission check can be extended).
     */
    public function destroy(CalendarEvent $calendarEvent)
    {
        if ($calendarEvent->user_id !== null && $calendarEvent->user_id !== auth()->id()) {
            abort(403, 'Apenas o consultor responsável pode eliminar este evento.');
        }
        if (!$calendarEvent->isDeletableFromCalendar()) {
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
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:agendado,confirmado,chegou,iniciado,faltou,cancelado'],
        ]);

        $newStatus = $validated['status'];

        // Verificar se a transição é válida
        $currentStatus = $calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO;
        if (!$calendarEvent->canTransitionTo($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível alterar o estado de "' . CalendarEvent::statuses()[$currentStatus] . '" para "' . CalendarEvent::statuses()[$newStatus] . '".',
            ], 422);
        }

        $calendarEvent->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Estado atualizado com sucesso.',
            'event' => $this->formatEventForCalendar($calendarEvent->fresh()),
            'status' => $newStatus,
            'status_label' => CalendarEvent::statuses()[$newStatus],
            'status_icon' => $calendarEvent->fresh()->status_icon,
        ]);
    }

    private function formatEventForCalendar(CalendarEvent $event, bool $withResourceId = false): array
    {
        $classMap = CalendarEvent::typeClassMap();
        $className = $classMap[$event->event_type] ?? 'bg-secondary';

        $event->loadMissing('eventServices');
        $eventServicesData = $event->eventServices->isNotEmpty()
            ? $event->eventServices->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'duration' => $dur = ($s->pivot->duration ?? $s->duration),
                'price' => (float) ($s->pivot->price ?? $s->price),
                'formatted_price' => $s->pivot->price !== null ? number_format((float) $s->pivot->price, 2, ',', '.') . ' €' : $s->formatted_price,
                'formatted_duration' => $this->formatDurationMinutes((int) $dur),
            ])->values()->all()
            : [];
        $serviceName = $event->eventServices->isNotEmpty()
            ? $event->eventServices->pluck('name')->join(', ')
            : ($event->service?->name ?? null);

        $arr = [
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $event->start_at->toIso8601String(),
            'end' => $event->end_at->toIso8601String(),
            'className' => $className,
            'extendedProps' => [
                'description' => $event->description,
                'event_type' => $event->event_type,
                'event_type_label' => CalendarEvent::eventTypes()[$event->event_type] ?? $event->event_type,
                'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                'status_label' => CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado',
                'status_icon' => $event->status_icon,
                'user_id' => $event->user_id,
                'user_name' => $event->user?->name,
                'service_id' => $event->service_id,
                'service_name' => $serviceName,
                'event_services' => $eventServicesData,
                'is_source_editable' => $event->isSourceEditable(),
                'is_deletable' => $event->isDeletableFromCalendar(),
                'is_time_editable' => $event->isTimeEditable(),
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
            return $hours . 'h ' . $mins . 'min';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        return $mins . 'min';
    }
}
