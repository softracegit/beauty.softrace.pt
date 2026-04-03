<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Lista de clientes — {{ config('app.name') }}</title>
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
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Lista de clientes</h1>
    <div class="meta">{{ $appName ?? config('app.name') }} · Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $total }} cliente(s)</div>
  </div>

  @if(!empty($filtrosLinhas))
    <div class="filtros">
      <strong>Filtros:</strong><br>
      @foreach($filtrosLinhas as $linha)
        {{ $linha }}@if(!$loop->last)<br>@endif
      @endforeach
    </div>
  @else
    <div class="filtros"><strong>Filtros:</strong> Todos os clientes (sem pesquisa).</div>
  @endif

  <table>
    <thead>
      <tr>
        <th style="width:18%">Nome</th>
        <th style="width:16%">Email</th>
        <th style="width:11%">Telefone</th>
        <th style="width:9%">NIF</th>
        <th style="width:12%">Localidade</th>
        <th style="width:16%">Morada</th>
        <th style="width:8%">Cód. postal</th>
        <th style="width:10%">Registado em</th>
      </tr>
    </thead>
    <tbody>
      @foreach($clients as $c)
        <tr>
          <td>{{ $c->name }}</td>
          <td>{{ $c->email ?? '—' }}</td>
          <td>{{ $c->formatted_phone ?? '—' }}</td>
          <td>{{ $c->nif ?? '—' }}</td>
          <td>{{ $c->locality ?? '—' }}</td>
          <td>{{ $c->address ?? '—' }}</td>
          <td>{{ $c->postal_code ?? '—' }}</td>
          <td>{{ $c->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if($clients->isEmpty())
    <p style="margin-top:10px; font-size:8px; color:#666;">Nenhum cliente encontrado para os critérios indicados.</p>
  @endif

  <div class="footer">Documento para impressão ou arquivo.</div>
</body>
</html>
