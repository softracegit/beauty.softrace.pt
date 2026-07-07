<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Marcações — {{ config('app.name') }}</title>
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
    $precoIdx = array_search('preco', $columns, true);
    $showFooter = !empty($marcacoesTotais) && $colCount > 0 && ($precoIdx !== false || in_array('notas', $columns, true));
  @endphp
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
        @foreach($columns as $colKey)
          <th @class([
            'text-end text-nowrap' => $colKey === 'preco',
          ])>{{ $labels[$colKey] ?? $colKey }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($marcacoes as $ev)
        @php
          $totalPreco = $ev->eventServiceItems->sum(function ($es) {
            return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
          });
          $categoria = \App\Support\MarcacoesReportEstadoFilter::eventRowCategoriasLabel($ev);
          $servicoNomes = \App\Support\MarcacoesReportEstadoFilter::eventRowServicesLabel($ev);
        @endphp
        <tr>
          @foreach($columns as $colKey)
            @switch($colKey)
              @case('data')
              @case('data_hora')
                <td class="text-nowrap">
                  {{ $ev->start_at->locale('pt')->translatedFormat('j F') }}<br>
                  <span style="color:#666;font-size:6px;">{{ $ev->start_at->format('Y') }} · {{ $ev->start_at->format('H:i') }}</span>
                </td>
                @break
              @case('estado')
                <td>{{ \App\Support\MarcacoesReportEstadoFilter::eventRowStatusLabel($ev) }}</td>
                @break
              @case('cliente')
                <td>{{ $ev->client?->name ?? '—' }}</td>
                @break
              @case('tecnico')
                <td>{{ $ev->user?->name ?? '—' }}</td>
                @break
              @case('servico')
              @case('servicos')
                <td style="font-size:7px;">
                  @if($categoria !== '')
                    <span style="color:#666;font-size:6px;display:block;">{{ $categoria }}</span>
                  @endif
                  {{ $servicoNomes }}
                </td>
                @break
              @case('categoria')
                <td style="font-size:7px;">
                  @if($categoria !== '')
                    <span style="color:#666;font-size:6px;display:block;">{{ $categoria }}</span>
                  @endif
                  {{ $servicoNomes }}
                </td>
                @break
              @case('origem_marcacao')
                <td>{{ \App\Support\MarcacoesReportEstadoFilter::eventRowOrigemLabel($ev) }}</td>
                @break
              @case('preco')
                <td class="text-end text-nowrap">{{ number_format($totalPreco, 2, ',', ' ') }}€</td>
                @break
              @case('notas')
                <td style="font-size:7px;">{{ $ev->description ? \Illuminate\Support\Str::limit($ev->description, 120) : '—' }}</td>
                @break
            @endswitch
          @endforeach
        </tr>
      @endforeach
    </tbody>
    @if($showFooter)
      <tfoot>
        <tr>
          @if($precoIdx !== false && $precoIdx > 0)
            <td colspan="{{ $precoIdx }}" class="text-end" style="font-weight:bold;">Total</td>
          @endif
          @foreach($columns as $idx => $colKey)
            @if($precoIdx !== false && $idx < $precoIdx)
              @continue
            @endif
            @switch($colKey)
              @case('preco')
                <td class="text-end text-nowrap" style="font-weight:bold;">{{ number_format($marcacoesTotais['preco_total'] ?? 0, 2, ',', ' ') }}€</td>
                @break
              @case('notas')
                <td style="font-weight:bold;font-size:7px;">{{ ($marcacoesTotais['servicos_count'] ?? 0) }} serviço(s)</td>
                @break
              @default
                @if($precoIdx === false && $idx === 0)
                  <td class="text-end" style="font-weight:bold;">Total</td>
                @else
                  <td></td>
                @endif
            @endswitch
          @endforeach
        </tr>
      </tfoot>
    @endif
  </table>

  @if($marcacoes->isEmpty())
    <p style="margin-top:10px; font-size:8px; color:#666;">Nenhum registo para os filtros selecionados.</p>
  @endif

  <div class="footer">
    Documento para impressão ou arquivo. Os valores refletem os filtros indicados acima.
  </div>
</body>
</html>
