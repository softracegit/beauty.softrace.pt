<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Comissões — {{ config('app.name') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; }
    .header { margin-bottom: 12px; }
    .header h1 { font-size: 15px; margin: 0 0 6px 0; }
    .header .meta { font-size: 7px; color: #666; margin-bottom: 8px; }
    .filtros { font-size: 7px; color: #555; margin-bottom: 10px; line-height: 1.45; }
    .filtros strong { color: #333; }
    .nota { font-size: 7px; color: #666; margin-bottom: 10px; line-height: 1.45; }
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
    $comIva = $comissoesComIva ?? true;
    $ivaLabel = $comIva ? 'c/ IVA' : 's/ IVA';
    $columns = $pdfColumns ?? array_keys($pdfColumnLabels ?? []);
    $labels = $pdfColumnLabels ?? [];
    $colCount = count($columns);
    $totalValue = (float) ($comIva ? ($comissoesTotais['total_comissao_com_iva'] ?? 0) : ($comissoesTotais['total_comissao_sem_iva'] ?? 0));
  @endphp
  <div class="header">
    <h1>Relatório de comissões</h1>
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

  @if(!empty($usesHistoricalFooter))
    <div class="nota">
      <strong>Nota:</strong> o total de comissões a pagar inclui valores históricos do Zappy (até 31/05/2026).
      As linhas individuais reflectem o cálculo CRM; a partir de 01/06/2026 linhas e total coincidem.
    </div>
  @endif

  <table>
    <thead>
      <tr>
        @foreach($columns as $colKey)
          <th @class([
            'text-end text-nowrap' => in_array($colKey, ['valor_servico', 'comissao_taxa', 'valor_comissao'], true),
          ])>{{ $labels[$colKey] ?? $colKey }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($linhas as $linha)
        <tr>
          @foreach($columns as $colKey)
            @switch($colKey)
              @case('data_venda')
                <td class="text-nowrap">{{ $linha->data_emissao ? \App\Support\DateTimeDisplay::businessDate($linha->data_emissao) : '—' }}</td>
                @break
              @case('numero_fatura')
                <td class="text-nowrap">{{ $linha->numero_fatura ?: '—' }}</td>
                @break
              @case('colaborador')
                <td>{{ $linha->tecnico }}</td>
                @break
              @case('cliente')
                <td>{{ $linha->cliente }}</td>
                @break
              @case('servico')
                <td style="font-size:7px;">{{ $linha->servico }}</td>
                @break
              @case('valor_servico')
                <td class="text-end text-nowrap">{{ number_format((float) ($comIva ? $linha->valor_com_iva : $linha->valor_sem_iva), 2, ',', ' ') }}€</td>
                @break
              @case('comissao_taxa')
                <td class="text-end text-nowrap">{{ $linha->comissao_taxa ?? '—' }}</td>
                @break
              @case('valor_comissao')
                <td class="text-end text-nowrap">{{ number_format((float) ($comIva ? $linha->comissao_com_iva : $linha->comissao_sem_iva), 2, ',', ' ') }}€</td>
                @break
            @endswitch
          @endforeach
        </tr>
      @endforeach
    </tbody>
    @if(!empty($comissoesTotais) && $colCount > 0)
      <tfoot>
        <tr>
          @if($colCount === 1)
            <td class="text-end text-nowrap" style="font-weight:bold;">Total comissões a pagar ({{ $ivaLabel }}): {{ number_format($totalValue, 2, ',', ' ') }}€</td>
          @else
            <td colspan="{{ $colCount - 1 }}" class="text-end" style="font-weight:bold;">Total comissões a pagar ({{ $ivaLabel }})</td>
            <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($totalValue, 2, ',', ' ') }}€</td>
          @endif
        </tr>
      </tfoot>
    @endif
  </table>

  @if($linhas->isEmpty())
    <p style="margin-top:10px; font-size:8px; color:#666;">Nenhum registo para os filtros selecionados.</p>
  @endif

  <div class="footer">
    Relatório de apoio à gestão de comissões. Valores {{ $ivaLabel }}.
  </div>
</body>
</html>
