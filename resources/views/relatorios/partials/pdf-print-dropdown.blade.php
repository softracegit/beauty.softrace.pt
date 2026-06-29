@php
  $pdfPrintUrl = $pdfPrintUrl ?? '';
  $pdfColumnOptions = $pdfColumnOptions ?? [];
  $pdfPrintScope = $pdfPrintScope ?? 'relatorio';
  $pdfColsParam = $pdfColsParam ?? 'pdf_cols';
  $pdfOrientationParam = $pdfOrientationParam ?? 'pdf_orientation';
  $columnOrder = array_keys($pdfColumnOptions);
@endphp
<div class="dropdown js-relatorio-pdf-print" data-scope="{{ $pdfPrintScope }}" data-cols-param="{{ $pdfColsParam }}" data-orientation-param="{{ $pdfOrientationParam }}" data-column-order="{{ json_encode($columnOrder) }}">
  <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    <i class="ph ph-printer me-1"></i> Imprimir
  </button>
  <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm relatorio-print-dropdown">
    <div class="small fw-semibold mb-2">Colunas do PDF</div>
    @foreach($pdfColumnOptions as $colKey => $colLabel)
      <div class="form-check mb-1">
        <input class="form-check-input js-relatorio-pdf-col" type="checkbox" value="{{ $colKey }}" id="{{ $pdfPrintScope }}PdfCol_{{ $colKey }}" checked>
        <label class="form-check-label small" for="{{ $pdfPrintScope }}PdfCol_{{ $colKey }}">{{ $colLabel }}</label>
      </div>
    @endforeach
    <div class="relatorio-pdf-orientation-group" role="radiogroup" aria-label="Orientação do PDF">
      <label class="relatorio-pdf-orientation-option">
        <input type="radio" name="{{ $pdfPrintScope }}_pdf_orientation_ui" value="portrait" class="visually-hidden js-relatorio-pdf-orientation">
        <span class="relatorio-pdf-orientation-icon" aria-hidden="true"><i class="ph ph-file"></i></span>
        <span class="relatorio-pdf-orientation-label">Vertical</span>
      </label>
      <label class="relatorio-pdf-orientation-option">
        <input type="radio" name="{{ $pdfPrintScope }}_pdf_orientation_ui" value="landscape" class="visually-hidden js-relatorio-pdf-orientation" checked>
        <span class="relatorio-pdf-orientation-icon relatorio-pdf-orientation-icon--landscape" aria-hidden="true"><i class="ph ph-file"></i></span>
        <span class="relatorio-pdf-orientation-label">Horizontal</span>
      </label>
    </div>
    <a href="{{ $pdfPrintUrl }}"
      class="btn btn-primary btn-sm w-100 mt-3 js-relatorio-pdf-print-link"
      data-base-href="{{ $pdfPrintUrl }}"
      target="_blank"
      rel="noopener">
      Gerar PDF
    </a>
    <p class="small text-muted mb-0 mt-2 js-relatorio-pdf-cols-warning d-none">Seleccione pelo menos uma coluna.</p>
  </div>
</div>
