<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Property;
use App\Models\Visit;
use App\Services\CalendarEventService;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * Store a new visit
     */
    public function store(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'scheduled_at' => ['required', 'date'],
        ]);

        // Verificar se o imóvel está associado à oportunidade
        if (!$opportunity->properties()->where('properties.id', $validated['property_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'O imóvel não está associado a esta oportunidade.',
            ], 422);
        }

        $visit = $opportunity->visits()->create([
            'property_id' => $validated['property_id'],
            'scheduled_at' => $validated['scheduled_at'],
            'status' => Visit::STATUS_AGENDADA,
        ]);

        CalendarEventService::createFromVisit($visit);

        return response()->json([
            'success' => true,
            'message' => 'Visita agendada com sucesso.',
            'visit' => $visit->load('property.mainImage'),
        ]);
    }

    /**
     * Update a visit (status, feedback)
     */
    public function update(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:agendada,realizada,cancelada'],
            'client_feedback_strengths' => ['nullable', 'string'],
            'client_feedback_weaknesses' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
        ]);

        $visit->update($validated);

        if ($visit->calendarEvent) {
            $visit->calendarEvent->update([
                'start_at' => $visit->scheduled_at,
                'end_at' => $visit->scheduled_at->copy()->addHour(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Visita atualizada com sucesso.',
            'visit' => $visit->fresh()->load('property.mainImage'),
        ]);
    }
}
