<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Resumo empresa — {{ $appName ?? config('app.name') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; }
    .header { margin-bottom: 10px; }
    .header h1 { font-size: 14px; margin: 0 0 4px 0; }
    .meta { font-size: 7px; color: #666; margin-bottom: 8px; }
    .kpis { margin-bottom: 12px; }
    .kpis td { padding: 3px 8px 3px 0; }
    .kpis .label { color: #666; }
    .kpis .value { font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.data th, table.data td { padding: 4px 5px; border-bottom: 1px solid #ddd; vertical-align: top; }
    table.data th { background: #f5f5f5; font-size: 6px; text-transform: uppercase; text-align: left; }
    .text-end { text-align: right; }
    h2 { font-size: 10px; margin: 12px 0 6px 0; }
    .footer { margin-top: 10px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
@php
  $fmt = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $fmtPct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';
@endphp
  <div class="header">
    <h1>Resumo empresa</h1>
    <div class="meta">
      {{ $appName ?? config('app.name') }}
      · {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }}
      · Gerado em {{ now()->format('d/m/Y H:i') }}
      @if(!empty($storeNames))
        · Lojas: {{ implode(', ', $storeNames) }}
      @endif
    </div>
  </div>

  <table class="kpis">
    <tr>
      <td class="label">Faturação</td><td class="value">{{ $fmt($metrics['faturacao_total']) }}</td>
      <td class="label">Vendas</td><td class="value">{{ $metrics['num_vendas'] }}</td>
      <td class="label">Ticket médio</td><td class="value">{{ $fmt($metrics['ticket_medio']) }}</td>
      <td class="label">Ocupação</td><td class="value">{{ $fmtPct($metrics['taxa_ocupacao']) }}</td>
    </tr>
    <tr>
      <td class="label">Conclusão</td><td class="value">{{ $fmtPct($metrics['taxa_conclusao']) }}</td>
      <td class="label">No-shows</td><td class="value">{{ $metrics['noshows_total'] }} ({{ $fmtPct($metrics['taxa_noshow']) }})</td>
      <td class="label">Faltou / Canc. / Anul.</td>
      <td class="value" colspan="3">{{ $metrics['faltou_total'] }} / {{ $metrics['cancelado_total'] }} / {{ $metrics['anulado_total'] }}</td>
    </tr>
  </table>

  <h2>Por loja</h2>
  <table class="data">
    <thead>
      <tr>
        <th>Loja</th>
        <th class="text-end">Previsto</th>
        <th class="text-end">Feito</th>
        <th class="text-end">Por fazer</th>
        <th class="text-end">Ocupação</th>
        <th class="text-end">Faltou</th>
        <th class="text-end">Cancel.</th>
        <th class="text-end">Anul.</th>
        <th class="text-end">No-show %</th>
        <th class="text-end">Conclusão</th>
        <th class="text-end">Clientes</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($metrics['por_loja'] as $row)
        <tr>
          <td>{{ $row->nome }}</td>
          <td class="text-end">{{ $fmt($row->previsto) }}</td>
          <td class="text-end">{{ $fmt($row->vendas_feitas) }}</td>
          <td class="text-end">{{ $fmt($row->por_fazer) }}</td>
          <td class="text-end">{{ $fmtPct($row->taxa_ocupacao) }}</td>
          <td class="text-end">{{ $row->faltou }}</td>
          <td class="text-end">{{ $row->cancelado }}</td>
          <td class="text-end">{{ $row->anulado }}</td>
          <td class="text-end">{{ $fmtPct($row->taxa_noshow) }}</td>
          <td class="text-end">{{ $fmtPct($row->taxa_conclusao) }}</td>
          <td class="text-end">{{ $row->clientes_unicos }}</td>
        </tr>
      @empty
        <tr><td colspan="11">Sem dados no período.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2>Top serviços</h2>
  <table class="data">
    <thead><tr><th>Serviço</th><th class="text-end">Qtd</th><th class="text-end">Receita</th></tr></thead>
    <tbody>
      @forelse ($metrics['top_servicos'] as $svc)
        <tr>
          <td>{{ $svc->nome }}</td>
          <td class="text-end">{{ $svc->qtd }}</td>
          <td class="text-end">{{ $fmt($svc->receita) }}</td>
        </tr>
      @empty
        <tr><td colspan="3">Sem dados.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2>Comissões por técnico</h2>
  <table class="data">
    <thead><tr><th>Técnico</th><th class="text-end">Comissão c/ IVA</th></tr></thead>
    <tbody>
      @forelse ($metrics['comissoes_tecnicos'] as $tec)
        <tr>
          <td>{{ $tec->nome }}</td>
          <td class="text-end">{{ $fmt($tec->comissao) }}</td>
        </tr>
      @empty
        <tr><td colspan="2">Sem comissões.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">Relatório agregado — não altera a loja activa da sessão.</div>
</body>
</html>
