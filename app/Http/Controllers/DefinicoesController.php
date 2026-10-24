<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\ClientTagService;
use App\Services\StoreBusinessSettingsService;
use App\Services\StoreOperationalSettingsService;
use App\Services\StoreSettingsActivityLogger;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DefinicoesController extends Controller
{
    public function __construct(
        private readonly StoreSettingsActivityLogger $settingsActivityLogger,
        private readonly ClientTagService $clientTagService,
        private readonly StoreBusinessSettingsService $storeBusinessSettings,
        private readonly StoreOperationalSettingsService $operationalSettings,
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

        $overwriteAll = $request->boolean('overwrite_all_stores');
        if ($overwriteAll) {
            $this->storeBusinessSettings->forceAllStoresInheritOrganization($organization);
        }

        $tab = $this->storeBusinessSettings->resolveActiveTab($request->input('_active_tab'), false);

        return redirect()
            ->route('definicoes.empresa', ['tab' => $tab])
            ->with('status', $overwriteAll
                ? 'Dados da empresa guardados e aplicados a todas as lojas.'
                : 'Dados da empresa guardados.');
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
        $orgId = current_organization_id();
        $storeId = $this->operationalSettings->representativeStoreId($orgId) ?? current_store_id();

        return view('definicoes.agendamentos', array_merge(
            $this->operationalSettings->bookingViewData($storeId),
            [
                'pageTitle' => 'Booking',
                'settingsScopeNote' => 'Estas opções aplicam-se às lojas sem definições próprias. Lojas com Booking específico na ficha não são sobrescritas.',
                'storesWithSpecificSettings' => $this->operationalSettings->storesWithSpecificBookingSettings($orgId),
                'storesWithSpecificSettingsTab' => 'booking',
            ],
        ));
    }

    public function updateMarcacoes(Request $request): RedirectResponse
    {
        $orgId = current_organization_id();
        $repId = $this->operationalSettings->representativeStoreId($orgId) ?? current_store_id();
        $overwriteAll = $request->boolean('overwrite_all_stores');
        $data = $this->operationalSettings->validateBooking($request, $repId);
        $this->operationalSettings->applyBookingToOrganization($data, $orgId, $overwriteAll);

        $store = app(CurrentStore::class)->get();
        $this->settingsActivityLogger->logSection(
            $store,
            'marcacoes',
            $overwriteAll
                ? 'Definições de Booking aplicadas a todas as lojas'
                : 'Definições de Booking actualizadas',
            [$overwriteAll
                ? 'Booking sobrescrito em todas as lojas da organização'
                : 'Booking aplicado às lojas sem definições específicas'],
        );

        return redirect()
            ->route('definicoes.marcacoes')
            ->with('status', $overwriteAll
                ? 'Definições de Booking guardadas em todas as lojas.'
                : 'Definições de Booking guardadas (lojas com Booking específico na ficha mantêm-se).');
    }

    public function equipa(): View
    {
        $orgId = current_organization_id();
        $data = $this->operationalSettings->equipaViewDataForOrganization($orgId);

        return view('definicoes.equipa', array_merge($data, [
            'pageTitle' => 'Equipa',
            'settingsScopeNote' => 'O limite de tempo pessoal aplica-se às lojas sem definição própria. Lojas com tempo pessoal específico na ficha não são sobrescritas. A tabela lista membros de toda a organização.',
            'storesWithSpecificSettings' => $this->operationalSettings->storesWithSpecificPersonalTimeSettings($orgId),
            'storesWithSpecificSettingsTab' => 'equipa',
        ]));
    }

    public function updateEquipa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'members' => ['nullable', 'array'],
            'members.*.role' => ['required', 'string', 'in:'.implode(',', array_keys(User::roles()))],
            'members.*.visible_in_agenda' => ['nullable', 'boolean'],
            'members.*.visible_in_booking' => ['nullable', 'boolean'],
            'members.*.agenda_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'personal_time_limit_store_hours' => ['nullable', 'boolean'],
        ]);

        $orgId = current_organization_id();
        $storeIds = $this->operationalSettings->storeIdsForOrganization($orgId);
        $overwriteAll = $request->boolean('overwrite_all_stores');
        $limit = $request->boolean('personal_time_limit_store_hours');
        $this->operationalSettings->applyPersonalTimeLimitToOrganization($limit, $orgId, $overwriteAll);

        try {
            $this->operationalSettings->applyEquipaMembers($validated['members'] ?? [], $storeIds);
        } catch (ValidationException $e) {
            return redirect()
                ->route('definicoes.equipa')
                ->withErrors($e->errors())
                ->withInput();
        }

        $store = app(CurrentStore::class)->get();
        $this->settingsActivityLogger->logSection(
            $store,
            'equipa',
            'Configuração da equipa actualizada',
            [$overwriteAll
                ? 'Tempo pessoal sobrescrito em todas as lojas; membros actualizados'
                : 'Tempo pessoal aplicado às lojas sem definição específica; membros actualizados'],
        );

        return redirect()
            ->route('definicoes.equipa')
            ->with('status', $overwriteAll
                ? 'Equipa actualizada em todas as lojas.'
                : 'Equipa actualizada (lojas com tempo pessoal específico na ficha mantêm-se).');
    }

    public function pagamentos(): View
    {
        $orgId = current_organization_id();
        $storeId = $this->operationalSettings->representativeStoreId($orgId) ?? current_store_id();
        $secret = \App\Support\StripeCredentials::secretKey($storeId);
        $webhook = \App\Support\StripeCredentials::webhookSecret($storeId);
        $flags = $this->operationalSettings->paymentFlagsViewData($storeId);

        return view('definicoes.pagamentos', array_merge($flags, [
            'pageTitle' => 'Pagamentos',
            'stripeEnabled' => \App\Support\StripeCredentials::isEnabled($storeId),
            'stripeReady' => \App\Support\StripeCredentials::isReady($storeId),
            'stripePublishableKey' => \App\Support\StripeCredentials::publishableKey($storeId),
            'stripeSecretMasked' => $secret !== '' ? \App\Support\StripeCredentials::maskSecret($secret) : '',
            'stripeWebhookMasked' => $webhook !== '' ? \App\Support\StripeCredentials::maskSecret($webhook) : '',
            'stripeHasSecret' => $secret !== '',
            'stripeHasWebhook' => $webhook !== '',
            'stripeWebhookUrl' => url('/stripe/webhook'),
            'paymentMethods' => \App\Support\PaymentMethodCatalog::forStore($storeId),
            'settingsScopeNote' => 'Stripe e métodos são da organização. Gorjeta e pagamento online aplicam-se às lojas sem definição própria; lojas com Pagamentos específicos na ficha não são sobrescritas nesses pontos.',
            'storesWithSpecificSettings' => $this->operationalSettings->storesWithSpecificPaymentFlagSettings($orgId),
            'storesWithSpecificSettingsTab' => 'pagamentos',
        ]));
    }

    public function updatePagamentos(Request $request): RedirectResponse
    {
        $orgId = current_organization_id();
        $storeId = $this->operationalSettings->representativeStoreId($orgId) ?? current_store_id();
        $store = app(CurrentStore::class)->get();

        $request->validate([
            'pos_gorjeta_enabled' => ['sometimes', 'boolean'],
            'online_booking_payment_required' => ['sometimes', 'boolean'],
            'methods' => ['nullable', 'array'],
            'methods.*.code' => ['required', 'string'],
            'methods.*.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'methods.*.agenda' => ['sometimes', 'boolean'],
            'methods.*.booking' => ['sometimes', 'boolean'],
        ]);

        $overwriteAll = $request->boolean('overwrite_all_stores');
        $this->operationalSettings->applyPaymentFlagsToOrganization(
            $request->boolean('pos_gorjeta_enabled'),
            $request->boolean('online_booking_payment_required'),
            $orgId,
            $overwriteAll,
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

        $this->settingsActivityLogger->logSection(
            $store,
            'pagamentos',
            'Definições de pagamento actualizadas (organização)',
            [$overwriteAll
                ? 'Métodos/Stripe e flags aplicados a todas as lojas (overwrite)'
                : 'Métodos/Stripe da organização; flags só em lojas sem definição específica'],
        );

        return redirect()
            ->route('definicoes.pagamentos')
            ->with('status', $overwriteAll
                ? 'Pagamentos guardados em todas as lojas.'
                : 'Pagamentos guardados (lojas com gorjeta/pagamento online específicos na ficha mantêm-se nesses pontos).');
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
        $keys = UserNotificationPreference::preferenceKeys();
        $saved = $user->notificationPreferences()
            ->whereIn('category', $keys)
            ->get()
            ->keyBy('category');

        $matrix = [];
        foreach ($keys as $key) {
            $row = $saved->get($key);
            $matrix[$key] = [
                'label' => $meta[$key]['label'],
                'description' => $meta[$key]['description'],
                'email_only' => (bool) ($meta[$key]['email_only'] ?? false),
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
        $meta = UserNotificationPreference::marcacaoTypesMeta();

        foreach (UserNotificationPreference::preferenceKeys() as $key) {
            $emailOnly = (bool) ($meta[$key]['email_only'] ?? false);
            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category' => $key,
                ],
                [
                    'bell_enabled' => $emailOnly
                        ? false
                        : self::notificationToggleFromInput($bellInput[$key] ?? null),
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
