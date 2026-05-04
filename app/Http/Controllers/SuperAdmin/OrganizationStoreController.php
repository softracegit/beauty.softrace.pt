<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationStoreController extends Controller
{
    public function create(Organization $organization): View
    {
        $this->authorize('createStore', $organization);

        return view('super-admin.stores.create', compact('organization'));
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('createStore', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'slug')],
            'timezone' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ]);

        $slug = $validated['slug'] ?? null;
        if ($slug === null || trim((string) $slug) === '') {
            $slug = $this->uniqueStoreSlug(Str::slug($validated['name']));
        }

        $store = $organization->stores()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'timezone' => $validated['timezone'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        $this->seedDefaultCrmSettingsForStore($store);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Loja criada.');
    }

    public function edit(Organization $organization, Store $store): View
    {
        $this->authorize('updateStore', [$organization, $store]);

        return view('super-admin.stores.edit', compact('organization', 'store'));
    }

    public function update(Request $request, Organization $organization, Store $store): RedirectResponse
    {
        $this->authorize('updateStore', [$organization, $store]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store->getKey())],
            'timezone' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ]);

        $store->update($validated);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Loja actualizada.');
    }

    public function destroy(Organization $organization, Store $store): RedirectResponse
    {
        $this->authorize('deleteStore', [$organization, $store]);

        $labels = [];
        if ($store->agents()->exists()) {
            $labels[] = 'equipa';
        }
        if ($store->categories()->exists()) {
            $labels[] = 'categorias de serviços';
        }
        if ($store->services()->exists()) {
            $labels[] = 'serviços';
        }
        if ($store->extraCategories()->exists()) {
            $labels[] = 'categorias de extras';
        }
        if ($store->clients()->exists()) {
            $labels[] = 'clientes';
        }
        if ($store->calendarEvents()->exists()) {
            $labels[] = 'eventos na agenda';
        }
        if ($store->bookings()->exists()) {
            $labels[] = 'marcações online (checkout)';
        }
        if ($store->sales()->exists()) {
            $labels[] = 'vendas';
        }
        if ($store->personalTimeTypes()->exists()) {
            $labels[] = 'tipos de tempo pessoal';
        }
        if ($store->bookingSlotHolds()->exists()) {
            $labels[] = 'reservas temporárias de horário';
        }
        if ($store->bookingAuthCodes()->exists()) {
            $labels[] = 'códigos de autenticação de marcação';
        }

        if ($labels !== []) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível eliminar: a loja ainda tem '.implode(', ', $labels).'.');
        }

        DB::transaction(function () use ($store): void {
            $store->crmSettings()->delete();
            $store->delete();
        });

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Loja eliminada.');
    }

    private function uniqueStoreSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'loja';
        $candidate = $slug;
        $n = 0;
        while (Store::query()->where('slug', $candidate)->exists()) {
            $n++;
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }

    private function seedDefaultCrmSettingsForStore(Store $store): void
    {
        $defaults = [
            CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED => '1',
            CrmSetting::KEY_BOOKING_SLOT_HOLD_MINUTES => '6',
            CrmSetting::KEY_BOOKING_ANY_STAFF_RULE => CrmSetting::BOOKING_ANY_STAFF_RULE_A,
        ];

        foreach ($defaults as $key => $value) {
            CrmSetting::query()->updateOrCreate(
                ['store_id' => $store->id, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
