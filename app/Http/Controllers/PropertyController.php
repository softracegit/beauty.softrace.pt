<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Models\Local;
use App\Models\TransactionType;
use App\Models\PropertyType;
use App\Models\PropertyTypology;
use App\Models\PropertyCondition;
use App\Models\PropertyFeature;
use App\Models\Agent;
use App\Models\Note;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Property::with('mainImage', 'transactionType', 'propertyType', 'propertyTypology')->orderBy('created_at', 'desc');

        // Pesquisa
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transaction_type_id')) {
            $query->where('transaction_type_id', $request->transaction_type_id);
        }

        if ($request->filled('property_typology_id')) {
            $query->where('property_typology_id', $request->property_typology_id);
        }

        if ($request->filled('id_district')) {
            $query->where('id_district', $request->id_district);
        }

        if ($request->filled('id_city')) {
            $query->where('id_city', $request->id_city);
        }

        // Filtro de preço
        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        $properties = $query->paginate(15)->withQueryString();

        // Dados para filtros
        $statuses = Property::statuses();
        $transactionTypes = Property::transactionTypes();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $districts = Local::getDistricts();

        return view('properties.index', compact('properties', 'statuses', 'transactionTypes', 'propertyTypes', 'propertyTypologies', 'districts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $statuses = Property::statuses();
        $transactionTypes = Property::transactionTypes();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $propertyConditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();
        $propertyFeatures = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $orientations = Property::orientations();
        $energyCertificates = Property::energyCertificates();
        $districts = Local::getDistricts();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        return view('properties.create', compact('statuses', 'transactionTypes', 'propertyTypes', 'propertyTypologies', 'propertyConditions', 'propertyFeatures', 'orientations', 'energyCertificates', 'districts', 'agents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePropertyRequest $request)
    {
        $validated = $request->validated();

        // Gerar referência automática se não fornecida
        if (empty($validated['reference'])) {
            $validated['reference'] = Property::generateReference();
        }
        
        // Gerar título automático se não fornecido
        if (empty($validated['title'])) {
            $title = 'Imóvel';
            if (!empty($validated['property_typology_id'])) {
                $typology = PropertyTypology::find($validated['property_typology_id']);
                if ($typology) {
                    $title = $typology->name;
                }
            }
            if (!empty($validated['address'])) {
                $title .= ' - ' . $validated['address'];
            }
            $validated['title'] = $title;
        }

        // Calcular commission_value se commission_percentage e price estiverem preenchidos
        if (isset($validated['commission_percentage']) && isset($validated['price']) && $validated['price'] > 0) {
            $validated['commission_value'] = ($validated['price'] * $validated['commission_percentage']) / 100;
        }

        // Separar features do resto dos dados
        $features = $validated['features'] ?? [];
        unset($validated['features']);

        $property = Property::create($validated);

        // Associar features
        if (!empty($features)) {
            $property->features()->sync($features);
        }

        return redirect()->route('properties.show', $property)
            ->with('success', 'Imóvel criado com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Property $property)
    {
        $property->load('images', 'notes.user');
        
        $statuses = Property::statuses();
        $transactionTypes = Property::transactionTypes();
        $orientations = Property::orientations();
        $energyCertificates = Property::energyCertificates();

        return view('properties.show', compact('property', 'statuses', 'transactionTypes', 'orientations', 'energyCertificates'));
    }

    /**
     * Store a note for the property.
     */
    public function storeNote(Request $request, Property $property)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $property->notes()->create([
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
     * Show the form for editing the specified resource.
     */
    public function edit(Property $property)
    {
        $property->load('images', 'transactionType', 'propertyType', 'propertyTypology', 'propertyCondition', 'features');
        
        $statuses = Property::statuses();
        $transactionTypes = Property::transactionTypes();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('sort_order')->get();
        $propertyTypologies = PropertyTypology::where('is_active', true)->orderBy('sort_order')->get();
        $propertyConditions = PropertyCondition::where('is_active', true)->orderBy('sort_order')->get();
        $propertyFeatures = PropertyFeature::where('is_active', true)->orderBy('sort_order')->get();
        $orientations = Property::orientations();
        $energyCertificates = Property::energyCertificates();
        $districts = Local::getDistricts();
        $agents = Agent::where('status', Agent::STATUS_ACTIVE)->orderBy('name')->get();

        // Buscar dados de localização se existirem
        $selectedDistrict = null;
        $selectedCity = null;
        $selectedParish = null;
        $cities = collect();
        $parishes = collect();

        if ($property->id_district) {
            $selectedDistrict = $property->id_district;
            $cities = Local::getCitiesByDistrict($property->id_district);
            
            if ($property->id_city) {
                $selectedCity = $property->id_city;
                $parishes = Local::getParishesByCity($property->id_city);
                
                if ($property->id_parish) {
                    $selectedParish = $property->id_parish;
                }
            }
        }

        return view('properties.edit', compact(
            'property', 
            'statuses', 
            'transactionTypes', 
            'propertyTypes',
            'propertyTypologies',
            'propertyConditions',
            'propertyFeatures',
            'orientations', 
            'energyCertificates', 
            'districts',
            'agents',
            'selectedDistrict',
            'selectedCity',
            'selectedParish',
            'cities',
            'parishes'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePropertyRequest $request, Property $property)
    {
        $validated = $request->validated();

        // Calcular commission_value se commission_percentage e price estiverem preenchidos
        if (isset($validated['commission_percentage']) && isset($validated['price']) && $validated['price'] > 0) {
            $validated['commission_value'] = ($validated['price'] * $validated['commission_percentage']) / 100;
        }

        // Separar features do resto dos dados
        $features = $validated['features'] ?? [];
        unset($validated['features']);

        $property->update($validated);

        // Sincronizar features
        $property->features()->sync($features);

        return redirect()->route('properties.show', $property)
            ->with('success', 'Imóvel atualizado com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Property $property)
    {
        $property->delete();

        return redirect()->route('properties.index')
            ->with('success', 'Imóvel removido com sucesso.');
    }

    /**
     * Get cities (concelhos) by district ID
     */
    public function getCitiesByDistrict(Request $request)
    {
        $districtId = $request->input('district_id');
        
        if (!$districtId) {
            return response()->json([]);
        }

        $cities = Local::getCitiesByDistrict($districtId);
        
        return response()->json($cities);
    }

    /**
     * Get parishes (freguesias) by city ID
     */
    public function getParishesByCity(Request $request)
    {
        $cityId = $request->input('city_id');
        
        if (!$cityId) {
            return response()->json([]);
        }

        $parishes = Local::getParishesByCity($cityId);
        
        return response()->json($parishes);
    }
}
