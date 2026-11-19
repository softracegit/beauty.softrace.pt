{{--
  Ficha técnica (resumo) + Observações da marcação — lado a lado quando há ficha.
  Prefixo: agendaOc | eventDetailOc
--}}
@php
  $pfx = $prefix ?? 'agendaOc';
@endphp
<div class="agenda-oc-field agenda-oc-notes-row">
  <div class="agenda-oc-clinical-progress-field d-none" id="{{ $pfx }}ClinicalSheetWrap" aria-hidden="true" hidden>
    <label class="form-label fw-semibold" id="{{ $pfx }}ClinicalProgressLabel">Ficha técnica da marcação</label>
    <button
      type="button"
      class="csheet-progress-card agenda-oc-clinical-edit-btn is-awaiting-services"
      id="{{ $pfx }}ClinicalProgressBtn"
      data-clinical-prefix="{{ $pfx }}"
      aria-labelledby="{{ $pfx }}ClinicalProgressLabel"
      aria-disabled="true"
      disabled
    >
      <span class="csheet-progress-card__body">
        <span class="csheet-progress-ring" aria-hidden="true">
          <svg class="csheet-progress-ring__svg" viewBox="0 0 36 36" focusable="false">
            <circle class="csheet-progress-ring__track" cx="18" cy="18" r="15.5" fill="none" />
            <circle class="csheet-progress-ring__bar is-empty" id="{{ $pfx }}ClinicalProgressBar" cx="18" cy="18" r="15.5" fill="none" pathLength="100" stroke-dasharray="0 100" />
          </svg>
          <span class="csheet-progress-ring__pct" id="{{ $pfx }}ClinicalProgressPct">0%</span>
        </span>
        <span class="csheet-progress-card__meta">
          <span class="csheet-progress-card__count" id="{{ $pfx }}ClinicalProgressCount">Por favor adicione pelo menos um serviço à marcação</span>
          <span class="csheet-progress-card__sub d-none" id="{{ $pfx }}ClinicalProgressSub">preenchidos</span>
        </span>
        <i class="ph ph-caret-right csheet-progress-card__chevron" aria-hidden="true"></i>
      </span>
    </button>
    <div class="d-none" id="{{ $pfx }}ClinicalSheetMount" aria-hidden="true"></div>
    <div class="d-none" id="{{ $pfx }}ClinicalPrefsHint" aria-hidden="true"></div>
  </div>
  <div class="agenda-oc-obs-field">
    <label class="form-label fw-semibold" for="{{ $pfx }}Obs">Observações da marcação</label>
    <textarea class="form-control form-control-sm agenda-oc-obs-field__input" id="{{ $pfx }}Obs" name="description" rows="3" placeholder="Ex: chega 10 min tarde · trazer amostra · avisar receção…"></textarea>
  </div>
</div>
<div class="d-none" aria-hidden="true">
  <div id="{{ $pfx }}TechnicalFallback">
    <textarea id="{{ $pfx }}TechnicalFormula" name="technical_formula" tabindex="-1"></textarea>
    <textarea id="{{ $pfx }}TechnicalProducts" name="technical_products" tabindex="-1"></textarea>
    <textarea id="{{ $pfx }}TechnicalNotes" name="technical_notes" tabindex="-1"></textarea>
  </div>
</div>
