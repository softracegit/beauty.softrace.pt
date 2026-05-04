<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('stores')
            ->orderBy('name')
            ->paginate(20);

        return view('super-admin.organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        return view('super-admin.organizations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'nif' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $slug = $validated['slug'] ?? null;
        if ($slug === null || trim((string) $slug) === '') {
            $slug = $this->uniqueOrganizationSlug(Str::slug($validated['name']));
        }

        Organization::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'nif' => $validated['nif'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('super-admin.organizations.index')
            ->with('success', 'Organização criada.');
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $organization->load(['stores' => fn ($q) => $q->orderBy('name')]);

        return view('super-admin.organizations.show', compact('organization'));
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('super-admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug,'.$organization->getKey()],
            'nif' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $slug = $validated['slug'] ?? null;
        if ($slug === null || trim((string) $slug) === '') {
            $slug = $this->uniqueOrganizationSlug(Str::slug($validated['name']), $organization->getKey());
        }

        $organization->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'nif' => $validated['nif'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Organização actualizada.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        if ($organization->stores()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível eliminar: existem lojas associadas. Suspenda a organização ou elimine as lojas primeiro.');
        }

        $organization->delete();

        return redirect()
            ->route('super-admin.organizations.index')
            ->with('success', 'Organização eliminada.');
    }

    private function uniqueOrganizationSlug(string $base, ?int $ignoreOrgId = null): string
    {
        $slug = $base !== '' ? $base : 'organizacao';
        $candidate = $slug;
        $n = 0;
        while (Organization::query()
            ->when($ignoreOrgId !== null, fn ($q) => $q->where('id', '!=', $ignoreOrgId))
            ->where('slug', $candidate)
            ->exists()) {
            $n++;
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }
}
