<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Catálogo de serviços — {{ $storeName }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #333; }
    .header { margin-bottom: 14px; }
    .header h1 { font-size: 16px; margin: 0 0 4px 0; }
    .header .meta { font-size: 8px; color: #666; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 5px 6px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
    th { background: #f5f5f5; font-size: 7px; text-transform: uppercase; color: #444; }
    tr.category-row td {
      background: #eef2f7;
      font-weight: bold;
      font-size: 10px;
      border-bottom: 1px solid #cbd5e1;
      padding-top: 10px;
    }
    tr.category-row:first-child td { padding-top: 5px; }
    .text-end { text-align: right; white-space: nowrap; }
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Catálogo de serviços — {{ $storeName }}</h1>
    <div class="meta">{{ $appName }} · {{ $scopeLabel ?? 'Todos os serviços' }} · Preços online · Gerado em {{ $generatedAt }} · {{ $totalRows }} linha(s)</div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:22%">Categoria</th>
        <th style="width:43%">Serviço</th>
        <th class="text-end" style="width:17%">Preço online</th>
        <th class="text-end" style="width:18%">Duração</th>
      </tr>
    </thead>
    <tbody>
      @forelse($categories as $category)
        <tr class="category-row">
          <td colspan="4">{{ $category->name }}</td>
        </tr>
        @foreach($category->services as $service)
          @if($service->options->isNotEmpty())
            @foreach($service->options as $option)
              <tr>
                <td>{{ $category->name }}</td>
                <td>{{ $service->name }} — {{ $option->name }}</td>
                <td class="text-end">{{ $option->online_price !== null ? $option->formatted_online_price : '—' }}</td>
                <td class="text-end">{{ $option->formatted_duration }}</td>
              </tr>
            @endforeach
          @else
            <tr>
              <td>{{ $category->name }}</td>
              <td>{{ $service->name }}</td>
              <td class="text-end">{{ $service->online_price !== null ? $service->formatted_online_price : '—' }}</td>
              <td class="text-end">{{ $service->formatted_duration }}</td>
            </tr>
          @endif
        @endforeach
      @empty
        <tr>
          <td colspan="4">Nenhum serviço encontrado.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">Documento gerado automaticamente a partir do catálogo da loja.</div>
</body>
</html>
