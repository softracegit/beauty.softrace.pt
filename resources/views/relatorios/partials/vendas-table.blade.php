@php
    $showClienteColumn = $showClienteColumn ?? true;
    $metaColspan = $showClienteColumn ? 6 : 5;
    $rowIdPrefix = $rowIdPrefix ?? 'vendas-acoes';
@endphp

@if($vendas->count() > 0)
  <div class="table-responsive">
    <table class="table table-sm table-hover table-striped vendas-report-table">
      <thead>
        <tr>
          <th>{{ $vendasDataColunaLabel ?? 'Data' }}</th>
          <th class="text-nowrap">Nº fatura</th>
          @if($showClienteColumn)
            <th>Cliente / NIF</th>
          @endif
          <th>Técnico</th>
          <th>Serviço</th>
          <th class="text-nowrap">Origem</th>
          <th class="text-end text-nowrap">Total</th>
          <th class="text-end text-nowrap">Taxas</th>
          <th class="text-end text-nowrap">Gorjeta</th>
          <th class="text-center text-nowrap">Estado</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($vendas as $linha)
          <tr>
            <td class="vendas-two-line text-nowrap">
              <div>{{ \Carbon\Carbon::parse($linha->data)->locale('pt')->translatedFormat('j F') }}</div>
              <small>{{ \Carbon\Carbon::parse($linha->data)->format('Y') }}</small>
            </td>
            <td class="vendas-fatura-cell">
              <div class="text-nowrap">{{ $linha->numero_fatura ?: '—' }}</div>
              @if(!empty($linha->fatura_subtitulo))
                <small class="vendas-fatura-sub text-muted">{{ $linha->fatura_subtitulo }}</small>
              @endif
            </td>
            @if($showClienteColumn)
              <td class="vendas-two-line">
                <div>{{ $linha->cliente }}</div>
                <small>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '-' }}</small>
              </td>
            @endif
            <td>{{ \Illuminate\Support\Str::of((string) $linha->tecnico)->before(' ') ?: '-' }}</td>
            <td class="vendas-servico-cell">
              @php
                $servicoNomes = (string) ($linha->servico_nomes ?? $linha->servico ?? '');
                $servicoNomes = $servicoNomes !== '' && $servicoNomes !== '—' ? $servicoNomes : '';
                $servicoNomesTrunc = $servicoNomes !== '' ? \Illuminate\Support\Str::limit($servicoNomes, 40) : '—';
                $categoria = trim((string) ($linha->categoria ?? ''));
                $categoria = $categoria !== '' && $categoria !== '—' ? $categoria : '';
              @endphp
              @if($categoria !== '')
                <small class="vendas-servico-categoria text-muted d-block">{{ $categoria }}</small>
              @endif
              <span class="vendas-servico-nomes" @if($servicoNomes !== '' && $servicoNomes !== $servicoNomesTrunc) title="{{ $servicoNomes }}" @endif>{{ $servicoNomesTrunc }}</span>
              @if(($linha->tipo_item ?? '') === \App\Models\SaleItem::TIPO_EXTRA)
                <span class="badge bg-info-light text-info ms-1">Extra</span>
              @endif
            </td>
            <td class="text-nowrap">{{ $linha->origem_marcacao ?? '—' }}</td>
            <td class="text-end text-nowrap">{{ number_format((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2, ',', ' ') }}€</td>
            <td class="text-end text-nowrap">
              @if((float) ($linha->taxas ?? 0) > 0)
                {{ number_format((float) $linha->taxas, 2, ',', ' ') }}€
              @else
                -
              @endif
            </td>
            <td class="text-end text-nowrap">
              @if((float) ($linha->gorjeta ?? 0) > 0)
                {{ number_format((float) $linha->gorjeta, 2, ',', ' ') }}€
              @else
                -
              @endif
            </td>
            <td class="text-center text-nowrap">
              @if(!empty($linha->is_anulado))
                <span class="badge bg-danger-subtle text-danger">Anulada</span>
              @elseif(($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO)
                <span class="badge bg-warning-subtle text-warning">Rascunho</span>
              @else
                <span class="badge bg-success-subtle text-success">Faturado</span>
              @endif
            </td>
            <td class="text-end p-1">
              <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-light dropdown-toggle js-vendas-dropdown" type="button" id="{{ $rowIdPrefix }}-{{ $loop->index }}" data-bs-toggle="dropdown" aria-expanded="false" title="Ações da fatura">
                  <i class="ph ph-dots-three-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="{{ $rowIdPrefix }}-{{ $loop->index }}">
                  @php
                    $isAnulado = !empty($linha->is_anulado);
                    $isDraft = ($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO;
                    $pdfUrl = (!$isDraft && $linha->sale && $linha->sale->vendus_document_id)
                      ? route('sales.vendus.pdf', $linha->sale)
                      : route('sales.pdf', $linha->sale);
                  @endphp
                  @if($isAnulado)
                    @if(!empty($linha->credit_note_pdf_url))
                      <li>
                        <a class="dropdown-item" href="{{ $linha->credit_note_pdf_url }}" target="_blank" rel="noopener">
                          <i class="ph ph-file-x me-2"></i>Ver nota de crédito
                        </a>
                      </li>
                    @else
                      <li><span class="dropdown-item-text text-muted small">Fatura anulada (sem nota de crédito)</span></li>
                    @endif
                  @elseif(!$isDraft)
                    <li>
                      <a class="dropdown-item" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
                        <i class="ph ph-file-pdf me-2"></i>Ver PDF
                      </a>
                    </li>
                  @endif
                  @if(!$isAnulado && $isDraft)
                    @php $saleRow = $linha->sale; @endphp
                    <li>
                      <button type="button" class="dropdown-item text-success js-sale-finalize"
                        data-sale-id="{{ $saleRow->id }}"
                        data-sale-amount="{{ (float) $saleRow->total }}"
                        data-client-name="{{ e($linha->cliente) }}"
                        data-client-email="{{ e($saleRow->client?->email ?? '') }}"
                        data-client-phone="{{ e($saleRow->client?->phone ?? '') }}"
                        data-client-nif="{{ e($linha->nif ?? '') }}"
                        data-modal-label="Faturar">
                        <i class="ph ph-check-circle me-2"></i>Faturar
                      </button>
                    </li>
                  @endif
                  @if($linha->calendar_event_id)
                    <li>
                      <a class="dropdown-item" href="{{ route('agenda.index', ['event' => $linha->calendar_event_id]) }}">
                        <i class="ph ph-calendar me-2"></i>Ver marcação
                      </a>
                    </li>
                  @else
                    <li><span class="dropdown-item-text text-muted small">Sem marcação associada</span></li>
                  @endif
                  @if(($linha->sale_status ?? '') === \App\Models\Sale::STATUS_PAGO && !$isDraft)
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <button type="button" class="dropdown-item text-danger js-sale-revert" data-revert-url="{{ route('sales.revert', $linha->sale) }}">
                        <i class="ph ph-arrow-counter-clockwise me-2"></i>Anular fatura
                      </button>
                    </li>
                  @endif
                </ul>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
      <tfoot class="table-light">
        <tr class="fw-semibold">
          <td colspan="{{ $metaColspan }}" class="text-end"></td>
          <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_valor_com_gorjeta'] ?? (($vendasTotais['total_valor'] ?? 0) + ($vendasTotais['total_gorjeta'] ?? 0)), 2, ',', ' ') }}€</td>
          <td class="text-end text-nowrap">
            @if((float) ($vendasTotais['total_taxas'] ?? 0) > 0)
              {{ number_format((float) ($vendasTotais['total_taxas'] ?? 0), 2, ',', ' ') }}€
            @else
              -
            @endif
          </td>
          <td class="text-end text-nowrap">
            @if((float) ($vendasTotais['total_gorjeta'] ?? 0) > 0)
              {{ number_format((float) ($vendasTotais['total_gorjeta'] ?? 0), 2, ',', ' ') }}€
            @else
              -
            @endif
          </td>
          <td></td>
          <td></td>
        </tr>
        <tr class="fw-bold vendas-tfoot-total">
          <td colspan="{{ $metaColspan }}" class="text-end">Total</td>
          <td class="text-end text-nowrap">{{ number_format((float) ($vendasTotais['total_absoluto'] ?? 0), 2, ',', ' ') }}€</td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  @if(method_exists($vendas, 'links'))
    @include('relatorios.partials.pagination', ['paginator' => $vendas])
  @endif
@else
  <p class="text-muted text-center py-3">{{ $emptyMessage ?? 'Nenhuma linha de venda nos filtros selecionados.' }}</p>
@endif
