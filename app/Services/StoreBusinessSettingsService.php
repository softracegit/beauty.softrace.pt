<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreBusinessSettingsService
{
    public function viewDataForStore(Store $store, ?string $requestedTab = null): array
    {
        $store->loadMissing('organization');
        $storeId = (int) $store->id;
        $org = $store->organization;

        return [
            'context' => 'store',
            'store' => $store,
            'organization' => $org,
            'entity' => $store,
            'weeklySchedule' => old(
                'weekly_schedule',
                $store->usesOrganizationSchedule()
                    ? ($org?->normalizedWeeklySchedule() ?? Store::defaultWeeklySchedule())
                    : $store->normalizedWeeklySchedule()
            ),
            'privacyLockIdleMinutes' => old(
                'privacy_lock_idle_minutes',
                CrmSetting::privacyLockIdleMinutes($storeId)
            ),
            'privacyLockEnabled' => CrmSetting::privacyLockEnabled($storeId),
            'emailUseBusinessBranding' => old('email_use_business_branding') !== null
                ? filter_var(old('email_use_business_branding'), FILTER_VALIDATE_BOOLEAN)
                : CrmSetting::emailUseBusinessBranding($storeId),
            'activeTab' => $this->resolveActiveTab($requestedTab),
        ];
    }

    public function viewDataForOrganization(Organization $organization, ?string $requestedTab = null): array
    {
        $orgId = (int) $organization->id;

        return [
            'context' => 'organization',
            'organization' => $organization,
            'store' => null,
            'entity' => $organization,
            'weeklySchedule' => old('weekly_schedule', $organization->normalizedWeeklySchedule()),
            'privacyLockIdleMinutes' => old(
                'privacy_lock_idle_minutes',
                $this->organizationPrivacyIdle($orgId)
            ),
            'privacyLockEnabled' => false,
            'emailUseBusinessBranding' => old('email_use_business_branding') !== null
                ? filter_var(old('email_use_business_branding'), FILTER_VALIDATE_BOOLEAN)
                : $this->organizationEmailBranding($orgId),
            'activeTab' => $this->resolveActiveTab($requestedTab),
        ];
    }

    /** @deprecated use viewDataForStore */
    public function viewData(Store $store, ?string $requestedTab = null): array
    {
        return $this->viewDataForStore($store, $requestedTab);
    }

    public function resolveActiveTab(?string $tab): string
    {
        $allowed = ['dados', 'logotipos', 'horario', 'privacidade', 'emails'];

        $fromErrors = $this->tabFromValidationErrors();
        if ($fromErrors !== null && in_array($fromErrors, $allowed, true)) {
            return $fromErrors;
        }

        $fromOld = old('_active_tab');
        if (is_string($fromOld) && in_array($fromOld, $allowed, true)) {
            return $fromOld;
        }
        if (is_string($tab) && in_array($tab, $allowed, true)) {
            return $tab;
        }

        return 'dados';
    }

    /**
     * Tab a abrir quando há erros de validação (ex.: PIN em Privacidade).
     */
    public function tabFromValidationErrors(): ?string
    {
        if (! session()->has('errors')) {
            return null;
        }

        /** @var \Illuminate\Support\ViewErrorBag|mixed $bag */
        $bag = session('errors');
        if (! $bag instanceof \Illuminate\Support\ViewErrorBag || ! $bag->any()) {
            return null;
        }

        foreach ($bag->getBags() as $messageBag) {
            foreach ($messageBag->keys() as $key) {
                $tab = $this->tabForFieldName((string) $key);
                if ($tab !== null) {
                    return $tab;
                }
            }
        }

        return null;
    }

    public function tabForFieldName(string $field): ?string
    {
        if (str_starts_with($field, 'weekly_schedule')) {
            return 'horario';
        }

        return match ($field) {
            'name', 'slug', 'phone', 'email', 'website_url', 'instagram_url',
            'address_line', 'city', 'postal_code', 'maps_url' => 'dados',
            'logo', 'logo_email', 'logo_favicon',
            'remove_logo', 'remove_logo_email', 'remove_logo_favicon' => 'logotipos',
            'privacy_lock_idle_minutes', 'privacy_lock_pin', 'privacy_lock_pin_confirmation' => 'privacidade',
            'email_use_business_branding' => 'emails',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function applyToStore(Request $request, Store $store): array
    {
        $storeId = (int) $store->id;
        $store->loadMissing('organization');
        $org = $store->organization;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store->getKey())],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:32'],
            'maps_url' => ['required', 'url', 'max:512'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:512'],
            'instagram_url' => ['nullable', 'url', 'max:512'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'logo_email' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'logo_favicon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_logo_email' => ['nullable', 'boolean'],
            'remove_logo_favicon' => ['nullable', 'boolean'],
            'privacy_lock_idle_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'privacy_lock_pin' => ['nullable', 'regex:/^\d{4}$/'],
            'privacy_lock_pin_confirmation' => ['nullable', 'same:privacy_lock_pin'],
            'email_use_business_branding' => ['nullable', 'boolean'],
            '_active_tab' => ['nullable', 'string', 'in:dados,logotipos,horario,privacidade,emails'],
        ];

        if (! CrmSetting::privacyLockEnabled($storeId)) {
            $rules['privacy_lock_pin'] = ['required', 'regex:/^\d{4}$/'];
            $rules['privacy_lock_pin_confirmation'] = ['required', 'same:privacy_lock_pin'];
        }

        $validated = $request->validate($rules, [
            'name.required' => 'Indique o nome.',
            'slug.required' => 'Indique o slug (URL pública).',
            'maps_url.required' => 'O link do mapa é obrigatório nesta loja.',
            'maps_url.url' => 'O link do mapa deve ser um URL válido.',
            'address_line.required' => 'A morada é obrigatória nesta loja.',
            'city.required' => 'A localidade é obrigatória nesta loja.',
            'postal_code.required' => 'O código postal é obrigatório nesta loja.',
            'privacy_lock_pin.required' => 'Defina o PIN de desbloqueio do posto (4 dígitos).',
            'privacy_lock_pin.regex' => 'O PIN deve ter exatamente 4 dígitos.',
            'privacy_lock_pin_confirmation.required' => 'Confirme o PIN de desbloqueio.',
            'privacy_lock_pin_confirmation.same' => 'A confirmação do PIN não coincide.',
            'email.email' => 'Indique um email válido.',
            'website_url.url' => 'O site deve ser um URL válido.',
            'instagram_url.url' => 'O Instagram deve ser um URL válido.',
        ]);

        $submittedSchedule = $this->validatedWeeklySchedule($request);
        $orgSchedule = $org?->normalizedWeeklySchedule() ?? Store::defaultWeeklySchedule();
        $scheduleToStore = $this->schedulesEqual($submittedSchedule, $orgSchedule)
            ? null
            : $submittedSchedule;

        $logoPath = $this->handleLogoField(
            $request,
            $store->logoStorageDirectory(),
            'logo',
            'remove_logo',
            $store->logo
        );
        $logoEmailPath = $this->handleLogoField(
            $request,
            $store->logoStorageDirectory(),
            'logo_email',
            'remove_logo_email',
            $store->logo_email
        );
        $logoFaviconPath = $this->handleLogoField(
            $request,
            $store->logoStorageDirectory(),
            'logo_favicon',
            'remove_logo_favicon',
            $store->logo_favicon
        );

        $store->update([
            'name' => $validated['name'],
            'slug' => Str::slug((string) $validated['slug']),
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'maps_url' => $validated['maps_url'],
            'phone' => $this->storeOverrideOrNull($validated['phone'] ?? null, $org?->phone),
            'email' => $this->storeOverrideOrNull($validated['email'] ?? null, $org?->email),
            'website_url' => $this->storeOverrideOrNull($validated['website_url'] ?? null, $org?->website_url),
            'instagram_url' => $this->storeOverrideOrNull($validated['instagram_url'] ?? null, $org?->instagram_url),
            'timezone' => null,
            'logo' => $logoPath,
            'logo_email' => $logoEmailPath,
            'logo_favicon' => $logoFaviconPath,
            'weekly_schedule' => $scheduleToStore,
        ]);

        $orgIdle = $org ? $this->organizationPrivacyIdle((int) $org->id) : 5;
        $submittedIdle = (int) ($validated['privacy_lock_idle_minutes'] ?? $orgIdle);
        if ($submittedIdle === $orgIdle) {
            CrmSetting::clearStoreSettingOverride($storeId, CrmSetting::KEY_PRIVACY_LOCK_IDLE_MINUTES);
        } else {
            CrmSetting::setPrivacyLockIdleMinutes($submittedIdle, $storeId);
        }

        if ($request->filled('privacy_lock_pin')) {
            CrmSetting::setPrivacyLockPinHash(
                Hash::make((string) $request->input('privacy_lock_pin')),
                $storeId,
            );
        }

        $orgEmailBranding = $org ? $this->organizationEmailBranding((int) $org->id) : false;
        $submittedEmailBranding = $request->boolean('email_use_business_branding');
        if ($submittedEmailBranding === $orgEmailBranding) {
            CrmSetting::clearStoreSettingOverride($storeId, CrmSetting::KEY_EMAIL_USE_BUSINESS_BRANDING);
        } else {
            CrmSetting::setEmailUseBusinessBranding($submittedEmailBranding, $storeId);
        }

        return ['Loja actualizada'];
    }

    /**
     * @return list<string>
     */
    public function applyToOrganization(Request $request, Organization $organization): array
    {
        $orgId = (int) $organization->id;

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
            'privacy_lock_idle_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'email_use_business_branding' => ['nullable', 'boolean'],
            '_active_tab' => ['nullable', 'string', 'in:dados,logotipos,horario,privacidade,emails'],
        ], [
            'name.required' => 'Indique o nome da empresa.',
            'email.email' => 'Indique um email válido.',
            'maps_url.url' => 'O link do mapa deve ser um URL válido.',
            'website_url.url' => 'O site deve ser um URL válido.',
            'instagram_url.url' => 'O Instagram deve ser um URL válido.',
        ]);

        $logoPath = $this->handleLogoField(
            $request,
            $organization->logoStorageDirectory(),
            'logo',
            'remove_logo',
            $organization->logo
        );
        $logoEmailPath = $this->handleLogoField(
            $request,
            $organization->logoStorageDirectory(),
            'logo_email',
            'remove_logo_email',
            $organization->logo_email
        );
        $logoFaviconPath = $this->handleLogoField(
            $request,
            $organization->logoStorageDirectory(),
            'logo_favicon',
            'remove_logo_favicon',
            $organization->logo_favicon
        );

        $organization->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => null,
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

        CrmSetting::setOrganizationPrivacyLockIdleMinutes(
            (int) ($validated['privacy_lock_idle_minutes'] ?? 5),
            $orgId,
        );
        CrmSetting::setOrganizationEmailUseBusinessBranding(
            $request->boolean('email_use_business_branding'),
            $orgId,
        );

        return ['Dados da empresa actualizados'];
    }

    /** @deprecated */
    public function apply(Request $request, Store $store, bool $includeSlugTimezone = false): array
    {
        return $this->applyToStore($request, $store);
    }

    /**
     * Null = herda da empresa. Guarda valor na loja só se for diferente do da empresa.
     */
    private function storeOverrideOrNull(mixed $submitted, mixed $organizationValue): ?string
    {
        $submittedNorm = $this->normalizeComparableString($submitted);
        $orgNorm = $this->normalizeComparableString($organizationValue);

        if ($submittedNorm === '' || $submittedNorm === $orgNorm) {
            return null;
        }

        return $submittedNorm;
    }

    private function normalizeComparableString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * @param  array<string, array{enabled: bool, start: ?string, end: ?string}>  $a
     * @param  array<string, array{enabled: bool, start: ?string, end: ?string}>  $b
     */
    private function schedulesEqual(array $a, array $b): bool
    {
        foreach (Agent::WEEKDAY_KEYS as $day) {
            $left = $a[$day] ?? ['enabled' => false, 'start' => null, 'end' => null];
            $right = $b[$day] ?? ['enabled' => false, 'start' => null, 'end' => null];
            $leftEnabled = (bool) ($left['enabled'] ?? false);
            $rightEnabled = (bool) ($right['enabled'] ?? false);
            if ($leftEnabled !== $rightEnabled) {
                return false;
            }
            if (! $leftEnabled) {
                continue;
            }
            if (($left['start'] ?? null) !== ($right['start'] ?? null)
                || ($left['end'] ?? null) !== ($right['end'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function organizationPrivacyIdle(int $organizationId): int
    {
        $raw = CrmSetting::query()
            ->where('setting_scope', CrmSetting::organizationSettingScope($organizationId, CrmSetting::KEY_PRIVACY_LOCK_IDLE_MINUTES))
            ->value('value');
        if ($raw !== null && is_numeric($raw)) {
            return max(0, min(240, (int) $raw));
        }

        return 5;
    }

    private function organizationEmailBranding(int $organizationId): bool
    {
        $raw = CrmSetting::query()
            ->where('setting_scope', CrmSetting::organizationSettingScope($organizationId, CrmSetting::KEY_EMAIL_USE_BUSINESS_BRANDING))
            ->value('value');
        if ($raw === null || $raw === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    private function handleLogoField(
        Request $request,
        string $directory,
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
            Storage::disk('public')->makeDirectory($directory);
            $path = $request->file($uploadField)->store($directory, 'public');
        }

        return $path;
    }

    /**
     * @return array<string, array{enabled: bool, start: ?string, end: ?string}>
     */
    public function validatedWeeklySchedule(Request $request): array
    {
        $raw = $request->input('weekly_schedule');
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'weekly_schedule' => 'Indique o horário.',
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
