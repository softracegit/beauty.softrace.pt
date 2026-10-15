{{--
  section: booking|pagamentos|equipa|negocio
  show: bool
  resetUrl: string
  resetLabel?: string  (não usar "label" — colide com o foreach dos logotipos)
--}}
@if (!empty($show) && !empty($resetUrl))
  @php
    $resetSection = (string) ($section ?? 'negocio');
    $resetButtonLabel = (string) ($resetLabel ?? 'Repor definições gerais');
    $resetModalId = 'reset-to-general-'.$resetSection;
  @endphp
  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $resetModalId }}">
      <i class="ph ph-arrow-counter-clockwise me-1"></i> {{ $resetButtonLabel }}
    </button>
  </div>
  <div class="modal fade" id="{{ $resetModalId }}" tabindex="-1" aria-labelledby="{{ $resetModalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="{{ $resetModalId }}-label">Repor definições gerais?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            Esta loja vai deixar de ter definições próprias nesta secção e passa a usar as
            <strong>definições gerais</strong> da organização.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <form method="post" action="{{ $resetUrl }}" class="d-inline">
            @csrf
            <input type="hidden" name="section" value="{{ $resetSection }}">
            <button type="submit" class="btn btn-primary">
              <i class="ph ph-arrow-counter-clockwise me-1"></i> Repor
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif
