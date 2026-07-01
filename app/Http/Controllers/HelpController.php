<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('ajuda.agenda');
    }

    public function agenda(): View
    {
        return view('ajuda.agenda', [
            'pageTitle' => 'Agenda',
        ]);
    }
}
