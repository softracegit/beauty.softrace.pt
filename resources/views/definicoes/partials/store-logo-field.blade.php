@php
  $inputName = $inputName ?? 'logo';
  $removeName = $removeName ?? 'remove_logo';
  $inputId = $inputId ?? 'store_logo_'.$inputName;
  $previewId = $previewId ?? 'storeLogoPreview_'.$inputName;
  $previewUrl = $previewUrl ?? '';
  $hasLogo = (bool) ($hasLogo ?? false);
  $label = $label ?? 'Logotipo';
  $help = $help ?? 'JPEG, PNG ou WebP. Máx. 2 MB.';
@endphp
<div class="store-logo-field">
  <label for="{{ $inputId }}" class="form-label fw-semibold d-block">{{ $label }}</label>
  <div class="d-flex align-items-start gap-3">
    <div class="store-logo-field__preview rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden">
      <img
        src="{{ $previewUrl }}"
        alt="{{ $label }}"
        id="{{ $previewId }}"
        class="store-logo-field__preview-img"
        loading="lazy"
        decoding="async"
      >
    </div>
    <div class="flex-grow-1 min-w-0">
      <input
        type="file"
        name="{{ $inputName }}"
        id="{{ $inputId }}"
        class="form-control form-control-sm @error($inputName) is-invalid @enderror"
        accept="image/jpeg,image/png,image/jpg,image/webp"
        onchange="previewStoreLogoField(this, '{{ $previewId }}')"
      >
      @error($inputName)
        <div class="invalid-feedback d-block">{{ $message }}</div>
      @enderror
      <div class="form-text">{{ $help }}</div>
      @if ($hasLogo)
        <div class="form-check mt-2">
          <input type="hidden" name="{{ $removeName }}" value="0">
          <input type="checkbox" class="form-check-input" name="{{ $removeName }}" value="1" id="{{ $removeName }}">
          <label class="form-check-label small" for="{{ $removeName }}">Remover imagem actual</label>
        </div>
      @endif
    </div>
  </div>
</div>
