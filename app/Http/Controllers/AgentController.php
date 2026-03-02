<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    /**
     * Display a listing of the agents.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Agent::class);
        $query = Agent::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nif', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $agents = $query->with('user')->paginate(9)->withQueryString();

        return view('agentes.index', compact('agents'));
    }

    /**
     * Show the form for creating a new agent.
     */
    public function create()
    {
        $this->authorize('create', Agent::class);
        $categories = Category::orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
        return view('agentes.create', compact('categories'));
    }

    /**
     * Store a newly created agent.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Agent::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Agent::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Agent::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'on_leave'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        $agentData = collect($validated)->except(['email', 'password', 'password_confirmation', 'role', 'avatar', 'service_ids'])->all();
        $agentData['user_id'] = $user->id;

        if ($request->hasFile('avatar')) {
            $agentData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $agent = Agent::create($agentData);
        $agent->services()->sync($request->input('service_ids', []));

        return redirect()->route('equipa.index')
            ->with('success', 'Membro criado com sucesso.');
    }

    /**
     * Display the specified agent.
     */
    public function show(Agent $agente)
    {
        $this->authorize('view', $agente);
        
        $agente->load(['notes.user', 'user']);
        return view('agentes.show', compact('agente'));
    }

    /**
     * Store a note for the agent.
     */
    public function storeNote(Request $request, Agent $agente)
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'type' => ['nullable', 'in:geral,email,chamada,reuniao'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_advance_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $agente->notes()->create([
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
     * Show the form for editing the specified agent.
     */
    public function edit(Agent $agente)
    {
        $this->authorize('update', $agente);
        $agente->load('services');
        $categories = Category::orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
        return view('agentes.edit', compact('agente', 'categories'));
    }

    /**
     * Update the specified agent.
     */
    public function update(Request $request, Agent $agente)
    {
        $this->authorize('update', $agente);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($agente->user_id)],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(array_keys(Agent::genders()))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(array_keys(Agent::maritalStatuses()))],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'on_leave'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            if ($agente->avatar && Storage::disk('public')->exists($agente->avatar)) {
                Storage::disk('public')->delete($agente->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        // Update agent data
        $agentData = $validated;
        unset($agentData['email'], $agentData['role'], $agentData['password']);
        $agente->update($agentData);

        // Update user data
        if ($agente->user) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ];
            
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            
            $agente->user->update($userData);
        }

        $agente->services()->sync($request->input('service_ids', []));

        return redirect()->route('equipa.show', $agente)
            ->with('success', 'Membro atualizado com sucesso.');
    }

    /**
     * Remove the specified agent.
     */
    public function destroy(Agent $agente)
    {
        $this->authorize('delete', $agente);
        
        // O user será removido automaticamente devido ao cascadeOnDelete
        $agente->delete();

        return redirect()->route('equipa.index')
            ->with('success', 'Membro removido com sucesso.');
    }
}
