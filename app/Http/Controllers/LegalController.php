<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', $this->legalViewData('Termos e Condições'));
    }

    public function privacy(): View
    {
        return view('legal.privacy', $this->legalViewData('Política de Privacidade'));
    }

    public function cookies(): View
    {
        return view('legal.cookies', $this->legalViewData('Política de Cookies'));
    }

    /**
     * @return array<string, mixed>
     */
    private function legalViewData(string $pageTitle): array
    {
        return [
            'pageTitle' => $pageTitle,
            'companyName' => (string) config('legal.company_name'),
            'companyNif' => trim((string) config('legal.company_nif')),
            'companyAddress' => trim((string) config('legal.company_address')),
            'contactEmail' => (string) config('legal.contact_email'),
            'privacyVersion' => (string) config('legal.privacy_version'),
        ];
    }
}
