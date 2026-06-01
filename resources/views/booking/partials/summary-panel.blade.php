@php
    $summaryTitle = $summaryTitle ?? 'Resumo da marcação';
    $showNextButton = $showNextButton ?? true;
    $bookingStoreKey = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug();
    $nextUrl = $nextUrl ?? route('booking.datetime', ['store' => $bookingStoreKey], false);
    $nextLabel = $nextLabel ?? 'Seguinte';
    $nextClass = $nextClass ?? 'btn-dark';
    $nextRequires = $nextRequires ?? null;
    $slotHoldSeconds = max(10, \App\Models\CrmSetting::bookingSlotHoldMinutes() * 60);
@endphp

<aside class="pt-1 booking-summary-panel" aria-label="Resumo da marcação">
    <h2 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1 booking-summary-panel__title d-none d-lg-block">{{ $summaryTitle }}</h2>
    <div class="card border shadow-sm rounded-3 booking-summary-card">
        <div class="card-body booking-summary-card__body">
            <div class="booking-summary-scroll" id="booking-summary-scroll">
                @include('booking.partials.summary-store')
                <section id="booking-summary-slot-hold" class="booking-summary-extra booking-summary-slot-hold is-hidden" aria-live="polite">
                    <p class="mb-0 small text-dark">
                        Marcação reservada por <strong id="booking-summary-slot-hold-time">{{ str_pad((string) (int) floor($slotHoldSeconds / 60), 2, '0', STR_PAD_LEFT) }}:{{ str_pad((string) ($slotHoldSeconds % 60), 2, '0', STR_PAD_LEFT) }}</strong>
                    </p>
                </section>

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

                <div class="booking-summary-services booking-summary-extra">
                    <div id="booking-summary-empty" class="booking-summary-empty">
                        <p class="mb-0">Nenhum serviço selecionado.</p>
                    </div>

                    <ul id="booking-summary-list" class="booking-summary-list list-unstyled is-hidden" role="list"></ul>
                    <div id="booking-summary-fees" class="booking-summary-fees is-hidden" role="list" aria-label="Taxas da marcação"></div>
                </div>
            </div>

            <div class="booking-summary-footer">
                {{-- No mobile o JS move #booking-summary-scroll para aqui; no desktop fica vazio (d-lg-none). --}}
                <div
                    id="booking-summary-mobile-drawer"
                    class="booking-summary-mobile-drawer d-lg-none"
                    role="region"
                    aria-label="Resumo da marcação"
                    aria-hidden="true"
                ></div>

                <div class="booking-summary-footer-bar">
                    <div id="booking-summary-total" class="booking-summary-total is-hidden" role="button" tabindex="0" aria-expanded="false" aria-controls="booking-summary-mobile-drawer" aria-label="Mostrar ou ocultar serviços da marcação">
                        <div class="booking-summary-total__meta">
                            <span id="booking-summary-total-count" class="booking-summary-total__label">0 serviços</span>
                            <span id="booking-summary-total-duration" class="booking-summary-total__duration">0min</span>
                            <span id="booking-summary-total-mobile-meta" class="booking-summary-total__mobile-meta d-none d-lg-none" aria-live="polite">0min • 0,00&nbsp;€ • 0:00</span>
                        </div>
                        <span id="booking-summary-total-value" class="booking-summary-total__value"></span>
                        <span class="d-lg-none booking-summary-total__toggle" aria-hidden="true">
                            <i class="bi bi-chevron-up booking-summary-total__toggle-icon"></i>
                        </span>
                    </div>

                    @if($showNextButton)
                        <div class="booking-summary-actions d-flex gap-2 align-items-center">
                            <button type="button" id="booking-next" class="btn {{ $nextClass }} flex-fill booking-summary-next-btn" disabled data-next-url="{{ $nextUrl }}" @if($nextRequires) data-next-requires="{{ $nextRequires }}" @endif>
                                {{ $nextLabel }}
                            </button>
                        </div>
                    @endif
                </div>

            </div>

            <div
                id="booking-summary-mobile-overlay"
                class="booking-summary-mobile-overlay d-lg-none"
                aria-hidden="true"
            ></div>
        </div>
    </div>
</aside>
