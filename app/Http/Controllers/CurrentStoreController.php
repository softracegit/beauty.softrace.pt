<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Store;
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

        $request->session()->put(SetCurrentStore::SESSION_KEY, $store->id);

        return redirect()->back();
    }
}
