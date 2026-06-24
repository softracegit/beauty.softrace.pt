@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Marcações').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Marcações</h2>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('relatorios.marcacoes.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
          <i class="ph ph-download-simple me-1"></i> Exportar
        </a>
        <a href="{{ route('relatorios.marcacoes.pdf', request()->query()) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
          <i class="ph ph-printer me-1"></i> Imprimir
        </a>
      </div>
    </div>
  </div>
  <form method="GET" action="{{ route('relatorios.marcacoes') }}" class="uview-cliente-tab-filters mb-3">
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Desde</label>
      <input type="text" name="marcacoes_desde" class="form-control form-control-sm" value="{{ $marcacoesDesde ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Até</label>
      <input type="text" name="marcacoes_ate" class="form-control form-control-sm" value="{{ $marcacoesAte ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Cliente</label>
      <select name="marcacoes_cliente" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($clientesOpts ?? [] as $c)
          <option value="{{ $c->id }}" {{ (string)($marcacoesCliente ?? '') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Serviço</label>
      <select name="marcacoes_servico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($servicosOpts ?? [] as $svc)
          <option value="{{ $svc->id }}" {{ (string)($marcacoesServico ?? '') === (string)$svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Técnico</label>
      <select name="marcacoes_tecnico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($tecnicosOpts ?? [] as $tec)
          <option value="{{ $tec->id }}" {{ (string)($marcacoesTecnico ?? '') === (string)$tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-estado">
      <label class="form-label small text-muted mb-0">Estado</label>
      <select name="marcacoes_estado" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach(\App\Models\CalendarEvent::statuses() as $key => $label)
          <option value="{{ $key }}" {{ ($marcacoesEstado ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

  @if($marcacoes->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>Data/Hora</th>
            <th>Estado</th>
            <th>Cliente</th>
            <th>Técnico</th>
            <th>Serviços</th>
            <th>Categoria</th>
            <th class="text-end text-nowrap">Preço</th>
            <th>Notas</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($marcacoes as $ev)
            @php
              $isFutura = $ev->start_at->isFuture();
              $badgeClass = $isFutura ? 'bg-success-light text-success' : 'bg-secondary-light text-secondary';
              $totalPreco = $ev->eventServiceItems->sum(function ($es) {
                return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
              });
            @endphp
            <tr>
              <td>{{ \App\Support\DateTimeDisplay::business($ev->start_at) }}</td>
              <td><span class="badge {{ $badgeClass }}">{{ \App\Models\CalendarEvent::statuses()[$ev->status] ?? $ev->status }}</span></td>
              <td>
                @if($ev->client)
                  <a href="{{ route('clientes.show', $ev->client) }}">{{ $ev->client->name }}</a>
                @else
                  —
                @endif
              </td>
              <td>{{ $ev->user?->name ?? '—' }}</td>
              <td>
                @foreach($ev->eventServiceItems as $es)
                  <span class="badge {{ $isFutura ? 'bg-primary-light text-primary' : 'bg-secondary-light text-secondary' }} me-1">{{ trim((string) ($es->option_name ?? '')) !== '' ? $es->option_name : ($es->service?->name ?? '—') }}</span>
                @endforeach
              </td>
              <td>
                @foreach($ev->eventServiceItems as $es)
                  <span class="badge {{ $isFutura ? 'bg-primary-light text-primary' : 'bg-secondary-light text-secondary' }} me-1">{{ $es->service?->category?->name ?? '—' }}</span>
                @endforeach
              </td>
              <td class="text-end text-nowrap">{{ number_format($totalPreco, 2, ',', ' ') }}€</td>
              <td class="small text-muted">
                @if($ev->description)
                  <span title="{{ $ev->description }}">{{ \Illuminate\Support\Str::limit($ev->description, 60) }}</span>
                @else
                  —
                @endif
              </td>
              <td>
                <button type="button"
                  class="btn btn-sm btn-light js-marcacao-modal-trigger"
                  data-bs-toggle="modal"
                  data-bs-target="#marcacaoDetalheModal"
                  data-template-id="marcacao-detail-{{ $ev->id }}"
                  title="Detalhes">
                  <i class="ph ph-list-dashes"></i>
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="table-light">
          <tr class="fw-semibold">
            <td colspan="5" class="text-end">Total</td>
            <td>—</td>
            <td class="text-end text-nowrap">{{ number_format($marcacoesTotais['preco_total'] ?? 0, 2, ',', ' ') }}€</td>
            <td colspan="2" class="small text-muted">{{ ($marcacoesTotais['servicos_count'] ?? 0) }} serviço(s)</td>
          </tr>
        </tfoot>
      </table>
    </div>
    @foreach($marcacoes as $ev)
      <template id="marcacao-detail-{{ $ev->id }}">
        @include('relatorios._marcacao_modal_fragment', ['ev' => $ev])
      </template>
    @endforeach
    <div class="modal fade" id="marcacaoDetalheModal" tabindex="-1" aria-labelledby="marcacaoDetalheModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="marcacaoDetalheModalLabel">Detalhes da marcação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body" id="marcacaoDetalheModalBody"></div>
          <div class="modal-footer" id="marcacaoDetalheModalFooter"></div>
        </div>
      </div>
    </div>
    @include('relatorios.partials.pagination', ['paginator' => $marcacoes])
  @else
    <p class="text-muted text-center py-3">Nenhuma marcação nos filtros selecionados.</p>
  @endif
@endsection

@section('js')
  <script>
    (function () {
      var modalEl = document.getElementById('marcacaoDetalheModal');
      if (!modalEl) return;
      modalEl.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn || !btn.getAttribute('data-template-id')) return;
        var tid = btn.getAttribute('data-template-id');
        var tpl = document.getElementById(tid);
        var body = document.getElementById('marcacaoDetalheModalBody');
        var footer = document.getElementById('marcacaoDetalheModalFooter');
        if (!tpl || !body || !footer) return;
        body.innerHTML = '';
        footer.innerHTML = '';
        var frag = tpl.content.cloneNode(true);
        var bodyEl = frag.querySelector('.js-marcacao-modal-body');
        var footerEl = frag.querySelector('.js-marcacao-modal-footer');
        if (bodyEl) body.appendChild(bodyEl);
        if (footerEl) footer.appendChild(footerEl);
      });
    })();
  </script>
@endsection
