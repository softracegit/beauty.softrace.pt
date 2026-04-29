<?php

namespace App\Http\Controllers;

use App\Services\TwilioSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingSmsController extends Controller
{
    public function __construct(
        private readonly TwilioSmsService $twilioSms
    ) {}

    public function index(): View
    {
        return view('marketing.campanhas-sms', [
            'pageTitle' => 'Campanhas SMS — Teste',
            'twilioConfigured' => $this->twilioSms->isConfigured(),
            'smsFrom' => config('services.twilio.sms_from'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'phone.required' => 'Indique o número de destino.',
            'message.required' => 'Indique o texto da mensagem.',
        ]);

        if (! $this->twilioSms->isConfigured()) {
            return redirect()
                ->route('marketing.campanhas-sms')
                ->with('error', 'Twilio não está configurado. Verifique TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN e TWILIO_SMS_FROM no .env.');
        }

        try {
            $result = $this->twilioSms->send($validated['phone'], $validated['message']);
            $sid = $result['sid'] !== '' ? $result['sid'] : '—';
            $status = $result['status'] ?? '—';

            return redirect()
                ->route('marketing.campanhas-sms')
                ->with('success', 'SMS enviado. SID: '.$sid.' · Estado: '.$status);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('marketing.campanhas-sms')
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('marketing.campanhas-sms')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
