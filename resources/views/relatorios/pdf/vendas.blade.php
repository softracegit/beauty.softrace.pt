<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Vendas — {{ config('app.name') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; }
    .header { margin-bottom: 12px; }
    .header h1 { font-size: 15px; margin: 0 0 6px 0; }
    .header .meta { font-size: 7px; color: #666; margin-bottom: 8px; }
    .filtros { font-size: 7px; color: #555; margin-bottom: 10px; line-height: 1.45; }
    .filtros strong { color: #333; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 4px 5px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
    th { background: #f5f5f5; font-size: 6px; text-transform: uppercase; }
    .text-end { text-align: right; }
    .text-nowrap { white-space: nowrap; }
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
  @php
    $columns = $pdfColumns ?? array_keys($pdfColumnLabels ?? []);
    $labels = $pdfColumnLabels ?? [];
    $colCount = count($columns);
    $dataLabel = $vendasDataColunaLabel ?? ($labels['data'] ?? 'Data');
    $moneyCols = ['total', 'taxas', 'gorjeta'];
    $showSubtotalRow = !empty($vendasTotais) && array_intersect($columns, $moneyCols) !== [];
    $showTotalRow = !empty($vendasTotais) && in_array('total', $columns, true);
  @endphp
  <div class="header">
    <h1>Relatório de vendas</h1>
    <div class="meta">{{ $appName ?? config('app.name') }} · Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $totalLinhas }} linha(s)</div>
  </div>

  @if(!empty($filtrosLinhas))
    <div class="filtros">
      <strong>Filtros aplicados:</strong><br>
      @foreach($filtrosLinhas as $linha)
        {{ $linha }}@if(!$loop->last)<br>@endif
      @endforeach
    </div>
  @endif

  <table>
    <thead>
      <tr>
        @foreach($columns as $colKey)
          <th @class([
            'text-end text-nowrap' => in_array($colKey, $moneyCols, true),
          ])>{{ $colKey === 'data' ? $dataLabel : ($labels[$colKey] ?? $colKey) }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($linhas as $linha)
        <tr>
          @foreach($columns as $colKey)
            @switch($colKey)
              @case('data')
                <td class="text-nowrap">{{ $linha->data->format('d/m/Y') }}</td>
                @break
              @case('cliente')
                <td>{{ $linha->cliente }}</td>
                @break
              @case('nif')
                <td>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '—' }}</td>
                @break
              @case('tecnico')
                <td>{{ $linha->tecnico }}</td>
                @break
              @case('servico')
                <td style="font-size:7px;">
                  @php
                    $categoria = trim((string) ($linha->categoria ?? ''));
                    $categoria = $categoria !== '' && $categoria !== '—' ? $categoria : '';
                  @endphp
                  @if($categoria !== '')
                    <span style="color:#666;font-size:6px;display:block;">{{ $categoria }}</span>
                  @endif
                  {{ $linha->servico_nomes ?? $linha->servico }}
                </td>
                @break
              @case('numero_fatura')
                <td>
                  <span class="text-nowrap">{{ $linha->numero_fatura ?: '—' }}</span>
                  @if(!empty($linha->fatura_subtitulo))
                    <br><span style="color:#666;font-size:6px;">{{ $linha->fatura_subtitulo }}</span>
                  @endif
                </td>
                @break
              @case('origem_marcacao')
                <td>{{ $linha->origem_marcacao ?? '—' }}</td>
                @break
              @case('total')
                <td class="text-end text-nowrap">{{ number_format((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2, ',', ' ') }}€</td>
                @break
              @case('taxas')
                <td class="text-end text-nowrap">@if((float) ($linha->taxas ?? 0) > 0){{ number_format((float) $linha->taxas, 2, ',', ' ') }}€@else—@endif</td>
                @break
              @case('gorjeta')
                <td class="text-end text-nowrap">@if((float)($linha->gorjeta ?? 0) > 0){{ number_format((float) $linha->gorjeta, 2, ',', ' ') }}€@else—@endif</td>
                @break
              @case('estado_fatura')
                <td>
                  @if(!empty($linha->is_anulado))
                    Anulada
                  @elseif(($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO)
                    Rascunho
                  @else
                    Faturado
                  @endif
                </td>
                @break
            @endswitch
          @endforeach
        </tr>
      @endforeach
    </tbody>
    @if($showSubtotalRow)
      <tfoot>
        <tr>
          @foreach($columns as $colKey)
            @switch($colKey)
              @case('total')
                <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($vendasTotais['total_valor_com_gorjeta'] ?? (($vendasTotais['total_valor'] ?? 0) + ($vendasTotais['total_gorjeta'] ?? 0)), 2, ',', ' ') }}€</td>
                @break
              @case('taxas')
                <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format((float) ($vendasTotais['total_taxas'] ?? 0), 2, ',', ' ') }}€</td>
                @break
              @case('gorjeta')
                <td class="text-end text-nowrap" style="font-weight:bold;">@if((float)($vendasTotais['total_gorjeta'] ?? 0) > 0){{ number_format((float) ($vendasTotais['total_gorjeta'] ?? 0), 2, ',', ' ') }}€@else—@endif</td>
                @break
              @default
                <td></td>
            @endswitch
          @endforeach
        </tr>
        @if($showTotalRow)
          <tr>
            @php $totalIdx = array_search('total', $columns, true); @endphp
            @if($totalIdx > 0)
              <td colspan="{{ $totalIdx }}" class="text-end" style="font-weight:bold;">Total</td>
            @endif
            @foreach(array_slice($columns, $totalIdx !== false ? $totalIdx : 0) as $colKey)
              @if($colKey === 'total')
                <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format((float) ($vendasTotais['total_absoluto'] ?? 0), 2, ',', ' ') }}€</td>
              @else
                <td></td>
              @endif
            @endforeach
          </tr>
        @endif
      </tfoot>
    @endif
  </table>

  @if($linhas->isEmpty())
    <p style="margin-top:10px; font-size:8px; color:#666;">Nenhum registo para os filtros selecionados.</p>
  @endif

  <div class="footer">
    Relatório de apoio. Use o PDF individual de cada fatura no CRM para documento oficial.
  </div>
</body>
</html>
