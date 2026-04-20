@php
    $isBookingClientUser = auth()->check()
        && auth()->user() instanceof \App\Models\User
        && auth()->user()->isBookingClient();
@endphp
<div class="offcanvas offcanvas-end" tabindex="-1" id="bookingStoreDetails" aria-labelledby="bookingOffcanvasTitle">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h6 mb-0 fw-semibold" id="bookingOffcanvasTitle">Menu</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="booking-offcanvas-nav mb-4" aria-label="Conta e marcação">
            <ul class="list-unstyled mb-0">
                <li>
                    @if ($isBookingClientUser)
                        <a href="{{ route('booking.conta.index') }}" class="booking-offcanvas-nav__link d-block py-2 px-2 rounded-2 text-decoration-none fw-semibold text-dark">
                            A minha conta
                        </a>
                    @else
                        <a href="{{ route('booking.acesso') }}" class="booking-offcanvas-nav__link d-block py-2 px-2 rounded-2 text-decoration-none fw-semibold text-dark">
                            A minha conta
                        </a>
                    @endif
                </li>
            </ul>
        </nav>

        <p class="small fw-semibold text-uppercase text-muted mb-3">Detalhes da loja</p>
        <div class="mb-3">
            <p class="text-muted small text-uppercase mb-1">Nome</p>
            <p class="mb-0 fw-semibold">{{ $businessName }}</p>
        </div>
        <div class="mb-3">
            <p class="text-muted small text-uppercase mb-1">Website</p>
            <a class="text-decoration-none" href="{{ config('app.url') }}" target="_blank" rel="noopener">{{ config('app.url') }}</a>
        </div>
        <p class="text-muted small mb-0">Em breve: morada, contactos e horário da loja.</p>
    </div>
</div>
