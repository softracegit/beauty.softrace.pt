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
    <h2 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1 booking-summary-panel__title d-none d-lg-block">{{ $summaryTitle }}</h2>
    <div class="card border shadow-sm rounded-3 booking-summary-card">
        <div class="card-body booking-summary-card__body">
            <div class="booking-summary-scroll" id="booking-summary-scroll">
                <div id="booking-summary-empty" class="booking-summary-empty">
                    <p class="mb-1">Nenhum serviço selecionado.</p>
                    <p class="booking-summary-empty__hint mb-0">Escolhe um serviço à esquerda para começar.</p>
                </div>

                <ul id="booking-summary-list" class="booking-summary-list list-unstyled is-hidden" role="list"></ul>

                <section id="booking-summary-technician" class="booking-summary-extra is-hidden" aria-label="Técnica selecionada">
                    <div class="booking-summary-tech">
                        <div id="booking-summary-tech-avatar" class="booking-summary-tech__avatar" aria-hidden="true"></div>
                        <div class="booking-summary-tech__body">
                            <span id="booking-summary-tech-name" class="booking-summary-tech__name"></span>
                            <span id="booking-summary-tech-meta" class="booking-summary-tech__meta"></span>
                        </div>
                    </div>
                </section>

                <section id="booking-summary-datetime" class="booking-summary-extra is-hidden" aria-label="Dia e hora selecionados">
                    <div class="booking-summary-datetime">
                        <span class="booking-summary-datetime__icon" aria-hidden="true">
                            <i class="bi bi-calendar3"></i>
                        </span>
                        <div class="booking-summary-datetime__body">
                            <span id="booking-summary-date-label" class="booking-summary-datetime__date"></span>
                            <span id="booking-summary-time-label" class="booking-summary-datetime__time"></span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="booking-summary-footer">
                {{-- No mobile o JS move #booking-summary-scroll para aqui; no desktop fica vazio (d-lg-none). --}}
                <div
                    id="booking-summary-mobile-drawer"
                    class="booking-summary-mobile-drawer d-lg-none"
                    role="region"
                    aria-label="Serviços na marcação"
                    aria-hidden="true"
                ></div>

                <div class="booking-summary-footer-bar">
                    <div id="booking-summary-total" class="booking-summary-total is-hidden" role="button" tabindex="0" aria-expanded="false" aria-controls="booking-summary-mobile-drawer" aria-label="Mostrar ou ocultar serviços da marcação">
                        <div class="booking-summary-total__meta">
                            <span id="booking-summary-total-count" class="booking-summary-total__label">0 serviços</span>
                            <span id="booking-summary-total-duration" class="booking-summary-total__duration">0min</span>
                        </div>
                        <span id="booking-summary-total-value" class="booking-summary-total__value"></span>
                        <span class="d-lg-none booking-summary-total__toggle" aria-hidden="true">
                            <i class="bi bi-chevron-down booking-summary-total__toggle-icon"></i>
                        </span>
                    </div>

                    @if($showBackButton || $showNextButton)
                        <div class="booking-summary-actions d-flex gap-2 align-items-center">
                            @if($showBackButton)
                                <a href="{{ $backUrl }}" class="btn btn-outline-secondary d-none d-lg-inline-flex justify-content-center align-items-center booking-summary-back-btn booking-summary-back-desktop" aria-label="Voltar">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </a>
                                <a href="{{ $backUrl }}" class="btn btn-outline-secondary d-inline-flex d-lg-none justify-content-center align-items-center booking-summary-back-btn booking-summary-back-mobile flex-shrink-0" aria-label="Voltar">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </a>
                            @endif
                            @if($showNextButton)
                                <button type="button" id="booking-next" class="btn {{ $nextClass }} flex-fill booking-summary-next-btn" disabled data-next-url="{{ $nextUrl }}" @if($nextRequires) data-next-requires="{{ $nextRequires }}" @endif>
                                    {{ $nextLabel }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</aside>
