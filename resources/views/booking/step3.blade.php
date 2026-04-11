@extends('booking.layout')

@section('title', 'Checkout')

@section('body_class', 'booking-page booking-page--step3')

@push('head')
    @unless($bookingClient ?? null)
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    @endunless
@endpush

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100" data-booking-submit-url="{{ route('booking.submit') }}">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-3 pt-0">
                <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                    <div class="col-lg-8">
                        <main class="pt-1">
                            <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1">Informação de contacto</h1>

                            <section class="booking-category-section mb-4 pb-1">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <div id="booking-checkout-error" class="alert alert-danger py-2 px-3 small mb-3 d-none" role="alert"></div>
                                        <div id="booking-checkout-magic-wrap" class="small mb-3 d-none">
                                            <a id="booking-checkout-magic-link" href="{{ $acessoUrl }}" class="fw-semibold">Pedir link de acesso por email</a>
                                        </div>

                                        @if($bookingClient ?? null)
                                            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                                <p class="small text-muted mb-0">Sessão iniciada como <span class="text-dark fw-semibold">{{ $bookingClient->name }}</span></p>
                                                <form method="post" action="{{ route('logout') }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-link btn-sm text-muted p-0">Sair</button>
                                                </form>
                                            </div>
                                            <form id="booking-checkout-form" novalidate>
                                                <input type="hidden" name="name" value="{{ e($bookingClient->name) }}">
                                                <input type="hidden" name="email" value="{{ e($bookingClient->email ?? '') }}">
                                                <input type="hidden" name="phone" value="{{ e($bookingClient->phone ?? '') }}">
                                                <div class="rounded-3 bg-light border px-3 py-3 mb-3 small">
                                                    <div class="mb-2"><span class="text-muted">Email</span><br><span class="text-dark fw-medium">{{ $bookingClient->email ?: '—' }}</span></div>
                                                    <div class="mb-0"><span class="text-muted">Telemóvel</span><br><span class="text-dark fw-medium">{{ $bookingClient->formatted_phone ?? $bookingClient->phone ?? '—' }}</span></div>
                                                </div>
                                                <div class="mb-0">
                                                    <label for="booking-contact-notes" class="form-label small text-muted mb-1">Observações</label>
                                                    <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="Alergias, preferências, observações..."></textarea>
                                                </div>
                                            </form>
                                            @if($definirPasswordUrl ?? null)
                                                <p class="small text-muted mt-3 mb-0">
                                                    <a href="{{ $definirPasswordUrl }}" class="text-decoration-none fw-semibold">Definir ou alterar password</a>
                                                    <span class="text-muted"> (opcional — também podes usar só o link por email)</span>
                                                </p>
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
                                                <a href="{{ $acessoUrl }}" class="text-decoration-none fw-semibold">Link por email</a>
                                                <span class="text-muted"> · </span>
                                                <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">Login com password</a>
                                            </p>
                                        @endif
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
                            'nextLabel' => 'Marcar',
                            'nextClass' => 'btn-success',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="bookingStoreDetails" aria-labelledby="bookingStoreDetailsTitle">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h6 mb-0 fw-semibold" id="bookingStoreDetailsTitle">Detalhes da loja</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body">
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
@endsection

@push('scripts')
    @unless($bookingClient ?? null)
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js" defer></script>
    @endunless
@endpush
