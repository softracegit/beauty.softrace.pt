@php
    $showClienteColumn = $showClienteColumn ?? true;
    $showNotasColumn = $showNotasColumn ?? true;
    $showPrecoColumn = $showPrecoColumn ?? true;
    $showFooter = $showFooter ?? true;
    $actionMode = $actionMode ?? 'modal';
    $emptyMessage = $emptyMessage ?? 'Nenhuma marcação nos filtros selecionados.';
    $metaColspan = ($showClienteColumn ? 1 : 0) + 5;
@endphp

@if($marcacoes->count() > 0)
  <div class="table-responsive">
    <table class="table table-sm table-hover table-striped vendas-report-table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Estado</th>
          @if($showClienteColumn)
            <th>Cliente</th>
          @endif
          <th>Técnico</th>
          <th>Serviço</th>
          <th class="text-nowrap">Origem</th>
          @if($showPrecoColumn)
            <th class="text-end text-nowrap">Preço</th>
          @endif
          @if($showNotasColumn)
            <th>Notas</th>
          @endif
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($marcacoes as $ev)
          @php
            $startLocal = \App\Support\DateTimeDisplay::inBusiness($ev->start_at, $ev->store_id);
            $isFutura = $startLocal?->isFuture() ?? false;
            $badgeClass = $isFutura ? 'bg-success-light text-success' : 'bg-secondary-light text-secondary';
            $totalPreco = $ev->eventServiceItems->sum(function ($es) {
                return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
            });
            $statusLabel = \App\Support\MarcacoesReportEstadoFilter::eventRowStatusLabel($ev);
            $servicoNomes = \App\Support\MarcacoesReportEstadoFilter::eventRowServicesLabel($ev);
            $categoria = \App\Support\MarcacoesReportEstadoFilter::eventRowCategoriasLabel($ev);
            $origem = \App\Support\MarcacoesReportEstadoFilter::eventRowOrigemLabel($ev);
            $servicoNomesTrunc = $servicoNomes !== '' && $servicoNomes !== '—'
                ? \Illuminate\Support\Str::limit($servicoNomes, 40)
                : '—';
          @endphp
          <tr>
            <td class="vendas-two-line text-nowrap">
              <div>{{ $startLocal?->locale('pt')->translatedFormat('j F') ?? '—' }}</div>
              <small>{{ $startLocal?->format('Y') ?? '—' }} · {{ $startLocal?->format('H:i') ?? '—' }}</small>
            </td>
            <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
            @if($showClienteColumn)
              <td>
                @if($ev->client)
                  <a href="{{ route('clientes.show', $ev->client) }}">{{ $ev->client->name }}</a>
                @else
                  —
                @endif
              </td>
            @endif
            <td>{{ \Illuminate\Support\Str::of((string) ($ev->user?->name ?? ''))->before(' ') ?: '—' }}</td>
            <td class="vendas-servico-cell">
              @if($categoria !== '')
                <small class="vendas-servico-categoria text-muted d-block">{{ $categoria }}</small>
              @endif
              <span class="vendas-servico-nomes" @if($servicoNomes !== '' && $servicoNomes !== $servicoNomesTrunc) title="{{ $servicoNomes }}" @endif>{{ $servicoNomesTrunc }}</span>
            </td>
            <td class="text-nowrap">{{ $origem }}</td>
            @if($showPrecoColumn)
              <td class="text-end text-nowrap">{{ number_format($totalPreco, 2, ',', ' ') }}€</td>
            @endif
            @if($showNotasColumn)
              <td class="small text-muted">
                @if($ev->description)
                  <span title="{{ $ev->description }}">{{ \Illuminate\Support\Str::limit($ev->description, 60) }}</span>
                @else
                  —
                @endif
              </td>
            @endif
            <td class="text-end p-1">
              @if($actionMode === 'agenda')
                <a href="{{ route('agenda.index') }}?event={{ $ev->id }}" class="btn btn-sm btn-light" title="Ver na agenda">
                  <i class="ph ph-calendar"></i>
                </a>
              @else
                <button type="button"
                  class="btn btn-sm btn-light js-marcacao-modal-trigger"
                  data-bs-toggle="modal"
                  data-bs-target="#marcacaoDetalheModal"
                  data-template-id="marcacao-detail-{{ $ev->id }}"
                  title="Detalhes">
                  <i class="ph ph-list-dashes"></i>
                </button>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
      @if($showFooter && $showPrecoColumn)
        <tfoot class="table-light">
          <tr class="fw-semibold">
            <td colspan="{{ $metaColspan }}" class="text-end">Total</td>
            <td class="text-end text-nowrap">{{ number_format($marcacoesTotais['preco_total'] ?? 0, 2, ',', ' ') }}€</td>
            @if($showNotasColumn)
              <td class="small text-muted">{{ ($marcacoesTotais['servicos_count'] ?? 0) }} serviço(s)</td>
            @endif
            <td></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
@else
  <p class="text-muted text-center py-3">{{ $emptyMessage }}</p>
@endif
