@php
    $isBookingClientUser = auth()->check()
        && auth()->user() instanceof \App\Models\User
        && auth()->user()->isBookingClient();

    $store = config('booking.public_store', []);
    $weekOpen = (string) ($store['weekday_open'] ?? '09:00');
    $weekClose = (string) ($store['weekday_close'] ?? '20:00');
    $weekSlotLabel = $weekOpen.' – '.$weekClose;

    $tz = (string) config('booking.business_timezone', config('app.timezone', 'Europe/Lisbon'));
    $now = now()->timezone($tz);
    $hoursUi = \App\Support\BookingStoreOpenStatus::publicUiState();
    $statusLabel = $hoursUi['label'];
    $statusClass = $hoursUi['css_class'];
    $statusSuffix = $hoursUi['suffix'];
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
            <h3 class="booking-offcanvas__agency-title mb-2">{{ $store['name'] ?? 'Loja' }}</h3>

            <div class="booking-offcanvas__info-list">
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">Morada</h4>
                    <p class="booking-offcanvas__info-value">{{ $store['address'] ?? '' }}</p>
                    <a
                        href="{{ $store['maps_url'] ?? '#' }}"
                        target="_blank"
                        rel="noopener"
                        class="booking-offcanvas__icon-btn booking-offcanvas__info-action"
                        aria-label="Abrir morada no Google Maps"
                    >
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    </a>
                </article>
                <article class="booking-offcanvas__info-item booking-offcanvas__info-item--with-action">
                    <h4 class="booking-offcanvas__info-label">Telefone</h4>
                    <p class="booking-offcanvas__info-value">{{ $store['phone'] ?? '' }}</p>
                    <a href="{{ $store['phone_tel_href'] ?? '#' }}" class="booking-offcanvas__icon-btn booking-offcanvas__info-action" aria-label="Ligar para {{ $store['phone'] ?? 'telefone' }}">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                    </a>
                </article>
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
                        <li class="{{ (int) $now->isoWeekday() === 1 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Segunda</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 2 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Terça</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 3 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Quarta</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 4 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Quinta</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 5 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Sexta</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 6 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Sábado</span><span>{{ $weekSlotLabel }}</span></li>
                        <li class="{{ (int) $now->isoWeekday() === 7 ? 'booking-offcanvas__hours-list-current' : '' }}"><span>Domingo</span><span>Encerrado</span></li>
                    </ul>
                </article>
                <article class="booking-offcanvas__info-item">
                    <div class="booking-offcanvas__follow-row">
                        <h4 class="booking-offcanvas__info-label">Siga-nos</h4>
                        <div class="booking-offcanvas__social-links">
                            <a href="{{ $store['website_url'] ?? '#' }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="Site da loja">
                                <i class="bi bi-globe" aria-hidden="true"></i>
                            </a>
                            <a href="{{ $store['instagram_url'] ?? '#' }}" target="_blank" rel="noopener" class="booking-offcanvas__social-link" aria-label="Instagram da loja">
                                <i class="bi bi-instagram" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <footer class="booking-offcanvas__footer mt-auto" aria-label="Conta">
            @if ($isBookingClientUser)
                <a href="{{ route('booking.conta.index') }}" class="booking-offcanvas-nav__link">
                    A minha conta
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <form method="post" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-link booking-offcanvas__logout p-0 text-decoration-none">Terminar sessão</button>
                </form>
            @else
                <button type="button" class="booking-offcanvas-nav__link btn btn-link text-start w-100 p-0 border-0 js-booking-open-auth-modal">
                    Iniciar sessão
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            @endif
        </footer>
    </div>
</div>
