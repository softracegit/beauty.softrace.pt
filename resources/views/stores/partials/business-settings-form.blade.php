{{--
  context: store|organization
  entity: Store|Organization
  organization: org (valores por defeito na ficha da loja)
--}}
@php
  $context = $context ?? 'store';
  $isStore = $context === 'store';
  $activeTab = $activeTab ?? 'dados';
  $org = $organization ?? null;
  $entity = $entity ?? $store ?? $organization;
  $formMethod = strtolower((string) ($formMethod ?? 'post'));
  $submitLabel = $submitLabel ?? 'Guardar';

  $phoneValue = old('phone', $isStore ? ($store->resolvedPhone() ?: '') : ($entity->phone ?? ''));
  $emailValue = old('email', $isStore ? ($store->resolvedEmail() ?: '') : ($entity->email ?? ''));
  $websiteValue = old('website_url', $isStore ? ($store->resolvedWebsiteUrl() ?: '') : ($entity->website_url ?? ''));
  $instagramValue = old('instagram_url', $isStore ? ($store->resolvedInstagramUrl() ?: '') : ($entity->instagram_url ?? ''));
@endphp

<style>
  .store-logo-field__preview { width: 4.5rem; height: 4.5rem; flex-shrink: 0; }
  .store-logo-field__preview-img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
  .inherit-hint { font-size: 0.8rem; color: var(--bs-secondary-color); }
</style>

<form method="post" action="{{ $formAction }}" enctype="multipart/form-data" id="store-business-settings-form">
  @csrf
  @if ($formMethod === 'put')
    @method('PUT')
  @endif
  <input type="hidden" name="_active_tab" id="store_business_active_tab" value="{{ $activeTab }}">

  <div class="card">
    <div class="uview-tabs" role="tablist">
      <button type="button" class="uview-tab nav-link {{ $activeTab === 'dados' ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#tab-dados" data-store-tab="dados">Dados</button>
      <button type="button" class="uview-tab nav-link {{ $activeTab === 'logotipos' ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#tab-logotipos" data-store-tab="logotipos">Logotipos</button>
      <button type="button" class="uview-tab nav-link {{ $activeTab === 'horario' ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#tab-horario" data-store-tab="horario">Horário</button>
      <button type="button" class="uview-tab nav-link {{ $activeTab === 'privacidade' ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#tab-privacidade" data-store-tab="privacidade">Privacidade</button>
      <button type="button" class="uview-tab nav-link {{ $activeTab === 'emails' ? 'active' : '' }}" role="tab" data-bs-toggle="tab" data-bs-target="#tab-emails" data-store-tab="emails">Emails</button>
    </div>

    <div class="card-body tab-content">
      <div class="tab-pane fade {{ $activeTab === 'dados' ? 'show active' : '' }}" id="tab-dados" role="tabpanel">
        <div class="uedit-section mb-4">
          <div class="uedit-section-title">{{ $isStore ? 'Identidade da loja' : 'Dados da empresa' }}</div>
          <div class="row g-3">
            <div class="col-12 {{ $isStore ? 'col-md-6' : '' }}">
              <label class="form-label fw-semibold" for="store_name">{{ $isStore ? 'Nome da loja' : 'Nome da empresa' }}</label>
              <input type="text" id="store_name" name="name" class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $entity->name) }}" required maxlength="255">
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if ($isStore)
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="store_slug">Slug (URL pública)</label>
                <input type="text" id="store_slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
                  value="{{ old('slug', $entity->slug) }}" required maxlength="255">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            @endif
          </div>
        </div>

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Contactos e redes</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="field_phone">Telefone</label>
              <input type="text" id="field_phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                value="{{ $phoneValue }}" maxlength="64" autocomplete="tel">
              @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if ($isStore && $store->usesOrganizationPhone() && $org?->phone)
                <div class="inherit-hint mt-1">Herdado da empresa. Pode alterar só nesta loja se pretender</div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label" for="field_email">Email</label>
              <input type="email" id="field_email" name="email" class="form-control @error('email') is-invalid @enderror"
                value="{{ $emailValue }}" maxlength="255" autocomplete="email">
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if ($isStore && $store->usesOrganizationEmail() && $org?->email)
                <div class="inherit-hint mt-1">Herdado da empresa. Pode alterar só nesta loja se pretender</div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label" for="field_website_url">Site</label>
              <input type="url" id="field_website_url" name="website_url" class="form-control @error('website_url') is-invalid @enderror"
                value="{{ $websiteValue }}" placeholder="https://" maxlength="512">
              @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if ($isStore && $store->usesOrganizationWebsite() && $org?->website_url)
                <div class="inherit-hint mt-1">Herdado da empresa. Pode alterar só nesta loja se pretender</div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label" for="field_instagram_url">Instagram</label>
              <input type="url" id="field_instagram_url" name="instagram_url" class="form-control @error('instagram_url') is-invalid @enderror"
                value="{{ $instagramValue }}" placeholder="https://www.instagram.com/..." maxlength="512">
              @error('instagram_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if ($isStore && $store->usesOrganizationInstagram() && $org?->instagram_url)
                <div class="inherit-hint mt-1">Herdado da empresa. Pode alterar só nesta loja se pretender</div>
              @endif
            </div>
          </div>
        </div>

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Morada {{ $isStore ? '(obrigatória nesta loja)' : '(sede / empresa)' }}</div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="store_address_line">Rua / número</label>
              <input type="text" id="store_address_line" name="address_line" class="form-control @error('address_line') is-invalid @enderror"
                value="{{ old('address_line', $entity->address_line) }}" maxlength="255" @required($isStore)>
              @error('address_line')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label" for="store_postal_code">Código postal</label>
              <input type="text" id="store_postal_code" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror"
                value="{{ old('postal_code', $entity->postal_code) }}" maxlength="32" @required($isStore)>
              @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
              <label class="form-label" for="store_city">Localidade</label>
              <input type="text" id="store_city" name="city" class="form-control @error('city') is-invalid @enderror"
                value="{{ old('city', $entity->city) }}" maxlength="120" @required($isStore)>
              @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <label class="form-label" for="store_maps_url">Link Google Maps</label>
              <input type="url" id="store_maps_url" name="maps_url" class="form-control @error('maps_url') is-invalid @enderror"
                value="{{ old('maps_url', $entity->maps_url) }}" placeholder="https://maps.google.com/..." maxlength="512" @required($isStore)>
              @error('maps_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade {{ $activeTab === 'logotipos' ? 'show active' : '' }}" id="tab-logotipos" role="tabpanel">
        <div class="uedit-section">
          @foreach ([
            ['logo', 'Genérico', 'Painel / marcação online', $store?->usesOrganizationLogo() ?? false, $org?->hasOwnLogo() ?? false],
            ['logo_favicon', 'Favicon', 'Ícone do browser no booking', $store?->usesOrganizationLogoFavicon() ?? false, $org?->hasOwnLogoFavicon() ?? false],
            ['logo_email', 'Emails', 'Cabeçalho dos emails', $store?->usesOrganizationLogoEmail() ?? false, $org?->hasOwnLogoEmail() ?? false],
          ] as [$field, $label, $help, $usesOrg, $orgHasLogo])
            @php
              $previewUrl = $field === 'logo'
                ? $entity->logoGenericUrl()
                : ($field === 'logo_email' ? $entity->logoEmailUrl() : $entity->logoFaviconUrl());
              $hasOwnLogo = (bool) $entity->{$field};
              $logoHelp = $help;
              if ($isStore && $usesOrg) {
                $logoHelp = $orgHasLogo
                  ? $help.' · A mostrar o da empresa — carregue um ficheiro para substituir só nesta loja.'
                  : $help.' · A empresa ainda não tem este logotipo.';
              } elseif ($isStore && $hasOwnLogo) {
                $logoHelp = $help.' · Específico desta loja. Remover volta ao da empresa.';
              }
            @endphp
            <div class="mb-4">
              @include('definicoes.partials.store-logo-field', [
                'inputName' => $field,
                'removeName' => 'remove_'.$field,
                'inputId' => 'store_'.$field,
                'previewId' => 'preview_'.$field,
                'previewUrl' => $previewUrl,
                'hasLogo' => $hasOwnLogo,
                'label' => $label,
                'help' => $logoHelp,
              ])
            </div>
          @endforeach
        </div>
      </div>

      <div class="tab-pane fade {{ $activeTab === 'horario' ? 'show active' : '' }}" id="tab-horario" role="tabpanel">
        @if ($isStore && ($store->usesOrganizationSchedule() ?? false))
          <p class="inherit-hint mb-3">Horário igual ao da empresa. Altere abaixo só se esta loja tiver horário próprio.</p>
        @endif
        @include('definicoes.partials.store-weekly-schedule', ['weeklySchedule' => $weeklySchedule])
      </div>

      <div class="tab-pane fade {{ $activeTab === 'privacidade' ? 'show active' : '' }}" id="tab-privacidade" role="tabpanel">
        <div id="privacy-lock-pin" class="uedit-section">
          <div class="uedit-section-title">Privacidade no posto</div>
          <div class="mb-3">
            <label class="form-label" for="privacy_lock_idle_minutes">
              {{ $isStore ? 'Bloqueio automático (minutos)' : 'Bloqueio automático por defeito (minutos)' }}
            </label>
            <input type="number" id="privacy_lock_idle_minutes" name="privacy_lock_idle_minutes" class="form-control"
              min="0" max="240" value="{{ old('privacy_lock_idle_minutes', $privacyLockIdleMinutes ?? 5) }}" style="max-width:12rem">
            @if ($isStore)
              <p class="inherit-hint mt-1 mb-0">Se coincidir com o da empresa, continua a herdar. O PIN é sempre desta loja.</p>
            @else
              <p class="inherit-hint mt-1 mb-0">As lojas usam este valor até definirem outro. O PIN é sempre definido em cada loja.</p>
            @endif
          </div>
          @if ($isStore)
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="privacy_lock_pin">PIN de desbloqueio (4 dígitos) @if(empty($privacyLockEnabled))<span class="text-danger">*</span>@endif</label>
                <input type="password" id="privacy_lock_pin" name="privacy_lock_pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                  class="form-control @error('privacy_lock_pin') is-invalid @enderror"
                  placeholder="{{ !empty($privacyLockEnabled) ? '••••' : '0000' }}"
                  @required(empty($privacyLockEnabled))>
                <div class="form-text">{{ !empty($privacyLockEnabled) ? 'Deixe vazio para manter o PIN actual.' : 'Obrigatório nesta loja.' }}</div>
                @error('privacy_lock_pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="privacy_lock_pin_confirmation">Confirmar PIN</label>
                <input type="password" id="privacy_lock_pin_confirmation" name="privacy_lock_pin_confirmation" inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                  class="form-control @error('privacy_lock_pin_confirmation') is-invalid @enderror"
                  @required(empty($privacyLockEnabled))>
                @error('privacy_lock_pin_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
          @endif
        </div>
      </div>

      <div class="tab-pane fade {{ $activeTab === 'emails' ? 'show active' : '' }}" id="tab-emails" role="tabpanel">
        <div class="uedit-section">
          <div class="uedit-section-title">Branding nos emails</div>
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="email_use_business_branding">Usar dados do negócio nos emails</label>
            <div class="form-check form-switch m-0">
              <input type="hidden" name="email_use_business_branding" value="0">
              <input class="form-check-input" type="checkbox" name="email_use_business_branding" value="1"
                id="email_use_business_branding" @checked($emailUseBusinessBranding ?? false)>
            </div>
          </div>
          <p class="small text-muted mt-2 mb-0">
            Quando activo, usa logotipo e nome comerciais; senão, usa a marca da aplicação.
            @if ($isStore)
              Se coincidir com a empresa, continua a herdar.
            @endif
          </p>
        </div>
      </div>
    </div>
  </div>

  <div class="uedit-form-actions mt-3">
    @if (!empty($cancelUrl))
      <a href="{{ $cancelUrl }}" class="btn btn-secondary">Cancelar</a>
    @endif
    <button type="submit" class="btn btn-primary"><i class="ph ph-check me-1"></i> {{ $submitLabel }}</button>
  </div>
</form>

<script>
function previewStoreLogoField(input, previewId) {
  var preview = document.getElementById(previewId);
  if (!preview || !input.files || !input.files[0]) return;
  preview.src = URL.createObjectURL(input.files[0]);
}

function storeBusinessShowTabForPane(pane) {
  if (!pane || !pane.id) return;
  var btn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
  if (btn && typeof bootstrap !== 'undefined') {
    bootstrap.Tab.getOrCreateInstance(btn).show();
  }
}

function storeBusinessTabPaneForField(field) {
  return field ? field.closest('.tab-pane') : null;
}

function storeBusinessFieldErrorMessage(field) {
  if (!field) return 'Preencha os campos obrigatórios.';
  var name = field.getAttribute('name') || field.id || '';
  var messages = {
    name: 'Indique o nome.',
    slug: 'Indique o slug (URL pública).',
    phone: 'Indique um telefone válido.',
    email: 'Indique um email válido.',
    website_url: 'Indique um URL de site válido.',
    instagram_url: 'Indique um URL de Instagram válido.',
    address_line: 'A morada é obrigatória nesta loja.',
    city: 'A localidade é obrigatória nesta loja.',
    postal_code: 'O código postal é obrigatório nesta loja.',
    maps_url: 'O link do mapa é obrigatório nesta loja.',
    privacy_lock_pin: 'Defina o PIN de desbloqueio do posto (4 dígitos).',
    privacy_lock_pin_confirmation: 'Confirme o PIN de desbloqueio.',
    privacy_lock_idle_minutes: 'Indique os minutos de bloqueio automático.',
    logo: 'Seleccione um logotipo válido (JPEG, PNG ou WebP).',
    logo_email: 'Seleccione um logotipo de email válido.',
    logo_favicon: 'Seleccione um favicon válido.'
  };

  if (name === 'privacy_lock_pin' || name === 'privacy_lock_pin_confirmation') {
    if (field.validity && field.validity.patternMismatch) {
      return name === 'privacy_lock_pin'
        ? 'O PIN deve ter exatamente 4 dígitos.'
        : 'A confirmação do PIN deve ter exatamente 4 dígitos.';
    }
    if (field.validity && field.validity.valueMissing) {
      return messages[name];
    }
  }

  if (field.validity) {
    if (field.validity.typeMismatch) {
      if (name === 'email') return messages.email;
      if (name === 'website_url' || name === 'instagram_url' || name === 'maps_url') {
        return name === 'maps_url'
          ? 'O link do mapa deve ser um URL válido.'
          : (messages[name] || 'Indique um URL válido.');
      }
    }
    if (field.validity.badInput && name === 'privacy_lock_idle_minutes') {
      return messages.privacy_lock_idle_minutes;
    }
  }

  return messages[name] || 'Preencha este campo.';
}

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('store-business-settings-form');
  var tabInput = document.getElementById('store_business_active_tab');

  document.querySelectorAll('[data-store-tab]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function () {
      if (tabInput) tabInput.value = btn.getAttribute('data-store-tab') || 'dados';
    });
  });

  if (window.location.hash === '#privacy-lock-pin') {
    var privacyBtn = document.querySelector('[data-store-tab="privacidade"]');
    if (privacyBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(privacyBtn).show();
  }

  // Erros do servidor: focar o primeiro campo inválido (a tab já vem activa via PHP).
  var firstInvalid = form ? form.querySelector('.is-invalid') : null;
  if (firstInvalid) {
    storeBusinessShowTabForPane(storeBusinessTabPaneForField(firstInvalid));
    setTimeout(function () {
      try { firstInvalid.focus({ preventScroll: false }); } catch (e) { firstInvalid.focus(); }
    }, 50);
  }

  // Validação HTML5: uma mensagem de cada vez, em português, na tab certa.
  if (form) {
    form.addEventListener('invalid', function (e) {
      e.preventDefault();
      if (form.__storeBizShowingError) return;
      form.__storeBizShowingError = true;

      var field = e.target;
      storeBusinessShowTabForPane(storeBusinessTabPaneForField(field));
      if (typeof window.showToast === 'function') {
        window.showToast(storeBusinessFieldErrorMessage(field), 'error');
      }
      setTimeout(function () {
        try { field.focus({ preventScroll: false }); } catch (err) { field.focus(); }
        form.__storeBizShowingError = false;
      }, 80);
    }, true);

    // Se preencheram PIN mas não a confirmação (ou vice-versa), mensagem específica.
    form.addEventListener('submit', function (e) {
      var pin = form.querySelector('[name="privacy_lock_pin"]');
      var pinConfirm = form.querySelector('[name="privacy_lock_pin_confirmation"]');
      if (!pin || !pinConfirm) return;

      var pinVal = (pin.value || '').trim();
      var confVal = (pinConfirm.value || '').trim();
      var msg = null;
      var focusEl = null;

      if (pin.required && pinVal === '') {
        msg = storeBusinessFieldErrorMessage(pin);
        focusEl = pin;
      } else if (pinConfirm.required && confVal === '') {
        msg = storeBusinessFieldErrorMessage(pinConfirm);
        focusEl = pinConfirm;
      } else if (pinVal !== '' && confVal === '') {
        msg = 'Confirme o PIN de desbloqueio.';
        focusEl = pinConfirm;
      } else if (pinVal !== '' && confVal !== '' && pinVal !== confVal) {
        msg = 'A confirmação do PIN não coincide.';
        focusEl = pinConfirm;
      } else if (pinVal !== '' && !/^\d{4}$/.test(pinVal)) {
        msg = 'O PIN deve ter exatamente 4 dígitos.';
        focusEl = pin;
      }

      if (!msg) return;
      e.preventDefault();
      storeBusinessShowTabForPane(storeBusinessTabPaneForField(focusEl));
      if (typeof window.showToast === 'function') {
        window.showToast(msg, 'error');
      }
      setTimeout(function () {
        try { focusEl.focus({ preventScroll: false }); } catch (err) { focusEl.focus(); }
      }, 50);
    });
  }
});
</script>
