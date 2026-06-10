@php
    $isBookingClientUser = auth()->check()
        && auth()->user() instanceof \App\Models\User
        && auth()->user()->isBookingClient();

    $profile = $bookingStoreProfile ?? ($bookingStore?->publicBookingProfile() ?? []);
    $weeklySchedule = $bookingWeeklySchedule ?? ($bookingStore?->normalizedWeeklySchedule() ?? \App\Models\Store::defaultWeeklySchedule());
    $bookingStoreSlug = (string) ($bookingStoreSlug ?? $bookingStore?->slug ?? \App\Models\Store::defaultPublicBookingStoreSlug());
    $dayLabels = \App\Models\Agent::weekdayLabels();
    $isoByKey = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
    $shortDayLabels = [
        'mon' => __('booking.weekdays.mon'),
        'tue' => __('booking.weekdays.tue'),
        'wed' => __('booking.weekdays.wed'),
        'thu' => __('booking.weekdays.thu'),
        'fri' => __('booking.weekdays.fri'),
        'sat' => __('booking.weekdays.sat'),
        'sun' => __('booking.weekdays.sun'),
    ];

    $tz = $bookingStore?->bookingTimezone() ?? (string) config('booking.business_timezone', config('app.timezone', 'Europe/Lisbon'));
    $now = now()->timezone($tz);
    $hoursUi = \App\Support\BookingStoreOpenStatus::publicUiState($weeklySchedule);
    $statusLabel = $hoursUi['label'];
    $statusClass = $hoursUi['css_class'];
    $statusSuffix = $hoursUi['suffix'];
    $hasWebsite = ! empty($profile['website_url']) && ($profile['website_url'] ?? '#') !== '#';
    $hasInstagram = ! empty($profile['instagram_url']) && ($profile['instagram_url'] ?? '#') !== '#';
@endphp
<div class="offcanvas offcanvas-end booking-offcanvas" tabindex="-1" id="bookingStoreDetails" aria-labelledby="bookingOffcanvasTitle">
    <div class="offcanvas-header border-0 pb-0 booking-offcanvas__header">
        <button type="button" class="btn booking-offcanvas__close-btn" data-bs-dismiss="offcanvas" aria-label="{{ __('booking.partials.store_close_aria') }}">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <h2 class="visually-hidden offcanvas-title" id="bookingOffcanvasTitle">{{ __('booking.partials.store_menu_title') }}</h2>
    </div>
    <div class="offcanvas-body d-flex flex-column booking-offcanvas__body">
        <section class="booking-offcanvas__agency" aria-label="{{ __('booking.partials.store_agency_aria') }}">
            <h3 class="booking-offcanvas__agency-title mb-2">{{ $profile['name'] ?? ($businessName ?? __('booking.nav.default_store')) }}</h3>

            <div class="booking-offcanvas__info-list">
                @if (! empty($profile['address']))
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">{{ __('booking.partials.store_address') }}</h4>
                    <p class="booking-offcanvas__info-value">{{ $profile['address'] }}</p>
                    <a
                        href="{{ $profile['maps_url'] ?? '#' }}"
                        target="_blank"
                        rel="noopener"
                        class="booking-offcanvas__icon-btn booking-offcanvas__info-action"
                        aria-label="{{ __('booking.partials.store_open_maps_aria') }}"
                    >
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    </a>
                </article>
                @endif
                @if (! empty($profile['phone']))
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">{{ __('booking.partials.store_phone') }}</h4>
                    <p class="booking-offcanvas__info-value">{{ $profile['phone'] }}</p>
                    <a href="{{ $profile['phone_tel_href'] ?? '#' }}" class="booking-offcanvas__icon-btn booking-offcanvas__info-action" aria-label="{{ __('booking.partials.store_call_aria', ['phone' => $profile['phone']]) }}">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                    </a>
                </article>
                @endif
                @if (! empty($profile['email']))
                <article class="booking-offcanvas__info-item">
                    <h4 class="booking-offcanvas__info-label">{{ __('booking.partials.store_email') }}</h4>
                    <p class="booking-offcanvas__info-value mb-0">
                        <a href="mailto:{{ $profile['email'] }}" class="text-decoration-none text-body">{{ $profile['email'] }}</a>
                    </p>
                </article>
                @endif
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action booking-offcanvas__info-item--hours">
                    <h4 class="booking-offcanvas__info-label mb-0">{{ __('booking.partials.store_hours') }}</h4>
                    <p class="booking-offcanvas__info-value mt-2">
                        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>{{ $statusSuffix }}
                    </p>
                    <details class="booking-offcanvas__hours-details booking-offcanvas__info-action">
                        <summary class="booking-offcanvas__hours-summary" aria-label="{{ __('booking.partials.store_hours_details_aria') }}">
                            <span class="booking-offcanvas__icon-btn" aria-hidden="true">
                                <i class="bi bi-chevron-down"></i>
                            </span>
                        </summary>
                    </details>
                    <ul class="booking-offcanvas__hours-list list-unstyled mb-0">
                        @foreach (\App\Models\Agent::WEEKDAY_KEYS as $dayKey)
                            @php
                                $day = $weeklySchedule[$dayKey] ?? null;
                                $enabled = is_array($day) && ! empty($day['enabled']);
                                $slotLabel = $enabled
                                    ? (($day['start'] ?? '09:00').' – '.($day['end'] ?? '20:00'))
                                    : __('booking.partials.store_closed');
                                $iso = $isoByKey[$dayKey] ?? 0;
                            @endphp
                            <li class="{{ (int) $now->isoWeekday() === $iso ? 'booking-offcanvas__hours-list-current' : '' }}">
                                <span>{{ $shortDayLabels[$dayKey] ?? ($dayLabels[$dayKey] ?? $dayKey) }}</span>
                                <span>{{ $slotLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
                @if ($hasWebsite || $hasInstagram)
                <article class="booking-offcanvas__info-item">
                    <div class="booking-offcanvas__follow-row">
                        <h4 class="booking-offcanvas__info-label">{{ __('booking.partials.store_follow_us') }}</h4>
                        <div class="booking-offcanvas__social-links">
                            @if ($hasWebsite)
                            <a href="{{ $profile['website_url'] }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="{{ __('booking.partials.store_website_aria') }}">
                                <i class="bi bi-globe" aria-hidden="true"></i>
                            </a>
                            @endif
                            @if ($hasInstagram)
                            <a href="{{ $profile['instagram_url'] }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="{{ __('booking.partials.store_instagram_aria') }}">
                                <i class="bi bi-instagram" aria-hidden="true"></i>
                            </a>
                            @endif
                        </div>
                    </div>
                </article>
                @endif
            </div>
        </section>

        <footer class="booking-offcanvas__footer mt-auto" aria-label="{{ __('booking.partials.store_account_aria') }}">
            @if ($isBookingClientUser)
                <a href="{{ route('booking.conta.index', ['store' => $bookingStoreSlug], false) }}" class="booking-offcanvas-nav__link">
                    {{ __('booking.partials.store_my_account') }}
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('booking.logout', ['store' => $bookingStoreSlug], false) }}" class="btn btn-link booking-offcanvas__logout p-0 text-decoration-none mt-2 d-inline-block">{{ __('booking.nav.logout') }}</a>
            @else
                <button type="button" class="booking-offcanvas-nav__link btn btn-link text-start w-100 p-0 border-0 js-booking-open-auth-modal">
                    {{ __('booking.nav.login') }}
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            @endif
        </footer>
    </div>
</div>
