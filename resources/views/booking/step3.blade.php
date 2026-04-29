@extends('booking.layout')

@section('title', 'Checkout')

@section('body_class', 'booking-page booking-page--step3')

@push('head')
    @php
        $bookingClientHasFullProfileAtLoad =
            ($bookingClient ?? null)
            && trim((string) (($bookingClient->name ?? ''))) !== ''
            && trim((string) (($bookingClient->email ?? ''))) !== ''
            && trim((string) (($bookingClient->phone ?? ''))) !== '';
        $bookingClientNeedsPhoneForm =
            !($bookingClient ?? null)
            || ! $bookingClientHasFullProfileAtLoad;
    @endphp
    @if($bookingClientNeedsPhoneForm)
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    @endif
@endpush

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100"
        data-booking-payment-required="{{ ($onlineBookingPaymentRequired ?? true) ? '1' : '0' }}"
        data-booking-payment-intent-url="{{ $bookingPaymentIntentUrl }}"
        data-booking-payment-complete-url="{{ $bookingPaymentCompleteUrl }}"
        data-booking-confirm-without-payment-url="{{ $bookingConfirmWithoutPaymentUrl }}"
        data-booking-deposit-percent="{{ (int) config('booking.deposit_percent') }}"
    >
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-3 pt-0">
                <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                    <div class="col-lg-8">
                        <main class="pt-1">
                            <div class="d-flex align-items-center mb-3 ps-1 booking-page-main-heading">
                                @include('booking.partials.page-back-mobile', ['backUrl' => route('booking.datetime')])
                                <h1 class="booking-services-heading h6 fw-semibold text-dark mb-0 flex-grow-1 min-width-0">Informação de contacto</h1>
                            </div>

                            <section class="booking-category-section mb-4 pb-1">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <div id="booking-checkout-error" class="alert alert-danger py-2 px-3 small mb-3 d-none" role="alert"></div>

                                        @if($bookingClient ?? null)
                                            @php
                                                $bookingClientHasFullProfile =
                                                    trim((string) ($bookingClient->name ?? '')) !== ''
                                                    && trim((string) ($bookingClient->email ?? '')) !== ''
                                                    && trim((string) ($bookingClient->phone ?? '')) !== '';
                                            @endphp
                                            @if($bookingClientHasFullProfile)
                                                <h2 class="h5 fw-semibold text-dark mb-3">Bem-vindo {{ $bookingClient->name }}</h2>
                                                <form id="booking-checkout-form" novalidate>
                                                    <input type="hidden" name="name" value="{{ e($bookingClient->name) }}">
                                                    <input type="hidden" name="email" value="{{ e($bookingClient->email ?? '') }}">
                                                    <input type="hidden" name="phone" value="{{ e($bookingClient->phone ?? '') }}">
                                                    <div class="rounded-3 bg-light border px-3 py-3 mb-3 small">
                                                        <div class="mb-2"><span class="text-dark fw-medium">{{ $bookingClient->email ?: '—' }}</span></div>
                                                        <div class="mb-0"><span class="text-dark fw-medium">{{ $bookingClient->formatted_phone ?? $bookingClient->phone ?? '—' }}</span></div>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label for="booking-contact-notes" class="form-label small text-muted mb-1">Observações</label>
                                                        <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="Deseja incluir mais alguma informação?"></textarea>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="alert alert-light border small mb-3" role="status">
                                                    Complete o seu nome e telemóvel para concluir a marcação.
                                                </div>
                                                <form id="booking-checkout-form" novalidate>
                                                    <div class="mb-3">
                                                        <label for="booking-contact-name" class="form-label small text-muted mb-1">Nome</label>
                                                        <input id="booking-contact-name" name="name" type="text" class="form-control" autocomplete="name" required value="{{ e($bookingClient->name ?? '') }}">
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-12 col-md-6">
                                                            <label for="booking-contact-phone" class="form-label small text-muted mb-1">Telemóvel</label>
                                                            <input id="booking-contact-phone" name="phone_display" type="tel" class="form-control booking-phone-input" autocomplete="tel" required value="{{ e($bookingClient->formatted_phone ?? $bookingClient->phone ?? '') }}">
                                                            <input id="booking-contact-phone-e164" name="phone" type="hidden" value="{{ e($bookingClient->phone ?? '') }}">
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label for="booking-contact-email" class="form-label small text-muted mb-1">Email</label>
                                                            <input id="booking-contact-email" name="email" type="email" class="form-control" autocomplete="email" required value="{{ e($bookingClient->email ?? '') }}" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="mb-0">
                                                        <label for="booking-contact-notes" class="form-label small text-muted mb-1">Observações</label>
                                                        <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="Alergias, preferências, observações..."></textarea>
                                                    </div>
                                                </form>
                                            @endif
                                        @else
                                            <form id="booking-checkout-form" novalidate>
                                                <div class="mb-3">
                                                    <label for="booking-contact-name" class="form-label small text-muted mb-1">Nome</label>
                                                    <input id="booking-contact-name" name="name" type="text" class="form-control" autocomplete="name" required>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label for="booking-contact-phone" class="form-label small text-muted mb-1">Telemóvel</label>
                                                        <input id="booking-contact-phone" name="phone_display" type="tel" class="form-control booking-phone-input" autocomplete="tel" required>
                                                        <input id="booking-contact-phone-e164" name="phone" type="hidden">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label for="booking-contact-email" class="form-label small text-muted mb-1">Email</label>
                                                        <input id="booking-contact-email" name="email" type="email" class="form-control" autocomplete="email" required>
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label for="booking-contact-notes" class="form-label small text-muted mb-1">Observações</label>
                                                    <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="Alergias, preferências, observações..."></textarea>
                                                </div>
                                            </form>

                                            <p class="small text-muted mt-3 mb-0">
                                                Já tens conta?
                                                <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none fw-semibold js-booking-open-auth-modal">Entrar com código</button>
                                            </p>
                                        @endif

                                    </div>
                                </div>
                            </section>

                            <section class="booking-category-section mb-4 pb-1"@if(!($onlineBookingPaymentRequired ?? true)) hidden @endif>
                                <div id="booking-payment-panel" class="card border shadow-sm rounded-3 booking-category-card d-none">
                                    <div class="card-body">
                                        <h2 class="h6 fw-semibold text-dark mb-2">Pagamento</h2>
                                        <p class="small text-muted mb-2">
                                            Pagamento de reserva no valor de <strong id="booking-pay-deposit-amount">—</strong>
                                            (<span id="booking-pay-deposit-pct">—</span>% do total). O restante
                                            <strong id="booking-pay-remaining-amount">—</strong> paga na loja no dia do serviço.
                                        </p>
                                        <div id="booking-stripe-mount" class="mb-2"></div>
                                        <p id="booking-stripe-error" class="small text-danger mb-0 d-none" role="alert"></p>
                                    </div>
                                </div>
                            </section>

                            <section class="booking-category-section mb-4 pb-1" aria-label="Política de cancelamento">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <h3 class="h6 fw-semibold text-dark mb-2">Política de cancelamento</h3>
                                        <p class="small text-muted mb-0">Por favor, note que as reservas só podem ser canceladas com um aviso prévio de 3 horas.</p>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>

                    <div class="col-lg-4 booking-summary-column">
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => 'Resumo da marcação',
                            'showBackButton' => true,
                            'backUrl' => route('booking.datetime'),
                            'showNextButton' => true,
                            'nextUrl' => '#',
                            'nextRequires' => 'checkout',
                            'nextLabel' => ($onlineBookingPaymentRequired ?? true) ? 'Pagamento' : 'Marcar',
                            'nextClass' => ($onlineBookingPaymentRequired ?? true) ? 'btn-dark' : 'btn-success',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    @if($onlineBookingPaymentRequired ?? true)
        {{-- Sem defer: garante window.Stripe antes de app.js e do mount do Payment Element (evita inputs invisíveis). --}}
        <script src="https://js.stripe.com/v3/"></script>
    @endif
    @if($bookingClientNeedsPhoneForm)
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js" defer></script>
    @endif
@endpush
