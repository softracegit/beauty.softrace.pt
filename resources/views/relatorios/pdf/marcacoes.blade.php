<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Marcações — {{ config('app.name') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #333; }
    .header { margin-bottom: 14px; }
    .header h1 { font-size: 16px; margin: 0 0 6px 0; }
    .header .meta { font-size: 8px; color: #666; margin-bottom: 8px; }
    .filtros { font-size: 8px; color: #555; margin-bottom: 12px; line-height: 1.5; }
    .filtros strong { color: #333; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 5px 6px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
    th { background: #f5f5f5; font-size: 7px; text-transform: uppercase; letter-spacing: 0.02em; }
    .text-end { text-align: right; }
    .text-nowrap { white-space: nowrap; }
    .small { font-size: 8px; color: #555; }
    .footer { margin-top: 16px; font-size: 8px; color: #888; }
    .servicos-cell { max-width: 140px; word-wrap: break-word; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Relatório de marcações</h1>
    <div class="meta">{{ $appName ?? config('app.name') }} · Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $totalRegistos }} registo(s)</div>
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
        <th style="width:11%">Data/Hora</th>
        <th style="width:9%">Estado</th>
        <th style="width:14%">Cliente</th>
        <th style="width:12%">Técnico</th>
        <th style="width:18%">Serviços</th>
        <th style="width:12%">Categoria</th>
        <th class="text-end text-nowrap" style="width:8%">Preço</th>
        <th style="width:18%">Notas</th>
      </tr>
    </thead>
    <tbody>
      @foreach($marcacoes as $ev)
        @php
          $totalPreco = $ev->eventServiceItems->sum(function ($es) {
            return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
          });
          $services = \App\Support\MarcacoesReportEstadoFilter::eventRowServicesLabel($ev);
          if ($ev->event_type === \App\Models\CalendarEvent::TYPE_TEMPO_PESSOAL) {
            $categorias = '—';
          } else {
            $categorias = $ev->eventServiceItems
              ->map(fn ($es) => $es->service?->category?->name)
              ->map(fn ($n) => $n !== null && $n !== '' ? $n : '—')
              ->implode(', ');
          }
        @endphp
        <tr>
          <td>{{ \App\Support\DateTimeDisplay::business($ev->start_at) }}</td>
          <td>{{ \App\Support\MarcacoesReportEstadoFilter::eventRowStatusLabel($ev) }}</td>
          <td>{{ $ev->client?->name ?? '—' }}</td>
          <td>{{ $ev->user?->name ?? '—' }}</td>
          <td class="servicos-cell small">{{ $services }}</td>
          <td class="servicos-cell small">{{ $categorias !== '' ? $categorias : '—' }}</td>
          <td class="text-end text-nowrap">{{ number_format($totalPreco, 2, ',', ' ') }}€</td>
          <td class="small">{{ $ev->description ? \Illuminate\Support\Str::limit($ev->description, 120) : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
    @if(!empty($marcacoesTotais))
      <tfoot>
        <tr>
          <td colspan="5" class="text-end" style="font-weight:bold;">Total</td>
          <td>—</td>
          <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($marcacoesTotais['preco_total'] ?? 0, 2, ',', ' ') }}€</td>
          <td class="small" style="font-weight:bold;">{{ ($marcacoesTotais['servicos_count'] ?? 0) }} serviço(s)</td>
        </tr>
      </tfoot>
    @endif
  </table>

  @if($marcacoes->isEmpty())
    <p style="margin-top:12px; font-size:9px; color:#666;">Nenhum registo para os filtros selecionados.</p>
  @endif

  <div class="footer">
    Documento para impressão ou arquivo. Os valores refletem os filtros indicados acima.
  </div>
</body>
</html>
