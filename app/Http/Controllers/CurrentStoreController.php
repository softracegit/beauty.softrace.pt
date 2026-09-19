<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Support\StoreContextPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentStoreController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);

        $store = Store::query()->findOrFail($validated['store_id']);
        $this->authorize('switchTo', $store);

        StoreContextPreference::persist($request, (int) $store->id);

        $redirect = $request->input('redirect');
        if (is_string($redirect) && $redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect()->to($redirect);
        }

        return redirect()->back();
    }
}
