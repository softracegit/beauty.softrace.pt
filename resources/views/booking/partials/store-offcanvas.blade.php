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
        'mon' => 'Segunda',
        'tue' => 'Terça',
        'wed' => 'Quarta',
        'thu' => 'Quinta',
        'fri' => 'Sexta',
        'sat' => 'Sábado',
        'sun' => 'Domingo',
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
        <button type="button" class="btn booking-offcanvas__close-btn" data-bs-dismiss="offcanvas" aria-label="Fechar menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <h2 class="visually-hidden offcanvas-title" id="bookingOffcanvasTitle">Menu</h2>
    </div>
    <div class="offcanvas-body d-flex flex-column booking-offcanvas__body">
        <section class="booking-offcanvas__agency" aria-label="Informação da agência">
            <h3 class="booking-offcanvas__agency-title mb-2">{{ $profile['name'] ?? ($businessName ?? 'Loja') }}</h3>

            <div class="booking-offcanvas__info-list">
                @if (! empty($profile['address']))
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">Morada</h4>
                    <p class="booking-offcanvas__info-value">{{ $profile['address'] }}</p>
                    <a
                        href="{{ $profile['maps_url'] ?? '#' }}"
                        target="_blank"
                        rel="noopener"
                        class="booking-offcanvas__icon-btn booking-offcanvas__info-action"
                        aria-label="Abrir morada no Google Maps"
                    >
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    </a>
                </article>
                @endif
                @if (! empty($profile['phone']))
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">Telefone</h4>
                    <p class="booking-offcanvas__info-value">{{ $profile['phone'] }}</p>
                    <a href="{{ $profile['phone_tel_href'] ?? '#' }}" class="booking-offcanvas__icon-btn booking-offcanvas__info-action" aria-label="Ligar para {{ $profile['phone'] }}">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                    </a>
                </article>
                @endif
                @if (! empty($profile['email']))
                <article class="booking-offcanvas__info-item">
                    <h4 class="booking-offcanvas__info-label">Email</h4>
                    <p class="booking-offcanvas__info-value mb-0">
                        <a href="mailto:{{ $profile['email'] }}" class="text-decoration-none text-body">{{ $profile['email'] }}</a>
                    </p>
                </article>
                @endif
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action booking-offcanvas__info-item--hours">
                    <h4 class="booking-offcanvas__info-label mb-0">Horário</h4>
                    <p class="booking-offcanvas__info-value mt-2">
                        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>{{ $statusSuffix }}
                    </p>
                    <details class="booking-offcanvas__hours-details booking-offcanvas__info-action">
                        <summary class="booking-offcanvas__hours-summary" aria-label="Mostrar horários completos">
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
                                    : 'Encerrado';
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
                        <h4 class="booking-offcanvas__info-label">Siga-nos</h4>
                        <div class="booking-offcanvas__social-links">
                            @if ($hasWebsite)
                            <a href="{{ $profile['website_url'] }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="Site da loja">
                                <i class="bi bi-globe" aria-hidden="true"></i>
                            </a>
                            @endif
                            @if ($hasInstagram)
                            <a href="{{ $profile['instagram_url'] }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="Instagram da loja">
                                <i class="bi bi-instagram" aria-hidden="true"></i>
                            </a>
                            @endif
                        </div>
                    </div>
                </article>
                @endif
            </div>
        </section>

        <footer class="booking-offcanvas__footer mt-auto" aria-label="Conta">
            @if ($isBookingClientUser)
                <a href="{{ route('booking.conta.index', ['store' => $bookingStoreSlug], false) }}" class="booking-offcanvas-nav__link">
                    A minha conta
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('booking.logout', ['store' => $bookingStoreSlug], false) }}" class="btn btn-link booking-offcanvas__logout p-0 text-decoration-none mt-2 d-inline-block">Terminar sessão</a>
            @else
                <button type="button" class="booking-offcanvas-nav__link btn btn-link text-start w-100 p-0 border-0 js-booking-open-auth-modal">
                    Iniciar sessão
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            @endif
        </footer>
    </div>
</div>
