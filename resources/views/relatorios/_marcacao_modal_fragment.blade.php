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
  $slim = ($marcacaoDetailMode ?? 'full') === 'client';
  $statusLabel = CalendarEvent::statuses()[$ev->status] ?? $ev->status;
  $canReativar = auth()->user()?->isAdmin()
      && in_array($ev->status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true);
  $storeId = $ev->store_id ? (int) $ev->store_id : null;
  $clinicalSheetService = app(\App\Services\ClinicalSheetService::class);
  $clinicalSections = $clinicalSheetService->snapshotSections(
      is_array($ev->clinical_sheet) ? $ev->clinical_sheet : null,
      true,
      $clinicalSheetService->categoryIdsForEvent($ev)
  );
  $techFormula = trim((string) ($ev->technical_formula ?? ''));
  $techProducts = trim((string) ($ev->technical_products ?? ''));
  $techNotes = trim((string) ($ev->technical_notes ?? ''));
  $hasLegacyTech = $techFormula !== '' || $techProducts !== '' || $techNotes !== '';
  $visitNotes = trim((string) ($ev->description ?? ''));

  $items = $ev->eventServiceItems ?? collect();
  $totalMins = 0;
  $totalPrice = 0.0;
  $serviceLabels = [];
  foreach ($items as $es) {
      $totalMins += (int) ($es->duration ?? 0);
      $totalPrice += (float) ($es->price ?? 0);
      foreach ($es->extras ?? [] as $x) {
          $totalMins += (int) ($x->duration ?? 0);
          $totalPrice += (float) ($x->price ?? 0);
      }
      $serviceLabels[] = trim((string) ($es->option_name ?? '')) !== '' ? $es->option_name : ($es->service?->name ?? 'Serviço');
  }
  $isTerminal = in_array($ev->status, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true);
  $statusTone = match ($ev->status) {
      CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU => 'danger',
      CalendarEvent::STATUS_TERMINADO, CalendarEvent::STATUS_COMPLETO => 'success',
      CalendarEvent::STATUS_INICIADO, CalendarEvent::STATUS_CHEGOU => 'info',
      default => 'neutral',
  };
  $priceLabel = number_format($totalPrice, 2, ',', ' ').'€';
@endphp
<div class="js-marcacao-modal-body csheet-detail">
  <div class="csheet-hero">
    <div class="csheet-hero__main">
      <div class="csheet-hero__title">{{ $serviceLabels !== [] ? implode(' + ', $serviceLabels) : ($ev->title ?: 'Marcação') }}</div>
      <div class="csheet-hero__meta">
        <span><i class="ph ph-calendar-blank" aria-hidden="true"></i> {{ \App\Support\DateTimeDisplay::marcacao($ev->start_at, $storeId, 'd/m/Y') }}</span>
        <span><i class="ph ph-clock" aria-hidden="true"></i> {{ \App\Support\DateTimeDisplay::marcacao($ev->start_at, $storeId, 'H:i') }}–{{ \App\Support\DateTimeDisplay::marcacao($ev->end_at, $storeId, 'H:i') }}</span>
        @if($ev->user?->name)
          <span><i class="ph ph-user" aria-hidden="true"></i> {{ $ev->user->name }}</span>
        @endif
        @if($slim)
          <span><i class="ph ph-currency-eur" aria-hidden="true"></i> {{ $priceLabel }}</span>
        @endif
      </div>
    </div>
    <span class="csheet-status csheet-status--{{ $statusTone }}">{{ $statusLabel }}</span>
  </div>

  @unless($slim)
    <div class="csheet-stats">
      <div class="csheet-stat">
        <span class="csheet-stat__icon"><i class="ph ph-user-circle" aria-hidden="true"></i></span>
        <div class="min-w-0">
          <div class="csheet-stat__label">Cliente</div>
          <div class="csheet-stat__value text-truncate">
            @if($ev->client)
              <a href="{{ route('clientes.show', $ev->client) }}">{{ $ev->client->name }}</a>
            @else
              —
            @endif
          </div>
        </div>
      </div>
      <div class="csheet-stat">
        <span class="csheet-stat__icon"><i class="ph ph-timer" aria-hidden="true"></i></span>
        <div>
          <div class="csheet-stat__label">Duração</div>
          <div class="csheet-stat__value">{{ $formatMins($totalMins ?: null) }}</div>
        </div>
      </div>
      <div class="csheet-stat">
        <span class="csheet-stat__icon"><i class="ph ph-currency-eur" aria-hidden="true"></i></span>
        <div>
          <div class="csheet-stat__label">Total</div>
          <div class="csheet-stat__value">{{ $priceLabel }}</div>
        </div>
      </div>
    </div>

    <section class="csheet-card">
      <h6 class="csheet-card__title">Serviços e extras</h6>
      @forelse($items as $es)
        <div class="csheet-service">
          <div class="csheet-service__main">
            <div class="csheet-service__name">{{ trim((string) ($es->option_name ?? '')) !== '' ? $es->option_name : ($es->service?->name ?? '—') }}</div>
            @if($es->service?->category?->name)
              <div class="csheet-service__sub">{{ $es->service->category->name }}</div>
            @endif
            @foreach($es->extras as $x)
              <div class="csheet-service__extra">
                <i class="ph ph-plus-circle" aria-hidden="true"></i>
                {{ $x->extra?->name ?? 'Extra' }}
                <span class="text-muted">· {{ $formatMins($x->duration !== null ? (int) $x->duration : null) }} · {{ number_format((float) $x->price, 2, ',', ' ') }}€</span>
              </div>
            @endforeach
          </div>
          <div class="csheet-service__side">
            <div class="csheet-service__price">{{ number_format((float) $es->price, 2, ',', ' ') }}€</div>
            <div class="csheet-service__sub">{{ $formatMins($es->duration !== null ? (int) $es->duration : null) }}</div>
          </div>
        </div>
      @empty
        <p class="csheet-empty">Nenhum serviço associado.</p>
      @endforelse
    </section>
  @endunless

  @if($visitNotes !== '')
    <div class="csheet-obs-card">
      <span class="csheet-obs-card__label">Observações</span>
      <span class="csheet-obs-card__text">{{ $visitNotes }}</span>
    </div>
  @endif

  <section class="csheet-ficha">
    @forelse($clinicalSections as $section)
      @if(!empty($section['category_name']))
        <div class="csheet-ficha__category">{{ $section['category_name'] }}</div>
      @endif
      <div class="csheet-ficha__fields">
        @foreach($section['fields'] as $f)
          @php $isEmpty = !empty($f['empty']); @endphp
          <div class="csheet-field-card{{ in_array($f['type'], ['textarea'], true) ? ' csheet-field-card--wide' : '' }}{{ $isEmpty ? ' csheet-field-card--empty' : '' }}">
            <div class="csheet-field-card__label">{{ $f['label'] }}</div>
            <div class="csheet-field-card__value">
              @if($isEmpty)
                <span class="csheet-field-card__empty">---</span>
              @elseif(
                ($f['type'] ?? '') === 'material'
                || (is_array($f['value']) && ! array_is_list($f['value']) && (isset($f['value']['name']) || isset($f['value']['id'])))
              )
                @include('partials.clinical-material-value', ['value' => $f['value']])
              @elseif(is_array($f['value']))
                <div class="csheet-chips">
                  @foreach($f['value'] as $chip)
                    @if(is_scalar($chip) || $chip === null)
                      <span class="csheet-chip">{{ $chip }}</span>
                    @endif
                  @endforeach
                </div>
              @elseif(is_bool($f['value']))
                <span class="csheet-bool csheet-bool--{{ $f['value'] ? 'yes' : 'no' }}">
                  <i class="ph {{ $f['value'] ? 'ph-check' : 'ph-x' }}" aria-hidden="true"></i> {{ $f['display'] }}
                </span>
              @else
                <span class="text-break">{{ $f['display'] }}</span>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @empty
      @unless($hasLegacyTech)
        <p class="csheet-empty">Sem ficha técnica registada nesta marcação.</p>
      @endunless
    @endforelse

    @if($hasLegacyTech)
      <div class="csheet-ficha__category">Registo técnico</div>
      <div class="csheet-ficha__fields">
        @foreach(['Fórmula' => $techFormula, 'Produtos' => $techProducts, 'Notas técnicas' => $techNotes] as $label => $val)
          @if($val !== '')
            <div class="csheet-field-card csheet-field-card--wide">
              <div class="csheet-field-card__label">{{ $label }}</div>
              <div class="csheet-field-card__value text-break">{{ $val }}</div>
            </div>
          @endif
        @endforeach
      </div>
    @endif
  </section>

  @if($isTerminal)
    <section class="csheet-card csheet-card--danger">
      <h6 class="csheet-card__title">{{ $ev->status === CalendarEvent::STATUS_FALTOU ? 'Falta' : 'Cancelamento' }}</h6>
      <div class="csheet-ficha__fields">
        @if($ev->cancellation_reason)
          <div class="csheet-field-card csheet-field-card--wide">
            <div class="csheet-field-card__label">Motivo</div>
            <div class="csheet-field-card__value text-break">{{ $ev->cancellation_reason }}</div>
          </div>
        @endif
        @if($ev->cancellation_notes)
          <div class="csheet-field-card csheet-field-card--wide">
            <div class="csheet-field-card__label">Notas</div>
            <div class="csheet-field-card__value text-break">{{ $ev->cancellation_notes }}</div>
          </div>
        @endif
        @if($ev->status === CalendarEvent::STATUS_CANCELADO)
          <div class="csheet-field-card">
            <div class="csheet-field-card__label">Reembolso reserva</div>
            <div class="csheet-field-card__value">{{ $ev->refund_reserva ? 'Sim' : 'Não' }}</div>
          </div>
          <div class="csheet-field-card">
            <div class="csheet-field-card__label">Avisou dentro do prazo</div>
            <div class="csheet-field-card__value">{{ $ev->avisou_dentro_prazo === null ? '—' : ($ev->avisou_dentro_prazo ? 'Sim' : 'Não') }}</div>
          </div>
        @endif
      </div>
      @if($ev->status === CalendarEvent::STATUS_FALTOU && !$ev->cancellation_type && !$ev->cancellation_reason)
        <p class="csheet-empty mb-0">Sem informação adicional registada.</p>
      @endif
    </section>
  @endif
</div>
<div class="js-marcacao-modal-footer d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
  <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Fechar</button>
  <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 ms-auto">
    @if(auth()->user()?->isAdmin())
      <button
        type="button"
        class="btn btn-light js-marcacao-activity-log-trigger"
        data-activity-log-url="{{ route('agenda.events.activity_log', $ev) }}"
      >
        <i class="ph ph-clock-counter-clockwise me-1" aria-hidden="true"></i> Ver logs
      </button>
    @endif
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
