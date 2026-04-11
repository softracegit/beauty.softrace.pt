@php
    $summaryTitle = $summaryTitle ?? 'Resumo da marcação';
    $showNextButton = $showNextButton ?? true;
    $nextUrl = $nextUrl ?? route('booking.datetime');
    $nextLabel = $nextLabel ?? 'Seguinte';
    $nextClass = $nextClass ?? 'btn-dark';
    $showBackButton = $showBackButton ?? false;
    $backUrl = $backUrl ?? route('booking.index');
    $nextRequires = $nextRequires ?? null;
@endphp

<aside class="pt-1 booking-summary-panel" aria-label="Resumo da marcação">
    <h2 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1">{{ $summaryTitle }}</h2>
    <div class="card border shadow-sm rounded-3 booking-summary-card">
        <div class="card-body booking-summary-card__body">
            <div class="booking-summary-scroll" id="booking-summary-scroll">
                <div id="booking-summary-empty" class="booking-summary-empty">
                    <p class="mb-1">Nenhum serviço selecionado.</p>
                    <p class="booking-summary-empty__hint mb-0">Escolhe um serviço à esquerda para começar.</p>
                </div>

                <ul id="booking-summary-list" class="booking-summary-list list-unstyled is-hidden" role="list"></ul>
            </div>

            <div class="booking-summary-footer">
                <div id="booking-summary-total" class="booking-summary-total is-hidden">
                    <div class="booking-summary-total__meta">
                        <span id="booking-summary-total-count" class="booking-summary-total__label">0 serviços</span>
                        <span id="booking-summary-total-duration" class="booking-summary-total__duration">0min</span>
                    </div>
                    <span id="booking-summary-total-value" class="booking-summary-total__value"></span>
                </div>

                @if($showBackButton || $showNextButton)
                    <div class="booking-summary-actions d-flex gap-2">
                        @if($showBackButton)
                            <a href="{{ $backUrl }}" class="btn btn-outline-secondary flex-fill">Voltar</a>
                        @endif
                        @if($showNextButton)
                            <button type="button" id="booking-next" class="btn {{ $nextClass }} flex-fill" disabled data-next-url="{{ $nextUrl }}" @if($nextRequires) data-next-requires="{{ $nextRequires }}" @endif>
                                {{ $nextLabel }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</aside>
