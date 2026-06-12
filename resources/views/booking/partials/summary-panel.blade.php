@php
    $summaryTitle = $summaryTitle ?? __('booking.partials.summary_title');
    $showNextButton = $showNextButton ?? true;
    $bookingStoreKey = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug();
    $nextUrl = $nextUrl ?? route('booking.datetime', ['store' => $bookingStoreKey], false);
    $nextLabel = $nextLabel ?? __('booking.partials.next');
    $nextClass = $nextClass ?? 'btn-dark';
    $nextRequires = $nextRequires ?? null;
    $slotHoldSeconds = max(10, \App\Models\CrmSetting::bookingSlotHoldMinutes() * 60);
@endphp

<aside @class(['pt-1 booking-summary-panel', 'booking-summary-panel--elegant' => ($bookingUsesRefinedLayout ?? false)]) aria-label="{{ __('booking.partials.summary_title') }}">
    <h2 @class([
        'booking-services-heading h6 fw-semibold text-dark mb-3 ps-1 booking-summary-panel__title d-none d-lg-block',
        'booking-summary-panel__title--elegant' => ($bookingUsesRefinedLayout ?? false),
    ])>{{ ($bookingUsesRefinedLayout ?? false) ? __('booking.elegant.summary_label') : $summaryTitle }}</h2>
    <div class="card border shadow-sm rounded-3 booking-summary-card">
        <div class="card-body booking-summary-card__body">
            <div class="booking-summary-scroll" id="booking-summary-scroll">
                @include('booking.partials.summary-store')
                <section id="booking-summary-slot-hold" class="booking-summary-extra booking-summary-slot-hold is-hidden" aria-live="polite">
                    <p class="mb-0 small text-dark">
                        {{ __('booking.partials.summary_slot_hold') }} <strong id="booking-summary-slot-hold-time">{{ str_pad((string) (int) floor($slotHoldSeconds / 60), 2, '0', STR_PAD_LEFT) }}:{{ str_pad((string) ($slotHoldSeconds % 60), 2, '0', STR_PAD_LEFT) }}</strong>
                    </p>
                </section>

                @if ($bookingUsesRefinedLayout ?? false)
                    <div class="booking-elegant-summary-scheduling">
                @endif

                <section
                    id="booking-summary-technician"
                    @class([
                        'is-hidden',
                        'booking-summary-extra' => ! ($bookingUsesRefinedLayout ?? false),
                        'booking-elegant-summary-scheduling__item' => ($bookingUsesRefinedLayout ?? false),
                    ])
                    aria-label="{{ __('booking.partials.summary_technician_aria') }}"
                >
                    <div class="booking-summary-tech">
                        <div id="booking-summary-tech-avatar" class="booking-summary-tech__avatar" aria-hidden="true"></div>
                        <div class="booking-summary-tech__body">
                            <span id="booking-summary-tech-name" class="booking-summary-tech__name"></span>
                            <span id="booking-summary-tech-meta" class="booking-summary-tech__meta"></span>
                        </div>
                    </div>
                </section>

                <section
                    id="booking-summary-datetime"
                    @class([
                        'is-hidden',
                        'booking-summary-extra' => ! ($bookingUsesRefinedLayout ?? false),
                        'booking-elegant-summary-scheduling__item' => ($bookingUsesRefinedLayout ?? false),
                    ])
                    aria-label="{{ __('booking.partials.summary_datetime_aria') }}"
                >
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

                @if ($bookingUsesRefinedLayout ?? false)
                    </div>
                @endif

                @if ($bookingUsesRefinedLayout ?? false)
                    <div class="booking-elegant-summary-services-box">
                @endif

                <div @class([
                    'booking-summary-services',
                    'booking-summary-extra' => ! ($bookingUsesRefinedLayout ?? false),
                ])>
                    <div id="booking-summary-empty" class="booking-summary-empty">
                        <p class="mb-0">{{ __('booking.partials.summary_empty') }}</p>
                    </div>

                    <ul id="booking-summary-list" class="booking-summary-list list-unstyled is-hidden" role="list"></ul>
                    <div id="booking-summary-fees" class="booking-summary-fees is-hidden" role="list" aria-label="{{ __('booking.partials.summary_fees_aria') }}"></div>
                </div>

                @if ($bookingUsesRefinedLayout ?? false)
                    </div>
                @endif
            </div>

            <div class="booking-summary-footer">
                {{-- No mobile o JS move #booking-summary-scroll para aqui; no desktop fica vazio (d-lg-none). --}}
                <div
                    id="booking-summary-mobile-drawer"
                    class="booking-summary-mobile-drawer d-lg-none"
                    role="region"
                    aria-label="{{ __('booking.partials.summary_mobile_drawer_aria') }}"
                    aria-hidden="true"
                ></div>

                <div class="booking-summary-footer-bar">
                    <div id="booking-summary-total" class="booking-summary-total is-hidden" role="button" tabindex="0" aria-expanded="false" aria-controls="booking-summary-mobile-drawer" aria-label="{{ __('booking.partials.summary_total_toggle_aria') }}">
                        <div class="booking-summary-total__meta">
                            <span id="booking-summary-total-count" class="booking-summary-total__label">0 {{ __('booking.js.services_other') }}</span>
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
