@extends('definicoes.layout')

@section('definicoes_content')
  <style>
    .negocio-settings-card {
      margin-bottom: 2rem;
    }
    .store-logo-field__preview {
      width: 4.5rem;
      height: 4.5rem;
      flex-shrink: 0;
    }
    .store-logo-field__preview-img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      display: block;
    }
  </style>

  <form method="post" action="{{ route('definicoes.negocio.update') }}" enctype="multipart/form-data">
    @csrf

    <div class="card negocio-settings-card">
      <div class="card-header">
        <h5 class="card-title mb-0">Dados gerais</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label for="store_name" class="form-label fw-semibold">Nome do negócio</label>
            <input
              type="text"
              id="store_name"
              name="name"
              class="form-control @error('name') is-invalid @enderror"
              value="{{ old('name', $store->name) }}"
              required
              maxlength="255"
            >
            @error('name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-6">
            <label for="store_phone" class="form-label">Telefone</label>
            <input
              type="text"
              id="store_phone"
              name="phone"
              class="form-control @error('phone') is-invalid @enderror"
              value="{{ old('phone', $store->phone) }}"
              maxlength="64"
              autocomplete="tel"
            >
            @error('phone')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-6">
            <label for="store_email" class="form-label">Email</label>
            <input
              type="email"
              id="store_email"
              name="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email', $store->email) }}"
              maxlength="255"
              autocomplete="email"
            >
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-6">
            <label for="store_website_url" class="form-label">Site</label>
            <input
              type="url"
              id="store_website_url"
              name="website_url"
              class="form-control @error('website_url') is-invalid @enderror"
              value="{{ old('website_url', $store->website_url) }}"
              placeholder="https://"
              maxlength="512"
            >
            @error('website_url')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-6">
            <label for="store_instagram_url" class="form-label">Instagram</label>
            <input
              type="url"
              id="store_instagram_url"
              name="instagram_url"
              class="form-control @error('instagram_url') is-invalid @enderror"
              value="{{ old('instagram_url', $store->instagram_url) }}"
              placeholder="https://www.instagram.com/..."
              maxlength="512"
            >
            @error('instagram_url')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>
    </div>

    <div class="card negocio-settings-card">
      <div class="card-header">
        <h5 class="card-title mb-0">Logótipo</h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-12 col-lg-4">
            @include('definicoes.partials.store-logo-field', [
              'inputName' => 'logo',
              'removeName' => 'remove_logo',
              'inputId' => 'store_logo_generic',
              'previewId' => 'storeLogoPreview_generic',
              'previewUrl' => $store->logoGenericUrl(),
              'hasLogo' => (bool) $store->logo,
              'label' => 'Genérico',
              'help' => 'Painel de resumo da marcação online.',
            ])
          </div>
          <div class="col-12 col-lg-4">
            @include('definicoes.partials.store-logo-field', [
              'inputName' => 'logo_favicon',
              'removeName' => 'remove_logo_favicon',
              'inputId' => 'store_logo_favicon',
              'previewId' => 'storeLogoPreview_favicon',
              'previewUrl' => $store->logoFaviconUrl(),
              'hasLogo' => (bool) $store->logo_favicon,
              'label' => 'Favicon (booking)',
              'help' => 'Ícone do browser na marcação online.',
            ])
          </div>
          <div class="col-12 col-lg-4">
            @include('definicoes.partials.store-logo-field', [
              'inputName' => 'logo_email',
              'removeName' => 'remove_logo_email',
              'inputId' => 'store_logo_email',
              'previewId' => 'storeLogoPreview_email',
              'previewUrl' => $store->logo_email ? $store->logoEmailUrl() : asset('template/img/logo-color-black.png'),
              'hasLogo' => (bool) $store->logo_email,
              'label' => 'Emails',
              'help' => 'Cabeçalho dos emails (quando activo).',
            ])
          </div>
        </div>
      </div>
    </div>

    <div class="card negocio-settings-card">
      <div class="card-header">
        <h5 class="card-title mb-0">Morada</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label for="store_address_line" class="form-label">Rua / número</label>
            <input
              type="text"
              id="store_address_line"
              name="address_line"
              class="form-control @error('address_line') is-invalid @enderror"
              value="{{ old('address_line', $store->address_line) }}"
              maxlength="255"
            >
            @error('address_line')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-4">
            <label for="store_postal_code" class="form-label">Código postal</label>
            <input
              type="text"
              id="store_postal_code"
              name="postal_code"
              class="form-control @error('postal_code') is-invalid @enderror"
              value="{{ old('postal_code', $store->postal_code) }}"
              maxlength="32"
            >
            @error('postal_code')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-8">
            <label for="store_city" class="form-label">Localidade</label>
            <input
              type="text"
              id="store_city"
              name="city"
              class="form-control @error('city') is-invalid @enderror"
              value="{{ old('city', $store->city) }}"
              maxlength="120"
            >
            @error('city')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12">
            <label for="store_maps_url" class="form-label">Link Google Maps <span class="text-muted fw-normal">(opcional)</span></label>
            <input
              type="url"
              id="store_maps_url"
              name="maps_url"
              class="form-control @error('maps_url') is-invalid @enderror"
              value="{{ old('maps_url', $store->maps_url) }}"
              placeholder="https://maps.google.com/..."
              maxlength="512"
            >
            <div class="form-text">Se ficar vazio, o link é gerado a partir da morada.</div>
            @error('maps_url')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>
    </div>

    <div class="card negocio-settings-card">
      <div class="card-header">
        <h5 class="card-title mb-0">Horário</h5>
      </div>
      <div class="card-body">
        @include('definicoes.partials.store-weekly-schedule', ['weeklySchedule' => $weeklySchedule])
      </div>
    </div>

    <div class="card negocio-settings-card" id="privacy-lock-pin">
      <div class="card-header">
        <h5 class="card-title mb-0">Privacidade no posto</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label for="privacy_lock_idle_minutes" class="form-label">Bloqueio automático (minutos)</label>
            <input
              type="number"
              id="privacy_lock_idle_minutes"
              name="privacy_lock_idle_minutes"
              class="form-control @error('privacy_lock_idle_minutes') is-invalid @enderror"
              min="0"
              max="240"
              step="1"
              value="{{ old('privacy_lock_idle_minutes', $privacyLockIdleMinutes ?? 5) }}"
            >
            <div class="form-text">0 desativa o bloqueio por inatividade.</div>
            @error('privacy_lock_idle_minutes')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-4">
            <label for="privacy_lock_pin" class="form-label">PIN de desbloqueio (4 dígitos)</label>
            <input
              type="password"
              id="privacy_lock_pin"
              name="privacy_lock_pin"
              inputmode="numeric"
              pattern="[0-9]{4}"
              maxlength="4"
              class="form-control @error('privacy_lock_pin') is-invalid @enderror"
              placeholder="{{ !empty($privacyLockEnabled) ? '••••' : '0000' }}"
            >
            <div class="form-text">Deixe vazio para manter o PIN atual.</div>
            @error('privacy_lock_pin')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-12 col-md-4">
            <label for="privacy_lock_pin_confirmation" class="form-label">Confirmar PIN</label>
            <input
              type="password"
              id="privacy_lock_pin_confirmation"
              name="privacy_lock_pin_confirmation"
              inputmode="numeric"
              pattern="[0-9]{4}"
              maxlength="4"
              class="form-control @error('privacy_lock_pin_confirmation') is-invalid @enderror"
            >
            @error('privacy_lock_pin_confirmation')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
  </form>

  @if (session('status'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('status')), 'success');
        }
      });
    </script>
  @endif

  <script>
    function previewStoreLogoField(input, previewId) {
      var preview = document.getElementById(previewId);
      if (!preview || !input.files || !input.files[0]) return;
      preview.src = URL.createObjectURL(input.files[0]);
    }
  </script>
@endsection
