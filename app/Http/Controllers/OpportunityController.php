<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Models\Opportunity;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Property;
use App\Models\TransactionType;
use App\Models\PropertyType;
use App\Models\PropertyTypology;
use App\Models\PropertyFeature;
use App\Models\PropertyCondition;
use App\Models\Local;
use App\Models\OpportunityPropertyPreference;
use App\Models\OpportunityPreferenceLocation;
use App\Models\Note;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Opportunity::with('client', 'lead', 'agent')->orderBy('created_at', 'desc');

        // Pesquisa
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transaction_type_id')) {
            $query->whereHas('propertyPreferences', function ($q) use ($request) {
                $q->where('transaction_type_id', $request->transaction_type_id)
                  ->where('is_active', true);
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        $opportunities = $query->paginate(15)->withQueryString();

        $statuses = Opportunity::statuses();
        $transactionTypes = Opportunity::transactionTypes();
        $clients = Client::orderBy('name')->get();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        return view('opportunities.index', compact('opportunities', 'statuses', 'transactionTypes', 'clients', 'agents'));
    }

    /**
     * Display the kanban board.
     */
    public function kanban(Request $request)
    {
        $query = Opportunity::with('client', 'agent', 'properties', 'propertyPreferences');

        if ($request->filled('transaction_type_id')) {
            $query->whereHas('propertyPreferences', function ($q) use ($request) {
                $q->where('transaction_type_id', $request->transaction_type_id)
                  ->where('is_active', true);
            });
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        // Apenas estados ativos no kanban
        $query->whereIn('status', Opportunity::getActiveStatuses());

        $opportunities = $query->get();
        
        $statuses = Opportunity::getActiveStatuses();
        $statusLabels = Opportunity::statuses();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        return view('opportunities.kanban', compact('opportunities', 'statuses', 'statusLabels', 'agents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $statuses = Opportunity::statuses();
        $transactionTypes = TransactionType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $typologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $conditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();
        $districts = Local::getDistricts();
        $clients = Client::orderBy('name')->get();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        // Se vier de uma lead, pré-preencher dados
        $lead = null;
        $prefilledData = [];
        $preference = null;
        
        if ($request->has('lead_id')) {
            $lead = Lead::with(['propertyPreferences.typologies', 'propertyPreferences.preferenceLocations', 'propertyPreferences.features'])->findOrFail($request->lead_id);
            $preference = $lead->propertyPreferences->first();
            
            // Buscar ou criar cliente baseado na lead
            $client = null;
            if ($lead->email) {
                $client = Client::where('email', $lead->email)->first();
            }
            
            // Buscar transaction_type_id baseado no tipo da lead ou das preferências
            $transactionTypeId = null;
            
            if ($preference && $preference->transaction_type_id) {
                // Usar transaction_type_id das preferências se existir
                $transactionTypeId = $preference->transaction_type_id;
            } else {
                // Fallback para o tipo da lead
                if ($lead->type === Lead::TYPE_COMPRA) {
                    $transactionType = TransactionType::where('slug', 'venda')->first();
                    $transactionTypeId = $transactionType?->id;
                } elseif ($lead->type === Lead::TYPE_ARRENDAMENTO) {
                    $transactionType = TransactionType::where('slug', 'arrendamento')->first();
                    $transactionTypeId = $transactionType?->id;
                }
            }
            
            // Determinar tipo da oportunidade baseado no tipo da lead
            $opportunityType = match($lead->type) {
                Lead::TYPE_COMPRA => Opportunity::TYPE_COMPRA,
                Lead::TYPE_ARRENDAMENTO => Opportunity::TYPE_ARRENDAMENTO,
                Lead::TYPE_ANGARIACAO => Opportunity::TYPE_ANGARIACAO,
                default => Opportunity::TYPE_COMPRA,
            };
            
            $prefilledData = [
                'client_id' => $client?->id,
                'agent_id' => $lead->agent_id,
                'transaction_type_id' => $transactionTypeId,
                'type' => $opportunityType,
                'priority' => $lead->priority ?? Opportunity::PRIORITY_MEDIUM,
                'notes' => $lead->notes,
            ];
            
        }

        $types = Opportunity::types();
        $priorities = Opportunity::priorities();

        return view('opportunities.create', compact('statuses', 'transactionTypes', 'propertyTypes', 'propertyTypologies', 'typologies', 'features', 'conditions', 'districts', 'clients', 'agents', 'lead', 'preference', 'prefilledData', 'types', 'priorities'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOpportunityRequest $request)
    {
        $validated = $request->validated();

        // Gerar referência automaticamente se não fornecida
        if (!isset($validated['reference']) || empty($validated['reference'])) {
            $validated['reference'] = Opportunity::generateReference();
        }

        // Se vier de uma lead (via query string), usar essa lead
        // Caso contrário, criar uma lead automaticamente
        if ($request->has('lead_id')) {
            $lead = Lead::findOrFail($request->lead_id);
            $validated['lead_id'] = $lead->id;
            
            // Atualizar status da lead para "Ganho" quando convertida em oportunidade
            if ($lead->status !== Lead::STATUS_GANHO) {
                $lead->update([
                    'status' => Lead::STATUS_GANHO,
                    'status_changed_at' => now(),
                ]);
            }
        } else {
            // Criar uma lead automaticamente para registro
            $client = Client::findOrFail($validated['client_id']);
            
            // Determinar tipo da lead baseado no tipo da oportunidade
            $leadType = match($validated['type']) {
                Opportunity::TYPE_COMPRA => Lead::TYPE_COMPRA,
                Opportunity::TYPE_ARRENDAMENTO => Lead::TYPE_ARRENDAMENTO,
                Opportunity::TYPE_ANGARIACAO => Lead::TYPE_ANGARIACAO,
                default => Lead::TYPE_COMPRA,
            };
            
            $lead = Lead::create([
                'type' => $leadType,
                'name' => $client->name,
                'origin' => 'sistema',
                'email' => $client->email,
                'phone' => $client->phone,
                'priority' => $validated['priority'] ?? Lead::PRIORITY_MEDIUM,
                'status' => Lead::STATUS_GANHO, // Lead convertida imediatamente em oportunidade
                'agent_id' => $validated['agent_id'] ?? null,
                'notes' => 'Lead criada automaticamente a partir da oportunidade: ' . $validated['reference'],
                'status_changed_at' => now(),
            ]);

            $validated['lead_id'] = $lead->id;
        }

        $validated['status_changed_at'] = now();

        $opportunity = Opportunity::create($validated);

        // Criar preferências da oportunidade
        if ($request->filled('preference_property_type_id') && $request->filled('preference_transaction_type_id')) {
            // Criar preferência a partir do formulário
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
        } elseif ($request->has('lead_id')) {
            // Copiar TODAS as preferências da lead para a oportunidade
            $leadPreference = $lead->propertyPreferences()->with(['typologies', 'preferenceLocations', 'features'])->first();
            
            if ($leadPreference) {
                $preference = $opportunity->propertyPreferences()->create([
                    'property_type_id' => $leadPreference->property_type_id,
                    'transaction_type_id' => $leadPreference->transaction_type_id,
                    'property_condition_id' => $leadPreference->property_condition_id,
                    'min_price' => $leadPreference->min_price,
                    'max_price' => $leadPreference->max_price,
                    'notes' => $leadPreference->notes,
                    'is_active' => true,
                ]);

                // Copiar todas as localizações
                foreach ($leadPreference->preferenceLocations as $location) {
                    $preference->preferenceLocations()->create([
                        'id_district' => $location->id_district,
                        'id_city' => $location->id_city,
                        'id_parish' => $location->id_parish,
                    ]);
                }

                // Copiar todas as tipologias
                $preference->typologies()->sync($leadPreference->typologies->pluck('id')->toArray());

                // Copiar todas as características
                $preference->features()->sync($leadPreference->features->pluck('id')->toArray());
            }
        }

        // Log status inicial
        $opportunity->statusLogs()->create([
            'old_status' => null,
            'new_status' => $opportunity->status,
            'changed_by' => auth()->id(),
            'notes' => 'Oportunidade criada',
            'created_at' => now(),
        ]);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Oportunidade criada com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Opportunity $opportunity)
    {
        $opportunity->load([
            'client', 
            'lead', 
            'agent', 
            'properties.mainImage', 
            'visits.property.mainImage', 
            'proposals.property.mainImage', 
            'proposals.counterProposals', 
            'proposals.parentProposal', 
            'statusLogs.changedBy', 
            'propertyPreferences.typologies', 
            'propertyPreferences.preferenceLocations', 
            'propertyPreferences.features', 
            'propertyPreferences.transactionType', 
            'propertyPreferences.propertyType', 
            'propertyPreferences.propertyCondition', 
            'notes.user',
            'deal.agentCommissions.agent',
            'deal.closedBy',
            'deal.revertedBy',
        ]);
        
        $statuses = Opportunity::statuses();
        $transactionTypes = Opportunity::transactionTypes();
        
        // Buscar imóveis cruzados (sugeridos) - já carrega mainImage no modelo
        $crossedProperties = $opportunity->getCrossedProperties();
        
        // Imóveis associados - garantir que carrega mainImage e relacionamentos
        $associatedProperties = $opportunity->properties()->with('mainImage', 'transactionType', 'propertyType', 'propertyTypology')->get();

        return view('opportunities.show', compact(
            'opportunity', 
            'statuses', 
            'transactionTypes',
            'crossedProperties',
            'associatedProperties'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Opportunity $opportunity)
    {
        $opportunity->load('client', 'lead', 'agent', 'propertyPreferences.typologies', 'propertyPreferences.preferenceLocations', 'propertyPreferences.features');
        
        $statuses = Opportunity::statuses();
        $transactionTypes = TransactionType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $typologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $conditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();
        $districts = Local::getDistricts();
        $clients = Client::orderBy('name')->get();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        // Buscar preferência existente
        $preference = $opportunity->propertyPreferences->first();

        $types = Opportunity::types();
        $priorities = Opportunity::priorities();

        return view('opportunities.edit', compact(
            'opportunity', 
            'statuses', 
            'transactionTypes', 
            'propertyTypes',
            'propertyTypologies',
            'typologies',
            'features',
            'conditions',
            'districts',
            'clients',
            'agents',
            'preference',
            'types',
            'priorities'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity)
    {
        $validated = $request->validated();

        // Remover lead_id do validated - não pode ser alterado
        unset($validated['lead_id']);

        // Se o status mudou, logar a mudança e atualizar todos os campos
        if ($opportunity->status !== $validated['status']) {
            $opportunity->updateStatus(
                $validated['status'],
                auth()->id(),
                $request->input('status_change_notes')
            );
            // Atualizar os outros campos também (sem lead_id)
            $opportunity->update($validated);
        } else {
            // Atualizar todos os campos normalmente (sem lead_id)
            $opportunity->update($validated);
        }

        // Atualizar ou criar preferências
        if ($request->filled('preference_property_type_id') && $request->filled('preference_transaction_type_id')) {
            $preference = $opportunity->propertyPreferences()->first();
            
            if ($preference) {
                // Atualizar preferência existente
                $preference->update([
                    'property_type_id' => $request->preference_property_type_id,
                    'transaction_type_id' => $request->preference_transaction_type_id,
                    'property_condition_id' => $request->preference_property_condition_id ?? null,
                    'min_price' => $request->preference_min_price ?? null,
                    'max_price' => $request->preference_max_price ?? null,
                    'notes' => $request->preference_notes ?? null,
                ]);

                // Remover localizações antigas e criar novas
                $preference->preferenceLocations()->delete();
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

                // Sincronizar tipologias e características
                if ($request->filled('preference_typologies')) {
                    $preference->typologies()->sync($request->preference_typologies);
                } else {
                    $preference->typologies()->sync([]);
                }

                if ($request->filled('preference_features')) {
                    $preference->features()->sync($request->preference_features);
                } else {
                    $preference->features()->sync([]);
                }
            } else {
                // Criar nova preferência
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

                // Sincronizar tipologias e características
                if ($request->filled('preference_typologies')) {
                    $preference->typologies()->sync($request->preference_typologies);
                }

                if ($request->filled('preference_features')) {
                    $preference->features()->sync($request->preference_features);
                }
            }
        } else {
            // Se não há dados de preferência, remover preferências existentes
            $opportunity->propertyPreferences()->delete();
        }

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Oportunidade atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Opportunity $opportunity)
    {
        $opportunity->delete();

        return redirect()->route('opportunities.index')
            ->with('success', 'Oportunidade removida com sucesso.');
    }

    /**
     * Update opportunity status (via drag and drop).
     */
    public function updateStatus(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Opportunity::statuses()))],
            'notes' => ['nullable', 'string'],
        ]);

        $opportunity->updateStatus(
            $validated['status'],
            auth()->id(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Estado atualizado com sucesso.',
            'opportunity' => $opportunity->load('client', 'agent'),
        ]);
    }

    /**
     * Get crossed properties (suggested properties based on criteria).
     */
    public function getCrossedProperties(Opportunity $opportunity)
    {
        $properties = $opportunity->getCrossedProperties();

        return response()->json([
            'success' => true,
            'properties' => $properties->load('mainImage', 'transactionType', 'propertyType', 'propertyTypology'),
        ]);
    }

    /**
     * Attach a property to the opportunity.
     */
    public function attachProperty(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        // Verificar se já está associado
        if ($opportunity->properties()->where('property_id', $property->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Este imóvel já está associado à oportunidade.',
            ], 400);
        }

        $opportunity->attachProperty($property, $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Imóvel associado com sucesso.',
            'property' => $property->load('mainImage'),
        ]);
    }

    /**
     * Detach a property from the opportunity.
     */
    public function detachProperty(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        $opportunity->detachProperty($property);

        return response()->json([
            'success' => true,
            'message' => 'Imóvel desassociado com sucesso.',
        ]);
    }

    /**
     * Archive the specified opportunity.
     */
    public function archive(Opportunity $opportunity)
    {
        $opportunity->archive();

        return response()->json([
            'success' => true,
            'message' => 'Oportunidade arquivada com sucesso.',
        ]);
    }

    /**
     * Restore (unarchive) the specified opportunity.
     */
    public function restore(Opportunity $opportunity)
    {
        $opportunity->unarchive();

        return response()->json([
            'success' => true,
            'message' => 'Oportunidade desarquivada com sucesso.',
        ]);
    }

    /**
     * Store a note for the opportunity.
     */
    public function storeNote(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $opportunity->notes()->create([
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
}
