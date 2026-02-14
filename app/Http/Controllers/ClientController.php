<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Local;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of the clients.
     */
    public function index(Request $request)
    {
        $query = Client::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nif', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(9)->withQueryString();

        return view('clientes.index', compact('clients'));
    }

    /**
     * Show the form for creating a new client.
     */
    public function create()
    {
        $districts = Local::getDistricts();
        $cities = collect();
        $parishes = collect();

        return view('clientes.create', compact(
            'districts',
            'cities',
            'parishes'
        ));
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['available', 'unavailable', 'active'])],
            'notes' => ['nullable', 'string'],
        ]);

        $cliente = Client::create($validated);

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente criado com sucesso.');
    }

    /**
     * Display the specified client.
     */
    public function show(Client $cliente)
    {
        $cliente->load('notes.user');
        return view('clientes.show', compact('cliente'));
    }

    /**
     * Store a note for the client.
     */
    public function storeNote(Request $request, Client $cliente)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $cliente->notes()->create([
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
     * Show the form for editing the specified client.
     */
    public function edit(Client $cliente)
    {
        $districts = Local::getDistricts();

        // Buscar dados de localização do cliente se existirem
        $selectedDistrict = null;
        $selectedCity = null;
        $selectedParish = null;
        $cities = collect();
        $parishes = collect();

        if ($cliente->id_district) {
            $selectedDistrict = $cliente->id_district;
            $cities = Local::getCitiesByDistrict($cliente->id_district);
            
            if ($cliente->id_city) {
                $selectedCity = $cliente->id_city;
                $parishes = Local::getParishesByCity($cliente->id_city);
                
                if ($cliente->id_parish) {
                    $selectedParish = $cliente->id_parish;
                }
            }
        }

        return view('clientes.edit', compact(
            'cliente',
            'districts',
            'selectedDistrict',
            'selectedCity',
            'selectedParish',
            'cities',
            'parishes'
        ));
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $cliente)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($cliente->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Client::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Client::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['available', 'unavailable', 'active'])],
            'notes' => ['nullable', 'string'],
        ]);

        $cliente->update($validated);

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente removido com sucesso.');
    }

}
