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
    .text-center { text-align: center; }
    .text-nowrap { white-space: nowrap; }
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
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
        <th style="width:8%">Data</th>
        <th style="width:10%">N.º fatura</th>
        <th style="width:12%">Cliente</th>
        <th style="width:8%">NIF</th>
        <th style="width:10%">Técnico</th>
        <th style="width:15%">Serviço</th>
        <th class="text-end text-nowrap" style="width:8%">Total</th>
        <th class="text-end text-nowrap" style="width:7%">Taxas</th>
        <th class="text-end text-nowrap" style="width:7%">Gorjeta</th>
        <th class="text-end text-nowrap" style="width:7%">Em dívida</th>
        <th style="width:6%">Estado fatura</th>
      </tr>
    </thead>
    <tbody>
      @foreach($linhas as $linha)
        <tr>
          <td>{{ $linha->data->format('d/m/Y') }}</td>
          <td>{{ $linha->numero_fatura }}</td>
          <td>{{ $linha->cliente }}</td>
          <td>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '—' }}</td>
          <td>{{ $linha->tecnico }}</td>
          <td>
            {{ $linha->servico_nomes ?? $linha->servico }}
            @if(!empty($linha->servico_subtitulo))
              <br><span style="color:#666;font-size:6px;">{{ $linha->servico_subtitulo }}</span>
            @endif
          </td>
          <td class="text-end text-nowrap">{{ number_format((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2, ',', ' ') }}€</td>
          <td class="text-end text-nowrap">@if((float) ($linha->taxas ?? 0) > 0){{ number_format((float) $linha->taxas, 2, ',', ' ') }}€@else—@endif</td>
          <td class="text-end text-nowrap">@if((float)($linha->gorjeta ?? 0) > 0){{ number_format((float) $linha->gorjeta, 2, ',', ' ') }}€@else—@endif</td>
          <td class="text-end text-nowrap">@if((float)($linha->pendente ?? 0) > 0){{ number_format((float) $linha->pendente, 2, ',', ' ') }}€@else—@endif</td>
          <td>
            @if(($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO)
              Rascunho
            @else
              Faturado
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
    @if(!empty($vendasTotais))
      <tfoot>
        <tr>
          <td colspan="6" class="text-end" style="font-weight:bold;"></td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($vendasTotais['total_valor_com_gorjeta'] ?? (($vendasTotais['total_valor'] ?? 0) + ($vendasTotais['total_gorjeta'] ?? 0)), 2, ',', ' ') }}€</td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format((float) ($vendasTotais['total_taxas'] ?? 0), 2, ',', ' ') }}€</td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($vendasTotais['total_gorjeta'] ?? 0, 2, ',', ' ') }}€</td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($vendasTotais['total_divida'] ?? 0, 2, ',', ' ') }}€</td>
          <td></td>
        </tr>
        <tr>
          <td colspan="6" class="text-end" style="font-weight:bold;">Total</td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format((float) ($vendasTotais['total_absoluto'] ?? 0), 2, ',', ' ') }}€</td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
        </tr>
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
