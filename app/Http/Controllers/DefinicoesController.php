<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function marcacoes(): View
    {
        $storeId = current_store_id();

        return view('definicoes.agendamentos', [
            'pageTitle' => 'Marcações',
            'bookingSlotHoldMinutes' => CrmSetting::bookingSlotHoldMinutes($storeId),
            'bookingAnyStaffRules' => CrmSetting::bookingAnyStaffRulesUi(),
            'bookingAnyStaffRule' => CrmSetting::bookingAnyStaffRule($storeId),
        ]);
    }

    public function updateMarcacoes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'booking_slot_hold_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'booking_any_staff_rule' => ['nullable', 'string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
            'booking_any_staff_rule_options' => ['nullable', 'array', 'min:1', 'max:1'],
            'booking_any_staff_rule_options.*' => ['string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
        ], [
            'booking_slot_hold_minutes.min' => 'O tempo de reserva deve ser pelo menos 1 minuto.',
            'booking_slot_hold_minutes.max' => 'O tempo de reserva não pode exceder 240 minutos.',
            'booking_any_staff_rule_options.max' => 'Selecione apenas uma regra de atribuição.',
        ]);

        $selectedRule = null;
        if (! empty($validated['booking_any_staff_rule_options']) && is_array($validated['booking_any_staff_rule_options'])) {
            $selectedRule = (string) ($validated['booking_any_staff_rule_options'][0] ?? '');
        } elseif (! empty($validated['booking_any_staff_rule'])) {
            $selectedRule = (string) $validated['booking_any_staff_rule'];
        }
        $storeId = current_store_id();

        if ($selectedRule === null || $selectedRule === '') {
            $selectedRule = CrmSetting::bookingAnyStaffRule($storeId);
        }

        CrmSetting::setInt(
            CrmSetting::KEY_BOOKING_SLOT_HOLD_MINUTES,
            (int) $validated['booking_slot_hold_minutes'],
            $storeId
        );
        CrmSetting::setString(
            CrmSetting::KEY_BOOKING_ANY_STAFF_RULE,
            $selectedRule,
            $storeId
        );

        return redirect()
            ->route('definicoes.marcacoes')
            ->with('status', 'Definições de marcações guardadas.');
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
        $agents = Agent::query()
            ->where('store_id', current_store_id())
            ->with('user:id,name,role')
            ->whereHas('user')
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        return view('definicoes.equipa', [
            'pageTitle' => 'Equipa',
            'agents' => $agents,
            'roles' => User::roles(),
        ]);
    }

    public function updateEquipa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.role' => ['required', 'string', 'in:'.implode(',', array_keys(User::roles()))],
            'members.*.visible_in_agenda' => ['nullable', 'boolean'],
            'members.*.visible_in_booking' => ['nullable', 'boolean'],
            'members.*.agenda_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $rows = $validated['members'] ?? [];
        foreach ($rows as $agentId => $row) {
            $isVisibleInAgenda = (bool) ($row['visible_in_agenda'] ?? false);
            $agendaOrder = $row['agenda_order'] ?? null;
            if ($isVisibleInAgenda && ($agendaOrder === null || $agendaOrder === '')) {
                return redirect()
                    ->route('definicoes.equipa')
                    ->withErrors([
                        "members.$agentId.agenda_order" => 'Indique a ordem na agenda para membros visíveis na agenda.',
                    ])
                    ->withInput();
            }
        }

        $agentIds = array_map('intval', array_keys($rows));
        if ($agentIds === []) {
            return redirect()->route('definicoes.equipa')->with('status', 'Nada para atualizar.');
        }

        $agents = Agent::query()
            ->where('store_id', current_store_id())
            ->with('user:id,role')
            ->whereIn('id', $agentIds)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($rows, $agents): void {
            foreach ($rows as $agentId => $row) {
                $agentIdInt = (int) $agentId;
                $agent = $agents->get($agentIdInt);
                if (! $agent || ! $agent->user) {
                    continue;
                }

                $role = (string) ($row['role'] ?? $agent->user->role);
                if ($agent->user->role !== $role) {
                    $agent->user->role = $role;
                    $agent->user->save();
                }

                $agent->update([
                    'visible_in_agenda' => (bool) ($row['visible_in_agenda'] ?? false),
                    'visible_in_booking' => (bool) ($row['visible_in_booking'] ?? false),
                    'agenda_order' => (bool) ($row['visible_in_agenda'] ?? false)
                        ? (int) ($row['agenda_order'] ?? 0)
                        : 0,
                ]);
            }
        });

        return redirect()
            ->route('definicoes.equipa')
            ->with('status', 'Configuração da equipa atualizada.');
    }

    public function pagamentos(): View
    {
        $storeId = current_store_id();

        return view('definicoes.pagamentos', [
            'pageTitle' => 'Pagamentos',
            'onlineBookingPaymentRequired' => CrmSetting::onlineBookingPaymentRequired($storeId),
        ]);
    }

    public function updatePagamentos(Request $request): RedirectResponse
    {
        CrmSetting::setBool(
            CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED,
            $request->boolean('online_booking_payment_required'),
            current_store_id(),
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
