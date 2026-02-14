<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Note;
use App\Models\Agent;
use App\Services\CalendarEventService;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Local;
use App\Models\PropertyType;
use App\Models\PropertyTypology;
use App\Models\PropertyFeature;
use App\Models\TransactionType;
use App\Models\PropertyCondition;
use App\Http\Requests\ConvertLeadToOpportunityRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    /**
     * Display a listing of the leads.
     */
    public function index(Request $request)
    {
        $query = Lead::with('agent')->whereNull('archived_at')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('property_reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->paginate(15)->withQueryString();

        return view('leads.index', compact('leads'));
    }

    /**
     * Display the kanban board.
     */
    public function kanban(Request $request)
    {
        $query = Lead::with('agent', 'notes.user')->whereNull('archived_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Excluir estados de arquivo (Ganho e Perdido) do kanban
        $query->whereNotIn('status', [Lead::STATUS_GANHO, Lead::STATUS_PERDIDO]);

        $leads = $query->with('notes')->get();
        
        // Apenas estados ativos (excluir Ganho e Perdido)
        $allStatuses = Lead::getStatusOrder();
        $statuses = array_filter($allStatuses, function($status) {
            return !in_array($status, [Lead::STATUS_GANHO, Lead::STATUS_PERDIDO]);
        });
        $statuses = array_values($statuses); // Reindexar array
        
        $statusLabels = Lead::statuses();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        return view('leads.kanban', compact('leads', 'statuses', 'statusLabels', 'agents'));
    }

    /**
     * Update lead status (via drag and drop).
     */
    public function updateStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lead::statuses()))],
        ]);

        $lead->update([
            'status' => $validated['status'],
            'status_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado atualizado com sucesso.',
            'lead' => $lead->load('agent', 'notes'),
        ]);
    }

    /**
     * Show the form for creating a new lead.
     */
    public function create()
    {
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();
        $districts = Local::getDistricts();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $typologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $transactionTypes = TransactionType::where('is_active', true)->orderBy('sort_order')->get();
        $conditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();
        return view('leads.create', compact('agents', 'districts', 'propertyTypes', 'typologies', 'features', 'transactionTypes', 'conditions'));
    }

    /**
     * Store a newly created lead.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Lead::types()))],
            'name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'id_district' => ['nullable', 'integer'],
            'id_city' => ['nullable', 'integer'],
            'id_parish' => ['nullable', 'integer'],
            'priority' => ['required', Rule::in(array_keys(Lead::priorities()))],
            'property_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Lead::statuses()))],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            // Preferência de imóvel
            'preference_property_type_id' => ['nullable', 'exists:property_types,id'],
            'preference_transaction_type_id' => ['nullable', 'exists:transaction_types,id'],
            'preference_property_condition_id' => ['nullable', 'exists:property_conditions,id'],
            'preference_min_price' => ['nullable', 'numeric', 'min:0'],
            'preference_max_price' => ['nullable', 'numeric', 'min:0'],
            'preference_typologies' => ['nullable', 'array'],
            'preference_typologies.*' => ['exists:property_typologies,id'],
            'preference_locations' => ['nullable', 'array'],
            'preference_locations.*.id_district' => ['nullable', 'integer'],
            'preference_locations.*.id_city' => ['nullable', 'integer'],
            'preference_locations.*.id_parish' => ['nullable', 'integer'],
            'preference_features' => ['nullable', 'array'],
            'preference_features.*' => ['exists:property_features,id'],
            'preference_notes' => ['nullable', 'string'],
        ]);

        $validated['status_changed_at'] = now();

        $lead = Lead::create($validated);

        CalendarEventService::syncFromLead($lead);

        // Criar preferência se os dados foram fornecidos
        if ($request->filled('preference_property_type_id') && $request->filled('preference_transaction_type_id')) {
            $preference = $lead->propertyPreferences()->create([
                'property_type_id' => $validated['preference_property_type_id'],
                'transaction_type_id' => $validated['preference_transaction_type_id'],
                'property_condition_id' => $validated['preference_property_condition_id'] ?? null,
                'min_price' => $validated['preference_min_price'] ?? 0,
                'max_price' => $validated['preference_max_price'] ?? null,
                'notes' => $validated['preference_notes'] ?? null,
                'is_active' => true,
            ]);

            // Salvar localizações
            if (isset($validated['preference_locations'])) {
                foreach ($validated['preference_locations'] as $location) {
                    if (!empty($location['id_district']) || !empty($location['id_city']) || !empty($location['id_parish'])) {
                        $preference->preferenceLocations()->create([
                            'id_district' => $location['id_district'] ?? null,
                            'id_city' => $location['id_city'] ?? null,
                            'id_parish' => $location['id_parish'] ?? null,
                        ]);
                    }
                }
            }

            if (isset($validated['preference_typologies'])) {
                $preference->typologies()->sync($validated['preference_typologies']);
            }

            if (isset($validated['preference_features'])) {
                $preference->features()->sync($validated['preference_features']);
            }
        }

        return redirect()->route('leads.kanban')
            ->with('success', 'Lead criada com sucesso.');
    }

    /**
     * Display the specified lead.
     */
    public function show(Lead $lead)
    {
        $lead->load([
            'agent', 
            'notes.user',
            'propertyPreferences.typologies',
            'propertyPreferences.preferenceLocations',
            'propertyPreferences.features',
            'propertyPreferences.transactionType',
            'propertyPreferences.propertyType',
            'propertyPreferences.propertyCondition',
            'opportunity'
        ]);
        
        // Mostrar apenas estados do progress bar (excluindo Perdido)
        $statuses = Lead::getProgressStatusOrder();
        $statusLabels = Lead::statuses();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();
        
        // Dados para o modal de conversão
        $preference = $lead->propertyPreferences->first();
        $districts = \App\Models\Local::getDistricts();
        $propertyTypes = \App\Models\PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $typologies = \App\Models\PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $features = \App\Models\PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $transactionTypes = \App\Models\TransactionType::where('is_active', true)->orderBy('sort_order')->get();
        $conditions = \App\Models\PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();

        return view('leads.show', compact(
            'lead', 
            'statuses', 
            'statusLabels', 
            'agents',
            'preference',
            'districts',
            'propertyTypes',
            'typologies',
            'features',
            'transactionTypes',
            'conditions'
        ));
    }

    /**
     * Show the form for editing the specified lead.
     */
    public function edit(Lead $lead)
    {
        $lead->load([
            'propertyPreferences.typologies',
            'propertyPreferences.features',
            'propertyPreferences.preferenceLocations',
            'notes.user'
        ]);

        $preference = $lead->propertyPreferences->first();

        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();
        $districts = Local::getDistricts();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $typologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $transactionTypes = TransactionType::where('is_active', true)->orderBy('sort_order')->get();
        $conditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();

        // Buscar dados de localização se existirem
        $selectedDistrict = null;
        $selectedCity = null;
        $selectedParish = null;
        $cities = collect();
        $parishes = collect();

        if ($lead->id_district) {
            $selectedDistrict = $lead->id_district;
            $cities = Local::getCitiesByDistrict($lead->id_district);
            
            if ($lead->id_city) {
                $selectedCity = $lead->id_city;
                $parishes = Local::getParishesByCity($lead->id_city);
                
                if ($lead->id_parish) {
                    $selectedParish = $lead->id_parish;
                }
            }
        }

        return view('leads.edit', compact('lead', 'agents', 'districts', 'selectedDistrict', 'selectedCity', 'selectedParish', 'cities', 'parishes', 'preference', 'propertyTypes', 'typologies', 'features', 'transactionTypes', 'conditions'));
    }

    /**
     * Update the specified lead.
     */
    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Lead::types()))],
            'name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'id_district' => ['nullable', 'integer'],
            'id_city' => ['nullable', 'integer'],
            'id_parish' => ['nullable', 'integer'],
            'priority' => ['required', Rule::in(array_keys(Lead::priorities()))],
            'property_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Lead::statuses()))],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'notes' => ['nullable', 'string'],
            // Preferência de imóvel
            'preference_property_type_id' => ['nullable', 'exists:property_types,id'],
            'preference_transaction_type_id' => ['nullable', 'exists:transaction_types,id'],
            'preference_property_condition_id' => ['nullable', 'exists:property_conditions,id'],
            'preference_min_price' => ['nullable', 'numeric', 'min:0'],
            'preference_max_price' => ['nullable', 'numeric', 'min:0'],
            'preference_typologies' => ['nullable', 'array'],
            'preference_typologies.*' => ['exists:property_typologies,id'],
            'preference_locations' => ['nullable', 'array'],
            'preference_locations.*.id_district' => ['nullable', 'integer'],
            'preference_locations.*.id_city' => ['nullable', 'integer'],
            'preference_locations.*.id_parish' => ['nullable', 'integer'],
            'preference_features' => ['nullable', 'array'],
            'preference_features.*' => ['exists:property_features,id'],
            'preference_notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        // Se o status mudou, atualizar status_changed_at
        if ($lead->status !== $validated['status']) {
            $validated['status_changed_at'] = now();
        }

        $lead->update($validated);

        CalendarEventService::syncFromLead($lead->fresh());

        // Gerir preferência única
        $preference = $lead->propertyPreferences->first();

        // Verificar se os campos obrigatórios foram preenchidos
        $hasPreferenceData = $request->filled('preference_property_type_id') && $request->filled('preference_transaction_type_id');

        if ($hasPreferenceData) {
            // Criar ou atualizar preferência
            if ($preference) {
                $preference->update([
                    'property_type_id' => $validated['preference_property_type_id'],
                    'transaction_type_id' => $validated['preference_transaction_type_id'],
                    'property_condition_id' => $validated['preference_property_condition_id'] ?? null,
                    'min_price' => $validated['preference_min_price'] ?? 0,
                    'max_price' => $validated['preference_max_price'] ?? null,
                    'notes' => $validated['preference_notes'] ?? null,
                ]);

                // Remover localizações antigas e criar novas
                $preference->preferenceLocations()->delete();
                if (isset($validated['preference_locations'])) {
                    foreach ($validated['preference_locations'] as $location) {
                        if (!empty($location['id_district']) || !empty($location['id_city']) || !empty($location['id_parish'])) {
                            $preference->preferenceLocations()->create([
                                'id_district' => $location['id_district'] ?? null,
                                'id_city' => $location['id_city'] ?? null,
                                'id_parish' => $location['id_parish'] ?? null,
                            ]);
                        }
                    }
                }

                // Sincronizar tipologias e características
                if (isset($validated['preference_typologies'])) {
                    $preference->typologies()->sync($validated['preference_typologies']);
                } else {
                    $preference->typologies()->sync([]);
                }

                if (isset($validated['preference_features'])) {
                    $preference->features()->sync($validated['preference_features']);
                } else {
                    $preference->features()->sync([]);
                }
            } else {
                // Criar nova preferência
                $preference = $lead->propertyPreferences()->create([
                    'property_type_id' => $validated['preference_property_type_id'],
                    'transaction_type_id' => $validated['preference_transaction_type_id'],
                    'property_condition_id' => $validated['preference_property_condition_id'] ?? null,
                    'min_price' => $validated['preference_min_price'] ?? 0,
                    'max_price' => $validated['preference_max_price'] ?? null,
                    'notes' => $validated['preference_notes'] ?? null,
                    'is_active' => true,
                ]);

                // Salvar localizações
                if (isset($validated['preference_locations'])) {
                    foreach ($validated['preference_locations'] as $location) {
                        if (!empty($location['id_district']) || !empty($location['id_city']) || !empty($location['id_parish'])) {
                            $preference->preferenceLocations()->create([
                                'id_district' => $location['id_district'] ?? null,
                                'id_city' => $location['id_city'] ?? null,
                                'id_parish' => $location['id_parish'] ?? null,
                            ]);
                        }
                    }
                }

                if (isset($validated['preference_typologies'])) {
                    $preference->typologies()->sync($validated['preference_typologies']);
                }

                if (isset($validated['preference_features'])) {
                    $preference->features()->sync($validated['preference_features']);
                }
            }
        } else {
            // Se não há dados de preferência e existe uma preferência, removê-la
            if ($preference) {
                $preference->delete();
            }
        }

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Lead atualizada com sucesso.');
    }

    /**
     * Remove the specified lead.
     */
    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->route('leads.index')
            ->with('success', 'Lead removida com sucesso.');
    }

    /**
     * Store a note for the lead.
     */
    public function storeNote(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $lead->notes()->create([
            'user_id' => auth()->id(),
            'type' => $validated['type'] ?? Note::TYPE_GERAL,
            'note' => $validated['note'],
            'reminder_at' => $validated['reminder_at'] ?? null,
            'reminder_advance_minutes' => $validated['reminder_advance_minutes'] ?? 15,
            'reminder_sent' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nota adicionada com sucesso.',
        ]);
    }

    /**
     * Convert lead to opportunity.
     */
    public function convertToOpportunity(ConvertLeadToOpportunityRequest $request, Lead $lead)
    {
        try {
            DB::beginTransaction();

            // Criar ou atualizar cliente
            $client = Client::where('email', $request->client_email)->first();
            
            if (!$client) {
                $client = Client::create([
                    'name' => $request->client_name,
                    'email' => $request->client_email,
                    'phone' => $request->client_phone,
                    'type' => Client::TYPE_POTENCIAL_CLIENTE,
                    'status' => Client::STATUS_ACTIVE,
                ]);
            } else {
                // Atualizar dados do cliente se necessário
                $client->update([
                    'name' => $request->client_name,
                    'phone' => $request->client_phone ?? $client->phone,
                ]);
            }

            // Determinar tipo da oportunidade baseado no tipo da lead
            $opportunityType = match($lead->type) {
                Lead::TYPE_COMPRA => Opportunity::TYPE_COMPRA,
                Lead::TYPE_ARRENDAMENTO => Opportunity::TYPE_ARRENDAMENTO,
                Lead::TYPE_ANGARIACAO => Opportunity::TYPE_ANGARIACAO,
                default => Opportunity::TYPE_COMPRA,
            };

            // Criar oportunidade
            $opportunity = Opportunity::create([
                'reference' => Opportunity::generateReference(),
                'status' => Opportunity::STATUS_POR_TRATAR,
                'priority' => $request->priority,
                'type' => $opportunityType,
                'client_id' => $client->id,
                'lead_id' => $lead->id,
                'agent_id' => $request->agent_id ?? $lead->agent_id,
                'notes' => $request->notes ?? $lead->notes,
                'status_changed_at' => now(),
            ]);

            // Criar preferências da oportunidade
            if ($request->filled('preference_property_type_id') && $request->filled('preference_transaction_type_id')) {
                $preference = $opportunity->propertyPreferences()->create([
                    'property_type_id' => $request->preference_property_type_id,
                    'transaction_type_id' => $request->preference_transaction_type_id,
                    'property_condition_id' => $request->preference_property_condition_id ?? null,
                    'min_price' => $request->preference_min_price ?? null,
                    'max_price' => $request->preference_max_price ?? null,
                    'notes' => $request->preference_notes ?? null,
                    'is_active' => true,
                ]);

                // Salvar localizações
                if ($request->filled('preference_locations')) {
                    foreach ($request->preference_locations as $location) {
                        if (!empty($location['id_district']) || !empty($location['id_city']) || !empty($location['id_parish'])) {
                            $preference->preferenceLocations()->create([
                                'id_district' => $location['id_district'] ?? null,
                                'id_city' => $location['id_city'] ?? null,
                                'id_parish' => $location['id_parish'] ?? null,
                            ]);
                        }
                    }
                }

                // Sincronizar tipologias
                if ($request->filled('preference_typologies')) {
                    $preference->typologies()->sync($request->preference_typologies);
                }

                // Sincronizar características
                if ($request->filled('preference_features')) {
                    $preference->features()->sync($request->preference_features);
                }
            }

            // Log status inicial
            $opportunity->statusLogs()->create([
                'old_status' => null,
                'new_status' => $opportunity->status,
                'changed_by' => auth()->id(),
                'notes' => 'Oportunidade criada a partir da lead: ' . $lead->getLeadIdAttribute(),
                'created_at' => now(),
            ]);

            // Atualizar status da lead para "Ganho"
            if ($lead->status !== Lead::STATUS_GANHO) {
                $lead->update([
                    'status' => Lead::STATUS_GANHO,
                    'status_changed_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead convertida em oportunidade com sucesso.',
                'redirect' => route('opportunities.show', $opportunity),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao converter lead: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive the specified lead.
     */
    public function archive(Lead $lead)
    {
        $lead->archive();

        return response()->json([
            'success' => true,
            'message' => 'Lead arquivada com sucesso.',
        ]);
    }

    /**
     * Restore a lost lead to "Por Tratar" status or unarchive a lead.
     */
    public function restore(Request $request, Lead $lead)
    {
        // Se for para desarquivar
        if ($request->has('action') && $request->action === 'unarchive') {
            $lead->restore();

            return response()->json([
                'success' => true,
                'message' => 'Lead desarquivada com sucesso.',
            ]);
        }

        // Se for para recuperar lead perdida
        if ($lead->status !== Lead::STATUS_PERDIDO) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas leads perdidas podem ser recuperadas.',
            ], 400);
        }

        $lead->update([
            'status' => Lead::STATUS_POR_TRATAR,
            'status_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead recuperada com sucesso.',
        ]);
    }
}
