<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BookingPasswordController extends Controller
{
    public function edit(): View
    {
        return view('booking.conta.password', [
            'businessName' => config('app.name'),
            'mustSetPassword' => (bool) auth()->user()?->must_set_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
        if ($user->must_set_password) {
            $rules['current_password'] = ['nullable', 'string'];
        } else {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validate($rules);

        $user->password = $validated['password'];
        $user->must_set_password = false;
        $user->save();

        return redirect()->route('booking.step3')
            ->with('status', 'Password atualizada. Nas próximas vezes podes iniciar sessão em '.route('login').' com email e password.');
    }
}
