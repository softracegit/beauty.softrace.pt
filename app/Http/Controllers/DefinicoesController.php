<?php

namespace App\Http\Controllers;

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
        $user = auth()->user();

        return view('definicoes.conta', [
            'pageTitle' => 'Conta',
            'agendaUseOffcanvasMarcacaoTest' => (bool) ($user->agenda_use_offcanvas_marcacao_test ?? false),
        ]);
    }

    public function updateConta(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validate([
            'agenda_use_offcanvas_marcacao_test' => ['nullable', 'boolean'],
        ]);

        $user->agenda_use_offcanvas_marcacao_test = (bool) ($validated['agenda_use_offcanvas_marcacao_test'] ?? false);
        $user->save();

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
        ]);
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
