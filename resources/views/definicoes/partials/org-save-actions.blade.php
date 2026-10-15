{{--
  Acções de guardar nas Definições gerais.
  - Guardar: não sobrescreve lojas com definições específicas
  - Guardar em todas as lojas: overwrite (só se houver lojas específicas), com modal

  Variáveis:
    storesWithSpecificSettings (list)
    primaryLabel (string, opcional)
    showForceAll (bool, opcional — default: há lojas específicas)
    formId (string, opcional — form a submeter no modal)
--}}
@php
  $specificStores = $storesWithSpecificSettings ?? [];
  $hasSpecific = is_array($specificStores) && $specificStores !== [];
  $showForceAll = $showForceAll ?? $hasSpecific;
  $primaryLabel = $primaryLabel ?? 'Guardar alterações';
  $formId = $formId ?? null;
  $modalId = 'org-save-all-stores-modal-'.substr(md5(($formId ?? 'default').$primaryLabel), 0, 8);
@endphp

@include('definicoes.partials.stores-with-specific-settings-alert', [
  'storesWithSpecificSettings' => $specificStores,
  'storesWithSpecificSettingsTab' => $storesWithSpecificSettingsTab ?? null,
])

<div class="uedit-form-actions pt-3 d-flex flex-wrap gap-2 align-items-center">
  <button type="submit" class="btn btn-primary" name="overwrite_all_stores" value="0">
    <i class="ph ph-check me-1"></i> {{ $primaryLabel }}
  </button>
  @if ($showForceAll)
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
      <i class="ph ph-buildings me-1"></i> Guardar em todas as lojas
    </button>
  @endif
</div>

@if ($showForceAll)
  <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="{{ $modalId }}-label">Aplicar a todas as lojas?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">
            Isto vai <strong>substituir</strong> as definições específicas das lojas abaixo pelas definições gerais que está a guardar.
          </p>
          @if ($hasSpecific)
            <ul class="small mb-3">
              @foreach ($specificStores as $row)
                <li>{{ $row['name'] }}</li>
              @endforeach
            </ul>
          @endif
          <p class="small text-muted mb-0">Esta acção não pode ser desfeita automaticamente.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button
            type="submit"
            class="btn btn-danger"
            name="overwrite_all_stores"
            value="1"
            @if ($formId) form="{{ $formId }}" @endif
          >
            Sim, guardar em todas as lojas
          </button>
        </div>
      </div>
    </div>
  </div>
@endif
