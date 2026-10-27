{{--
  Notas e registo técnico desta visita (só staff).
  Prefixo: agendaOc | eventDetailOc
--}}
@php
  $pfx = $prefix ?? 'agendaOc';
@endphp
<div class="agenda-oc-field agenda-oc-visit-block">
  <div class="agenda-oc-info-card">
    <div class="agenda-oc-info-card__body">
      <div class="agenda-oc-visit-block__section">
        <label class="form-label fw-semibold mb-1" for="{{ $pfx }}Obs">Notas da marcação</label>
        <textarea class="form-control form-control-sm" id="{{ $pfx }}Obs" name="description" rows="3" placeholder="Ex: chega 10 min tarde · trazer amostra · avisar receção…"></textarea>
      </div>

      <div class="agenda-oc-visit-block__section agenda-oc-field--technical">
        <div class="agenda-oc-tech-field">
          <label class="form-label small text-muted" for="{{ $pfx }}TechnicalFormula">Fórmula</label>
          <textarea class="form-control form-control-sm" id="{{ $pfx }}TechnicalFormula" name="technical_formula" rows="2" placeholder="Ex: cor / oxidante / tempo de pose"></textarea>
        </div>
        <div class="agenda-oc-tech-field">
          <label class="form-label small text-muted" for="{{ $pfx }}TechnicalProducts">Produtos</label>
          <textarea class="form-control form-control-sm" id="{{ $pfx }}TechnicalProducts" name="technical_products" rows="2" placeholder="Produtos usados nesta visita"></textarea>
        </div>
        <div class="agenda-oc-tech-field">
          <label class="form-label small text-muted" for="{{ $pfx }}TechnicalNotes">Notas técnicas</label>
          <textarea class="form-control form-control-sm" id="{{ $pfx }}TechnicalNotes" name="technical_notes" rows="2" placeholder="Observações técnicas desta visita"></textarea>
        </div>
      </div>
    </div>
  </div>
</div>
