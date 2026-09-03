@php
  use App\Models\CalendarEvent;
  $formatMins = function (?int $m): string {
      if ($m === null || $m <= 0) {
          return '—';
      }
      $h = intdiv($m, 60);
      $min = $m % 60;
      if ($h > 0 && $min > 0) {
          return $h.'h '.$min.'min';
      }
      if ($h > 0) {
          return $h.'h';
      }

      return $min.'min';
  };
  $showAgenda = true;
  $statusLabel = CalendarEvent::statuses()[$ev->status] ?? $ev->status;
  $canReativar = auth()->user()?->isAdmin()
      && in_array($ev->status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true);
@endphp
<div class="js-marcacao-modal-body">
  <div class="row g-4 align-items-start">
    <div class="col-12 col-lg-6">
      <div class="uview-detail-group">
        <div class="uview-detail-title">Marcação</div>
        <div class="uview-detail-row">
          <div class="uview-detail-label">Data e hora de início</div>
          <div class="uview-detail-value">{{ \App\Support\DateTimeDisplay::business($ev->start_at) }}</div>
        </div>
        <div class="uview-detail-row">
          <div class="uview-detail-label">Data e hora de fim</div>
          <div class="uview-detail-value">{{ \App\Support\DateTimeDisplay::business($ev->end_at) }}</div>
        </div>
        <div class="uview-detail-row">
          <div class="uview-detail-label">Estado</div>
          <div class="uview-detail-value"><span class="badge bg-secondary-light text-secondary">{{ $statusLabel }}</span></div>
        </div>
        @if($ev->description)
          <div class="uview-detail-row">
            <div class="uview-detail-label">Notas</div>
            <div class="uview-detail-value text-break">{{ $ev->description }}</div>
          </div>
        @endif
      </div>

      <div class="uview-detail-group">
        <div class="uview-detail-title">Cliente e Equipa</div>
        <div class="uview-detail-row">
          <div class="uview-detail-label">Cliente</div>
          <div class="uview-detail-value">
            @if($ev->client)
              <a href="{{ route('clientes.show', $ev->client) }}">{{ $ev->client->name }}</a>
            @else
              —
            @endif
          </div>
        </div>
        <div class="uview-detail-row">
          <div class="uview-detail-label">Técnico</div>
          <div class="uview-detail-value">{{ $ev->user?->name ?? '—' }}</div>
        </div>
      </div>

      @if(in_array($ev->status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true))
        <div class="uview-detail-group">
          <div class="uview-detail-title">{{ $ev->status === CalendarEvent::STATUS_FALTOU ? 'Falta' : 'Cancelamento' }}</div>
          @if($ev->cancellation_reason)
            <div class="uview-detail-row">
              <div class="uview-detail-label">Motivo</div>
              <div class="uview-detail-value text-break">{{ $ev->cancellation_reason }}</div>
            </div>
          @endif
          @if($ev->cancellation_notes)
            <div class="uview-detail-row">
              <div class="uview-detail-label">Notas</div>
              <div class="uview-detail-value text-break">{{ $ev->cancellation_notes }}</div>
            </div>
          @endif
          @if($ev->status === CalendarEvent::STATUS_CANCELADO)
            <div class="uview-detail-row">
              <div class="uview-detail-label">Reembolso reserva</div>
              <div class="uview-detail-value">{{ $ev->refund_reserva ? 'Sim' : 'Não' }}</div>
            </div>
            <div class="uview-detail-row">
              <div class="uview-detail-label">Avisou dentro do prazo</div>
              <div class="uview-detail-value">{{ $ev->avisou_dentro_prazo === null ? '—' : ($ev->avisou_dentro_prazo ? 'Sim' : 'Não') }}</div>
            </div>
          @endif
          @if($ev->status === CalendarEvent::STATUS_FALTOU && !$ev->cancellation_type && !$ev->cancellation_reason)
            <div class="uview-detail-row">
              <div class="uview-detail-label">Detalhes</div>
              <div class="uview-detail-value text-muted">Sem informação adicional registada.</div>
            </div>
          @endif
        </div>
      @endif
    </div>

    <div class="col-12 col-lg-6">
      <div class="uview-detail-group mb-0">
        <div class="uview-detail-title">Serviços e extras</div>
        @forelse($ev->eventServiceItems as $es)
          <div class="@if(!$loop->last) pb-3 mb-3 border-bottom border-light @endif">
            <div class="uview-detail-row">
              <div class="uview-detail-label">Serviço</div>
              <div class="uview-detail-value">{{ trim((string) ($es->option_name ?? '')) !== '' ? $es->option_name : ($es->service?->name ?? '—') }}</div>
            </div>
            <div class="uview-detail-row">
              <div class="uview-detail-label">Categoria</div>
              <div class="uview-detail-value">{{ $es->service?->category?->name ?? '—' }}</div>
            </div>
            @if($es->service?->description)
              <div class="uview-detail-row">
                <div class="uview-detail-label">Descrição do serviço</div>
                <div class="uview-detail-value text-break">{{ $es->service->description }}</div>
              </div>
            @endif
            <div class="uview-detail-row">
              <div class="uview-detail-label">Duração</div>
              <div class="uview-detail-value">{{ $formatMins($es->duration !== null ? (int) $es->duration : null) }}</div>
            </div>
            <div class="uview-detail-row">
              <div class="uview-detail-label">Preço</div>
              <div class="uview-detail-value">{{ number_format((float) $es->price, 2, ',', ' ') }}€</div>
            </div>
            @foreach($es->extras as $x)
              <div class="uview-detail-row">
                <div class="uview-detail-label">Extra</div>
                <div class="uview-detail-value">
                  <span class="fw-medium">{{ $x->extra?->name ?? 'Extra' }}</span>
                  <span class="text-muted"> · {{ $formatMins($x->duration !== null ? (int) $x->duration : null) }}</span>
                  <span class="text-muted"> · {{ number_format((float) $x->price, 2, ',', ' ') }}€</span>
                </div>
              </div>
            @endforeach
          </div>
        @empty
          <div class="uview-detail-row">
            <div class="uview-detail-label">Serviços</div>
            <div class="uview-detail-value text-muted">Nenhum serviço associado.</div>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>
<div class="js-marcacao-modal-footer d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
  <div class="d-flex gap-2 ms-auto">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
    @if($canReativar)
      <button
        type="button"
        class="btn btn-primary js-reativar-marcacao"
        data-event-id="{{ $ev->id }}"
        data-preview-url="{{ route('relatorios.marcacoes.reativar-preview', $ev) }}"
        data-reativar-url="{{ route('relatorios.marcacoes.reativar', $ev) }}"
        data-status-label="{{ $statusLabel }}"
      >
        <i class="ph ph-arrow-counter-clockwise me-1"></i> Reativar
      </button>
    @endif
    @if($showAgenda)
      <a href="{{ route('agenda.index') }}?event={{ $ev->id }}" class="btn btn-primary">
        <i class="ph ph-calendar me-1"></i> Ver na agenda
      </a>
    @endif
  </div>
</div>
