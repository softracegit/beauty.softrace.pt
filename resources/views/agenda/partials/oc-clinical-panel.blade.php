{{--
  Layer da ficha técnica sobre a coluna direita (slide from right, sem header).
  Prefixo: agendaOc | eventDetailOc
--}}
@php
  $pfx = $prefix ?? 'agendaOc';
@endphp
<div
  class="agenda-oc-clinical-layer"
  id="{{ $pfx }}ClinicalPanel"
  aria-hidden="true"
  aria-labelledby="{{ $pfx }}ClinicalPanelTitle"
>
  <button type="button" class="btn-close agenda-oc-clinical-layer__close" data-clinical-close="{{ $pfx }}" aria-label="Fechar"></button>
  <div class="agenda-oc-clinical-layer__body csheet-modal-body" id="{{ $pfx }}ClinicalPanelBody">
    <h5 class="agenda-oc-clinical-layer__title" id="{{ $pfx }}ClinicalPanelTitle">Ficha técnica da marcação</h5>
  </div>
  <div class="agenda-oc-clinical-layer__footer">
    <button type="button" class="btn btn-light d-inline-flex align-items-center gap-2" data-clinical-close="{{ $pfx }}">
      <i class="ph ph-arrow-left" aria-hidden="true"></i>
      <span>Voltar</span>
    </button>
    <button type="button" class="btn btn-primary ms-auto" id="{{ $pfx }}ClinicalPanelSave" data-clinical-save="{{ $pfx }}">Guardar ficha</button>
  </div>
</div>
