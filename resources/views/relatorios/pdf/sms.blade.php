<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>SMS — {{ config('app.name') }}</title>
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
    .text-nowrap { white-space: nowrap; }
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
  @php
    $columns = $pdfColumns ?? array_keys($pdfColumnLabels ?? []);
    $labels = $pdfColumnLabels ?? [];
    $typeLabels = $typeLabels ?? \App\Models\SmsMessage::typeLabels();
    $storeId = $displayStoreId ?? null;
  @endphp
  <div class="header">
    <h1>Relatório de SMS</h1>
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
          <th>{{ $labels[$colKey] ?? $colKey }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($messages as $sms)
        @php
          $clientName = $sms->client_name ?: ($sms->client?->name ?? '—');
        @endphp
        <tr>
          @foreach($columns as $colKey)
            @switch($colKey)
              @case('data')
                <td class="text-nowrap">{{ \App\Support\DateTimeDisplay::formatInstant($sms->sent_at, $storeId, 'd/m/Y H:i') }}</td>
                @break
              @case('tipo')
                <td class="text-nowrap">{{ $typeLabels[$sms->type] ?? $sms->type }}</td>
                @break
              @case('remetente')
                <td class="text-nowrap">{{ $sms->from_phone }}</td>
                @break
              @case('cliente')
                <td>{{ $clientName }}</td>
                @break
              @case('telemovel')
                <td class="text-nowrap">{{ \App\Support\PhoneDisplay::formatInternational($sms->to_phone) ?? $sms->to_phone }}</td>
                @break
              @case('mensagem')
                <td style="font-size:7px;">{{ $sms->body }}</td>
                @break
            @endswitch
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">Relatório SMS · {{ $appName ?? config('app.name') }}</div>
</body>
</html>
