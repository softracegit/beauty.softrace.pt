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
        // Mostrar apenas users que têm agent associado (agentes ativos)
        $users = User::whereHas('agent', function ($query) {
            $query->where('status', Agent::STATUS_ACTIVE);
        })->orderBy('name')->get();

        return view('agenda.index', compact('eventTypes', 'users'));
    }

    /**
     * Get consultant resources for the resource view (users the current user can see).
     */
    public function resources(Request $request)
    {
        // Mostrar apenas agents ativos como recursos
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)
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

        $query = CalendarEvent::query()->with(['user', 'eventable']);

        // Verificar se o utilizador pode ver todos os eventos (admin ou diretor)
        $canViewAll = auth()->user()->canManageAgents();

        if (!$forResources) {
            // Se não pode ver todos, mostrar apenas eventos do utilizador atual
            // Se pode ver todos (admin/diretor), mostrar todos os eventos
            if (!$canViewAll) {
                $query->where('user_id', auth()->id());
            }
        } else {
            // Na vista de consultor, só mostrar eventos com consultor atribuído (user com agent ativo)
            $activeAgentUserIds = Agent::where('status', Agent::STATUS_ACTIVE)
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

        // Na vista de recursos, apenas users com agents ativos são válidos
        $validUserIds = $forResources 
            ? Agent::where('status', Agent::STATUS_ACTIVE)
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
                    'description' => $event->description,
                    'event_type' => $event->event_type,
                    'event_type_label' => CalendarEvent::eventTypes()[$event->event_type] ?? $event->event_type,
                    'status' => $event->status ?? CalendarEvent::STATUS_AGENDADO,
                    'status_label' => CalendarEvent::statuses()[$event->status ?? CalendarEvent::STATUS_AGENDADO] ?? 'Agendado',
                    'status_icon' => $event->status_icon,
                    'user_id' => $event->user_id,
                    'user_name' => $event->user?->name,
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
     * Show a single event (for modal/details).
     * In resource view the user may view events of other consultants (permission check can be added).
     */
    public function show(CalendarEvent $calendarEvent)
    {
        try {
            $calendarEvent->load(['user', 'eventable']);
            
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
                'user_id' => $calendarEvent->user_id,
                'user_name' => $calendarEvent->user?->name,
                'user_avatar_url' => $userAvatarUrl,
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
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'description' => ['nullable', 'string'],
            'event_type' => ['required', 'in:manual,outro'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();
        $validated['status'] = $validated['status'] ?? CalendarEvent::STATUS_AGENDADO;

        $event = CalendarEvent::create($validated);

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
        ];

        if ($calendarEvent->isSourceEditable()) {
            $rules['title'] = ['sometimes', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string'];
            $rules['event_type'] = ['sometimes', 'in:manual,outro'];
        }

        $validated = $request->validate($rules);

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
            $calendarEvent->user_id = $validated['user_id'] ?: null;
            $calendarEvent->save();
        }

        if ($calendarEvent->isSourceEditable()) {
            $calendarEvent->update(array_filter($validated, fn ($k) => in_array($k, ['title', 'description', 'event_type'], true), ARRAY_FILTER_USE_KEY));
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento atualizado com sucesso.',
            'event' => $this->formatEventForCalendar($calendarEvent->fresh()),
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
                'user_id' => $event->user_id,
                'user_name' => $event->user?->name,
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
}
