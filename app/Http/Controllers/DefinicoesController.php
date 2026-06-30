<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\StoreSettingsActivityLogger;
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
    public function __construct(
        private readonly StoreSettingsActivityLogger $settingsActivityLogger,
    ) {}

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
            'emailUseBusinessBranding' => CrmSetting::emailUseBusinessBranding((int) $store->id),
        ]);
    }

    public function updateNegocio(Request $request): RedirectResponse
    {
        $store = app(CurrentStore::class)->get();
        $storeId = (int) $store->id;

        $before = [
            'name' => $store->name,
            'email' => $store->email,
            'phone' => $store->phone,
            'address_line' => $store->address_line,
            'city' => $store->city,
            'postal_code' => $store->postal_code,
            'maps_url' => $store->maps_url,
            'website_url' => $store->website_url,
            'instagram_url' => $store->instagram_url,
            'logo' => $store->logo,
            'logo_email' => $store->logo_email,
            'logo_favicon' => $store->logo_favicon,
            'weekly_schedule' => $store->normalizedWeeklySchedule(),
        ];
        $beforeBranding = CrmSetting::emailUseBusinessBranding($storeId);

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
            'logo_email' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'logo_favicon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_logo_email' => ['nullable', 'boolean'],
            'remove_logo_favicon' => ['nullable', 'boolean'],
            'email_use_business_branding' => ['nullable', 'boolean'],
        ], [
            'maps_url.url' => 'O link do mapa deve ser um URL válido.',
            'website_url.url' => 'O site deve ser um URL válido.',
            'instagram_url.url' => 'O Instagram deve ser um URL válido.',
        ]);

        $logoPath = $this->handleStoreLogoField($request, $store, 'logo', 'remove_logo', $store->logo);
        $logoEmailPath = $this->handleStoreLogoField($request, $store, 'logo_email', 'remove_logo_email', $store->logo_email);
        $logoFaviconPath = $this->handleStoreLogoField($request, $store, 'logo_favicon', 'remove_logo_favicon', $store->logo_favicon);

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
            'logo_email' => $logoEmailPath,
            'logo_favicon' => $logoFaviconPath,
            'weekly_schedule' => $this->validatedWeeklySchedule($request),
        ]);

        CrmSetting::setEmailUseBusinessBranding(
            $request->boolean('email_use_business_branding'),
            $storeId,
        );

        $store->refresh();
        $changes = array_filter([
            $this->settingsActivityLogger->logScalarChange('Nome', $before['name'], $store->name),
            $this->settingsActivityLogger->logScalarChange('Email', $before['email'], $store->email),
            $this->settingsActivityLogger->logScalarChange('Telefone', $before['phone'], $store->phone),
            $this->settingsActivityLogger->logScalarChange('Morada', $before['address_line'], $store->address_line),
            $this->settingsActivityLogger->logScalarChange('Cidade', $before['city'], $store->city),
            $this->settingsActivityLogger->logScalarChange('Código postal', $before['postal_code'], $store->postal_code),
            $this->settingsActivityLogger->logScalarChange('Link do mapa', $before['maps_url'], $store->maps_url),
            $this->settingsActivityLogger->logScalarChange('Site', $before['website_url'], $store->website_url),
            $this->settingsActivityLogger->logScalarChange('Instagram', $before['instagram_url'], $store->instagram_url),
            $this->settingsActivityLogger->logBoolChange(
                'Branding nos emails',
                $beforeBranding,
                CrmSetting::emailUseBusinessBranding($storeId),
            ),
        ]);

        if ($before['logo'] !== $logoPath) {
            $changes[] = $logoPath ? 'Logo principal atualizado' : 'Logo principal removido';
        }
        if ($before['logo_email'] !== $logoEmailPath) {
            $changes[] = $logoEmailPath ? 'Logo de email atualizado' : 'Logo de email removido';
        }
        if ($before['logo_favicon'] !== $logoFaviconPath) {
            $changes[] = $logoFaviconPath ? 'Favicon atualizado' : 'Favicon removido';
        }
        if (json_encode($before['weekly_schedule']) !== json_encode($store->normalizedWeeklySchedule())) {
            $changes[] = 'Horário da loja alterado';
        }

        $this->settingsActivityLogger->logSection(
            $store,
            'negocio',
            'Definições do negócio atualizadas',
            array_values($changes),
        );

        return redirect()
            ->route('definicoes.negocio')
            ->with('status', 'Dados do negócio guardados.');
    }

    private function handleStoreLogoField(
        Request $request,
        Store $store,
        string $uploadField,
        string $removeField,
        ?string $currentPath,
    ): ?string {
        $path = $currentPath;
        if ($request->boolean($removeField) && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }
        if ($request->hasFile($uploadField)) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $logoDir = $store->logoStorageDirectory();
            Storage::disk('public')->makeDirectory($logoDir);
            $path = $request->file($uploadField)->store($logoDir, 'public');
        }

        return $path;
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
        $storeId = current_store_id();
        $store = app(CurrentStore::class)->get();
        $before = [
            'slot_hold' => CrmSetting::bookingSlotHoldMinutes($storeId),
            'cancellation_hours' => CrmSetting::bookingCancellationNoticeHours($storeId),
            'any_staff_rule' => CrmSetting::bookingAnyStaffRule($storeId),
            'theme' => CrmSetting::bookingTheme($storeId),
        ];
        $ruleLabels = CrmSetting::bookingAnyStaffRulesUi();
        $themeLabels = collect(BookingTheme::registry())->mapWithKeys(fn (array $meta, string $key) => [
            $key => (string) ($meta['label'] ?? $key),
        ])->all();

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

        $changes = array_filter([
            $this->settingsActivityLogger->logScalarChange(
                'Tempo de reserva (min)',
                $before['slot_hold'],
                CrmSetting::bookingSlotHoldMinutes($storeId),
            ),
            $this->settingsActivityLogger->logScalarChange(
                'Aviso mínimo de cancelamento (h)',
                $before['cancellation_hours'],
                CrmSetting::bookingCancellationNoticeHours($storeId),
            ),
            $this->settingsActivityLogger->logScalarChange(
                'Regra «qualquer técnico»',
                $before['any_staff_rule'],
                CrmSetting::bookingAnyStaffRule($storeId),
                fn (mixed $value) => $ruleLabels[(string) $value] ?? (string) $value,
            ),
            $this->settingsActivityLogger->logScalarChange(
                'Tema da marcação online',
                $before['theme'],
                CrmSetting::bookingTheme($storeId),
                fn (mixed $value) => $themeLabels[(string) $value] ?? (string) $value,
            ),
        ]);

        $this->settingsActivityLogger->logSection(
            $store,
            'marcacoes',
            'Definições de marcações atualizadas',
            array_values($changes),
        );

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

        $store = app(CurrentStore::class)->get();

        DB::transaction(function () use ($rows, $agents, $store): void {
            $changes = [];
            foreach ($rows as $agentId => $row) {
                $agentIdInt = (int) $agentId;
                $agent = $agents->get($agentIdInt);
                if (! $agent || ! $agent->user) {
                    continue;
                }

                $memberChanges = [];
                $role = (string) ($row['role'] ?? $agent->user->role);
                if ($agent->user->role !== $role) {
                    $roleLabels = User::roles();
                    $memberChanges[] = 'Tipo: '.($roleLabels[$agent->user->role] ?? $agent->user->role)
                        .' → '.($roleLabels[$role] ?? $role);
                    $agent->user->role = $role;
                    $agent->user->save();
                }

                $visibleAgenda = (bool) ($row['visible_in_agenda'] ?? false);
                $visibleBooking = (bool) ($row['visible_in_booking'] ?? false);
                $agendaOrder = (bool) ($row['visible_in_agenda'] ?? false)
                    ? (int) ($row['agenda_order'] ?? 0)
                    : 0;

                if ((bool) $agent->visible_in_agenda !== $visibleAgenda) {
                    $memberChanges[] = 'Visível na agenda: '.($agent->visible_in_agenda ? 'Sim' : 'Não')
                        .' → '.($visibleAgenda ? 'Sim' : 'Não');
                }
                if ((bool) $agent->visible_in_booking !== $visibleBooking) {
                    $memberChanges[] = 'Visível na marcação online: '.($agent->visible_in_booking ? 'Sim' : 'Não')
                        .' → '.($visibleBooking ? 'Sim' : 'Não');
                }
                if ((int) $agent->agenda_order !== $agendaOrder) {
                    $memberChanges[] = 'Ordem na agenda: '.$agent->agenda_order.' → '.$agendaOrder;
                }

                if ($memberChanges !== []) {
                    $changes[] = $agent->name.': '.implode('; ', $memberChanges);
                }

                $agent->update([
                    'visible_in_agenda' => $visibleAgenda,
                    'visible_in_booking' => $visibleBooking,
                    'agenda_order' => $agendaOrder,
                ]);
            }

            if ($changes !== []) {
                $this->settingsActivityLogger->logSection(
                    $store,
                    'equipa',
                    'Configuração da equipa atualizada',
                    $changes,
                );
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
        $storeId = current_store_id();
        $store = app(CurrentStore::class)->get();
        $beforeGorjeta = CrmSetting::posGorjetaEnabled($storeId);
        $beforeOnlinePayment = CrmSetting::onlineBookingPaymentRequired($storeId);

        CrmSetting::setBool(
            CrmSetting::KEY_POS_GORJETA_ENABLED,
            $request->boolean('pos_gorjeta_enabled'),
            $storeId,
        );
        CrmSetting::setBool(
            CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED,
            $request->boolean('online_booking_payment_required'),
            $storeId,
        );

        $changes = array_filter([
            $this->settingsActivityLogger->logBoolChange(
                'Gorjeta no POS',
                $beforeGorjeta,
                CrmSetting::posGorjetaEnabled($storeId),
            ),
            $this->settingsActivityLogger->logBoolChange(
                'Pagamento online obrigatório',
                $beforeOnlinePayment,
                CrmSetting::onlineBookingPaymentRequired($storeId),
            ),
        ]);

        $this->settingsActivityLogger->logSection(
            $store,
            'pagamentos',
            'Definições de pagamento atualizadas',
            array_values($changes),
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
