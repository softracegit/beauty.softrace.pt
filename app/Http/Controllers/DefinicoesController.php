<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\BookingTheme;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DefinicoesController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('definicoes.negocio');
    }

    public function negocio(): View
    {
        $store = app(CurrentStore::class)->get();

        return view('definicoes.negocio', [
            'pageTitle' => 'Negócio',
            'store' => $store,
            'weeklySchedule' => old('weekly_schedule', $store->normalizedWeeklySchedule()),
        ]);
    }

    public function updateNegocio(Request $request): RedirectResponse
    {
        $store = app(CurrentStore::class)->get();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'maps_url' => ['nullable', 'url', 'max:512'],
            'website_url' => ['nullable', 'url', 'max:512'],
            'instagram_url' => ['nullable', 'url', 'max:512'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'maps_url.url' => 'O link do mapa deve ser um URL válido.',
            'website_url.url' => 'O site deve ser um URL válido.',
            'instagram_url.url' => 'O Instagram deve ser um URL válido.',
        ]);

        $logoPath = $store->logo;
        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }
        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoDir = $store->logoStorageDirectory();
            Storage::disk('public')->makeDirectory($logoDir);
            $logoPath = $request->file('logo')->store($logoDir, 'public');
        }

        $store->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'maps_url' => $validated['maps_url'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'instagram_url' => $validated['instagram_url'] ?? null,
            'logo' => $logoPath,
            'weekly_schedule' => $this->validatedWeeklySchedule($request),
        ]);

        return redirect()
            ->route('definicoes.negocio')
            ->with('status', 'Dados do negócio guardados.');
    }

    public function marcacoes(): View
    {
        $storeId = current_store_id();

        return view('definicoes.agendamentos', [
            'pageTitle' => 'Marcações',
            'bookingSlotHoldMinutes' => CrmSetting::bookingSlotHoldMinutes($storeId),
            'bookingCancellationNoticeHours' => CrmSetting::bookingCancellationNoticeHours($storeId),
            'bookingAnyStaffRules' => CrmSetting::bookingAnyStaffRulesUi(),
            'bookingAnyStaffRule' => CrmSetting::bookingAnyStaffRule($storeId),
            'bookingTheme' => CrmSetting::bookingTheme($storeId),
            'bookingThemes' => BookingTheme::registry(),
        ]);
    }

    public function updateMarcacoes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'booking_slot_hold_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'booking_cancellation_notice_hours' => [
                'required',
                'integer',
                'min:'.CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MIN,
                'max:'.CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MAX,
            ],
            'booking_any_staff_rule' => ['nullable', 'string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
            'booking_any_staff_rule_options' => ['nullable', 'array', 'min:1', 'max:1'],
            'booking_any_staff_rule_options.*' => ['string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
            'booking_theme' => ['nullable', 'string', 'in:'.implode(',', array_keys(BookingTheme::registry()))],
        ], [
            'booking_slot_hold_minutes.min' => 'O tempo de reserva deve ser pelo menos 1 minuto.',
            'booking_slot_hold_minutes.max' => 'O tempo de reserva não pode exceder 240 minutos.',
            'booking_cancellation_notice_hours.min' => 'O aviso mínimo não pode ser negativo.',
            'booking_cancellation_notice_hours.max' => 'O aviso mínimo não pode exceder 168 horas (7 dias).',
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
        CrmSetting::setBookingCancellationNoticeHours(
            (int) $validated['booking_cancellation_notice_hours'],
            $storeId,
        );
        CrmSetting::setString(
            CrmSetting::KEY_BOOKING_ANY_STAFF_RULE,
            $selectedRule,
            $storeId
        );

        if (array_key_exists('booking_theme', $validated)) {
            CrmSetting::setBookingTheme((string) $validated['booking_theme'], $storeId);
        }

        return redirect()
            ->route('definicoes.marcacoes')
            ->with('status', 'Definições de marcações guardadas.');
    }

    public function equipa(): View
    {
        $agents = Agent::query()
            ->activeTeamMembers(current_store_id())
            ->with('user:id,name,role')
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
            ->activeTeamMembers(current_store_id())
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
            'posGorjetaEnabled' => CrmSetting::posGorjetaEnabled($storeId),
        ]);
    }

    public function updatePagamentos(Request $request): RedirectResponse
    {
        CrmSetting::setBool(
            CrmSetting::KEY_POS_GORJETA_ENABLED,
            $request->boolean('pos_gorjeta_enabled'),
            current_store_id(),
        );
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

    /**
     * @return array<string, array{enabled: bool, start: ?string, end: ?string}>
     */
    private function validatedWeeklySchedule(Request $request): array
    {
        $raw = $request->input('weekly_schedule');
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'weekly_schedule' => 'Indique o horário da loja.',
            ]);
        }

        $timePattern = '/^([01]\d|2[0-3]):(00|15|30|45)$/';
        $out = [];

        foreach (Agent::WEEKDAY_KEYS as $day) {
            $dayIn = $raw[$day] ?? [];
            $enabled = filter_var($dayIn['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $enabled) {
                $out[$day] = ['enabled' => false, 'start' => null, 'end' => null];

                continue;
            }
            $start = $dayIn['start'] ?? '09:00';
            $end = $dayIn['end'] ?? '20:00';
            if (! is_string($start) || ! is_string($end) || ! preg_match($timePattern, $start) || ! preg_match($timePattern, $end)) {
                throw ValidationException::withMessages([
                    "weekly_schedule.{$day}" => 'Horário inválido. Use intervalos de 15 minutos (00:00–23:45).',
                ]);
            }
            $smin = Agent::timeStringToMinutes($start);
            $emin = Agent::timeStringToMinutes($end);
            if ($smin >= $emin) {
                throw ValidationException::withMessages([
                    "weekly_schedule.{$day}" => 'A hora de início deve ser anterior à hora de fim.',
                ]);
            }
            $out[$day] = ['enabled' => true, 'start' => $start, 'end' => $end];
        }

        return $out;
    }
}
