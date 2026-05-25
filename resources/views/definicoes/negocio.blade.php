@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Negócio</h5>
    </div>
    <div class="card-body">
      @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
      @endif

      <p class="text-muted small mb-4">
        Dados visíveis na marcação online, no menu da loja e no CRM. Cada loja tem o seu próprio perfil.
      </p>

      <form method="post" action="{{ route('definicoes.negocio.update') }}" enctype="multipart/form-data">
        @csrf

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Identidade</div>
          <div class="row g-3">
            <div class="col-12 col-lg-8">
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
            <div class="col-12 col-lg-4">
              <label class="form-label fw-semibold d-block">Logotipo</label>
              <div class="d-flex align-items-start gap-3">
                <img
                  src="{{ $store->logoUrl() }}"
                  alt="Logotipo"
                  class="rounded border bg-light"
                  width="72"
                  height="72"
                  style="object-fit: cover;"
                  id="storeLogoPreview"
                >
                <div class="flex-grow-1">
                  <input
                    type="file"
                    name="logo"
                    id="store_logo"
                    class="form-control form-control-sm @error('logo') is-invalid @enderror"
                    accept="image/jpeg,image/png,image/jpg,image/webp"
                    onchange="previewStoreLogo(this)"
                  >
                  @error('logo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                  <div class="form-text">JPEG, PNG ou WebP. Máx. 2 MB. Aparece na marcação online.</div>
                  @if ($store->logo)
                    <div class="form-check mt-2">
                      <input type="hidden" name="remove_logo" value="0">
                      <input type="checkbox" class="form-check-input" name="remove_logo" value="1" id="remove_logo">
                      <label class="form-check-label small" for="remove_logo">Remover logotipo actual</label>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Contactos</div>
          <div class="row g-3">
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
          </div>
        </div>

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Morada</div>
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
              <div class="form-text">Se ficar vazio, o link é gerado automaticamente a partir da morada.</div>
              @error('maps_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>

        <div class="uedit-section mb-4">
          <div class="uedit-section-title">Redes e site</div>
          <div class="row g-3">
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

        @include('definicoes.partials.store-weekly-schedule', ['weeklySchedule' => $weeklySchedule])

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function previewStoreLogo(input) {
      var preview = document.getElementById('storeLogoPreview');
      if (!preview || !input.files || !input.files[0]) return;
      preview.src = URL.createObjectURL(input.files[0]);
    }
  </script>
@endsection
