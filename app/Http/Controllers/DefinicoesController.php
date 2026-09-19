<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\ClientTagService;
use App\Services\StoreBusinessSettingsService;
use App\Services\StoreSettingsActivityLogger;
use App\Support\BookingTheme;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DefinicoesController extends Controller
{
    public function __construct(
        private readonly StoreSettingsActivityLogger $settingsActivityLogger,
        private readonly ClientTagService $clientTagService,
        private readonly StoreBusinessSettingsService $storeBusinessSettings,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('definicoes.empresa');
    }

    public function empresa(Request $request): View
    {
        $orgId = current_organization_id();
        $organization = \App\Models\Organization::query()->findOrFail($orgId);
        $data = $this->storeBusinessSettings->viewDataForOrganization($organization, $request->query('tab'));

        return view('definicoes.empresa', array_merge($data, [
            'pageTitle' => 'Empresa',
        ]));
    }

    public function updateEmpresa(Request $request): RedirectResponse
    {
        $orgId = current_organization_id();
        $organization = \App\Models\Organization::query()->findOrFail($orgId);
        $this->storeBusinessSettings->applyToOrganization($request, $organization);

        $tab = $this->storeBusinessSettings->resolveActiveTab($request->input('_active_tab'));

        return redirect()
            ->route('definicoes.empresa', ['tab' => $tab])
            ->with('status', 'Dados da empresa guardados.');
    }

    public function negocio(Request $request): RedirectResponse
    {
        return $this->redirectToCurrentStoreEdit($request->query('tab'));
    }

    public function updateNegocio(): RedirectResponse
    {
        return $this->redirectToCurrentStoreEdit();
    }

    public function emails(): RedirectResponse
    {
        return $this->redirectToCurrentStoreEdit('emails');
    }

    public function updateEmails(): RedirectResponse
    {
        return $this->redirectToCurrentStoreEdit('emails');
    }

    private function redirectToCurrentStoreEdit(?string $tab = null): RedirectResponse
    {
        $store = app(CurrentStore::class)->get();
        $params = ['loja' => $store];
        if (is_string($tab) && $tab !== '') {
            $params['tab'] = $tab;
        }

        return redirect()->route('lojas.edit', $params);
    }

    public function marcacoes(): View
    {
        $storeId = current_store_id();

        return view('definicoes.agendamentos', [
            'pageTitle' => 'Booking',
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
            'personalTimeLimitStoreHours' => CrmSetting::personalTimeLimitStoreHours(current_store_id()),
            'storeHoursLabel' => app(CurrentStore::class)->get()->hoursDisplayLabel(),
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
            'personal_time_limit_store_hours' => ['nullable', 'boolean'],
        ]);

        $storeId = current_store_id();
        $beforePersonalTimeLimit = CrmSetting::personalTimeLimitStoreHours($storeId);
        CrmSetting::setPersonalTimeLimitStoreHours(
            $request->boolean('personal_time_limit_store_hours'),
            $storeId,
        );
        $personalTimeLimitChange = $this->settingsActivityLogger->logBoolChange(
            'Limitar tempo pessoal ao horário da loja',
            $beforePersonalTimeLimit,
            CrmSetting::personalTimeLimitStoreHours($storeId),
        );

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

        DB::transaction(function () use ($rows, $agents, $store, $personalTimeLimitChange): void {
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

            if ($personalTimeLimitChange !== null) {
                $this->settingsActivityLogger->logSection(
                    $store,
                    'equipa',
                    'Tempo pessoal na agenda',
                    [$personalTimeLimitChange],
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
        $secret = \App\Support\StripeCredentials::secretKey($storeId);
        $webhook = \App\Support\StripeCredentials::webhookSecret($storeId);

        return view('definicoes.pagamentos', [
            'pageTitle' => 'Pagamentos',
            'onlineBookingPaymentRequired' => CrmSetting::onlineBookingPaymentRequired($storeId),
            'posGorjetaEnabled' => CrmSetting::posGorjetaEnabled($storeId),
            'stripeEnabled' => \App\Support\StripeCredentials::isEnabled($storeId),
            'stripeReady' => \App\Support\StripeCredentials::isReady($storeId),
            'stripePublishableKey' => \App\Support\StripeCredentials::publishableKey($storeId),
            'stripeSecretMasked' => $secret !== '' ? \App\Support\StripeCredentials::maskSecret($secret) : '',
            'stripeWebhookMasked' => $webhook !== '' ? \App\Support\StripeCredentials::maskSecret($webhook) : '',
            'stripeHasSecret' => $secret !== '',
            'stripeHasWebhook' => $webhook !== '',
            'stripeWebhookUrl' => url('/stripe/webhook'),
            'paymentMethods' => \App\Support\PaymentMethodCatalog::forStore($storeId),
        ]);
    }

    public function updatePagamentos(Request $request): RedirectResponse
    {
        $storeId = current_store_id();
        $store = app(CurrentStore::class)->get();
        $beforeGorjeta = CrmSetting::posGorjetaEnabled($storeId);
        $beforeOnlinePayment = CrmSetting::onlineBookingPaymentRequired($storeId);

        $request->validate([
            'pos_gorjeta_enabled' => ['sometimes', 'boolean'],
            'online_booking_payment_required' => ['sometimes', 'boolean'],
            'methods' => ['nullable', 'array'],
            'methods.*.code' => ['required', 'string'],
            'methods.*.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'methods.*.agenda' => ['sometimes', 'boolean'],
            'methods.*.booking' => ['sometimes', 'boolean'],
        ]);

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

        $methodRows = [];
        foreach ((array) $request->input('methods', []) as $row) {
            if (! is_array($row) || empty($row['code'])) {
                continue;
            }
            $methodRows[] = [
                'code' => (string) $row['code'],
                'sort' => (int) ($row['sort'] ?? 0),
                'agenda' => self::notificationToggleFromInput($row['agenda'] ?? null),
                'booking' => self::notificationToggleFromInput($row['booking'] ?? null),
            ];
        }
        if ($methodRows !== []) {
            \App\Support\PaymentMethodCatalog::saveFromRequest($methodRows, $storeId);
        }

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
            ->with('status', 'Definições de pagamento guardadas (métodos: toda a organização; gorjeta e booking online: loja activa).');
    }

    public function updatePagamentosStripe(Request $request): RedirectResponse
    {
        $storeId = current_store_id();
        $store = app(CurrentStore::class)->get();
        $beforeStripeEnabled = \App\Support\StripeCredentials::isEnabled($storeId);
        $action = (string) $request->input('stripe_action', 'save');

        if ($action === 'disable') {
            \App\Support\StripeCredentials::setEnabled(false, $storeId);

            $this->settingsActivityLogger->logSection(
                $store,
                'pagamentos',
                'Stripe desativado na organização',
                array_values(array_filter([
                    $this->settingsActivityLogger->logBoolChange(
                        'Stripe ativo',
                        $beforeStripeEnabled,
                        false,
                    ),
                ])),
            );

            return redirect()
                ->route('definicoes.pagamentos')
                ->with('status', 'Stripe desativado para toda a organização.');
        }

        $validated = $request->validate([
            'stripe_publishable_key' => ['required', 'string', 'max:255'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'stripe_publishable_key.required' => 'A Publishable key é obrigatória.',
        ]);

        $secretInput = trim((string) ($validated['stripe_secret_key'] ?? ''));
        $webhookInput = trim((string) ($validated['stripe_webhook_secret'] ?? ''));
        $hasSecret = $secretInput !== '' || \App\Support\StripeCredentials::secretKey($storeId) !== '';
        $hasWebhook = $webhookInput !== '' || \App\Support\StripeCredentials::webhookSecret($storeId) !== '';

        $errors = [];
        if (! $hasSecret) {
            $errors['stripe_secret_key'] = 'A Secret key é obrigatória.';
        }
        if (! $hasWebhook) {
            $errors['stripe_webhook_secret'] = 'O Webhook secret é obrigatório.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        \App\Support\StripeCredentials::setPublishableKey(
            (string) $validated['stripe_publishable_key'],
            $storeId,
        );
        if ($secretInput !== '') {
            \App\Support\StripeCredentials::setSecretKey($secretInput, $storeId);
        }
        if ($webhookInput !== '') {
            \App\Support\StripeCredentials::setWebhookSecret($webhookInput, $storeId);
        }
        \App\Support\StripeCredentials::setEnabled(true, $storeId);

        $this->settingsActivityLogger->logSection(
            $store,
            'pagamentos',
            'Configuração Stripe da organização actualizada',
            array_values(array_filter([
                $this->settingsActivityLogger->logBoolChange(
                    'Stripe ativo',
                    $beforeStripeEnabled,
                    true,
                ),
            ])),
        );

        return redirect()
            ->route('definicoes.pagamentos')
            ->with('status', 'Stripe ativado e configurado para toda a organização.');
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

    public function etiquetas(): View
    {
        return view('definicoes.etiquetas', [
            'pageTitle' => 'Etiquetas',
            'clientTags' => $this->clientTagService->tagsForStore(current_store_id()),
        ]);
    }

    public function etiquetasClientes(): RedirectResponse
    {
        return redirect()->route('definicoes.etiquetas');
    }
}
