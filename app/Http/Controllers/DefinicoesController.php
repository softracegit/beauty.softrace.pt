<?php

namespace App\Http\Controllers;

use App\Models\CrmSetting;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DefinicoesController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('definicoes.conta');
    }

    public function conta(): View
    {
        return view('definicoes.conta', [
            'pageTitle' => 'Conta',
        ]);
    }

    public function updateConta(): RedirectResponse
    {
        return redirect()
            ->route('definicoes.conta')
            ->with('status', 'Preferências da conta guardadas.');
    }

    public function negocio(): View
    {
        return view('definicoes.negocio', [
            'pageTitle' => 'Negócio',
        ]);
    }

    public function agendamentos(): View
    {
        return view('definicoes.agendamentos', [
            'pageTitle' => 'Agendamentos',
            'bookingSlotHoldMinutes' => CrmSetting::bookingSlotHoldMinutes(),
        ]);
    }

    public function updateAgendamentos(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'booking_slot_hold_minutes' => ['required', 'integer', 'min:1', 'max:240'],
        ], [
            'booking_slot_hold_minutes.min' => 'O tempo de reserva deve ser pelo menos 1 minuto.',
            'booking_slot_hold_minutes.max' => 'O tempo de reserva não pode exceder 240 minutos.',
        ]);

        CrmSetting::setInt(
            CrmSetting::KEY_BOOKING_SLOT_HOLD_MINUTES,
            (int) $validated['booking_slot_hold_minutes']
        );

        return redirect()
            ->route('definicoes.agendamentos')
            ->with('status', 'Definições de agendamentos guardadas.');
    }

    public function vendas(): View
    {
        return view('definicoes.vendas', [
            'pageTitle' => 'Vendas',
        ]);
    }

    public function clientes(): View
    {
        return view('definicoes.clientes', [
            'pageTitle' => 'Clientes',
        ]);
    }

    public function equipa(): View
    {
        return view('definicoes.equipa', [
            'pageTitle' => 'Equipa',
        ]);
    }

    public function pagamentos(): View
    {
        return view('definicoes.pagamentos', [
            'pageTitle' => 'Pagamentos',
            'onlineBookingPaymentRequired' => CrmSetting::onlineBookingPaymentRequired(),
        ]);
    }

    public function updatePagamentos(Request $request): RedirectResponse
    {
        CrmSetting::setBool(
            CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED,
            $request->boolean('online_booking_payment_required'),
        );

        return redirect()
            ->route('definicoes.pagamentos')
            ->with('status', 'Definições de pagamento guardadas.');
    }

    public function notificacoes(): View
    {
        $user = auth()->user();
        $meta = UserNotificationPreference::marcacaoTypesMeta();
        $saved = $user->notificationPreferences()
            ->whereIn('category', UserNotificationPreference::MARCACAO_NOTIFICATION_KEYS)
            ->get()
            ->keyBy('category');

        $matrix = [];
        foreach (UserNotificationPreference::MARCACAO_NOTIFICATION_KEYS as $key) {
            $row = $saved->get($key);
            $matrix[$key] = [
                'label' => $meta[$key]['label'],
                'description' => $meta[$key]['description'],
                'bell' => $row ? $row->bell_enabled : true,
                'email' => $row ? $row->email_enabled : true,
            ];
        }

        return view('definicoes.notificacoes', [
            'pageTitle' => 'Notificações',
            'matrix' => $matrix,
        ]);
    }

    public function updateNotificacoes(Request $request): RedirectResponse
    {
        $user = auth()->user();

        /** @var array<string, mixed> $bellInput */
        $bellInput = $request->input('bell', []);
        /** @var array<string, mixed> $emailInput */
        $emailInput = $request->input('email', []);

        foreach (UserNotificationPreference::MARCACAO_NOTIFICATION_KEYS as $key) {
            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category' => $key,
                ],
                [
                    'bell_enabled' => self::notificationToggleFromInput($bellInput[$key] ?? null),
                    'email_enabled' => self::notificationToggleFromInput($emailInput[$key] ?? null),
                ]
            );
        }

        return redirect()
            ->route('definicoes.notificacoes')
            ->with('status', 'Preferências de notificação guardadas.');
    }

    /**
     * Checkbox sem hidden: ausente = desligado. Evita ambiguidade de hidden+checkbox com o mesmo nome.
     */
    private static function notificationToggleFromInput(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return in_array('1', $value, true)
                || in_array(1, $value, true)
                || in_array(true, $value, true);
        }

        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
