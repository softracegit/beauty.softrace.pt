@extends('booking.layout')

@section('title', 'Data e hora')

@section('body_class', 'booking-page booking-page--datetime')

@push('head')
    <link rel="stylesheet" href="{{ asset('template/vendor/flatpickr/flatpickr.min.css') }}">
@endpush

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100" data-booking-availability-url="{{ route('booking.availability') }}">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-3 pt-0">
                <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                    <div class="col-lg-8">
                        <main class="pt-1">
                            <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1">Escolha uma data e hora para a marcação</h1>

                            <section class="booking-category-section mb-4 pb-1">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <input id="booking-calendar" type="text" class="form-control d-none" aria-hidden="true" tabindex="-1">
                                        <div id="booking-week-view" class="booking-week-view d-none">
                                            <div class="booking-week-view__header">
                                                <button type="button" id="booking-week-prev" class="booking-week-view__nav" aria-label="Semana anterior">
                                                    <i class="bi bi-arrow-left"></i>
                                                </button>
                                                <h2 id="booking-week-title" class="booking-week-view__title mb-0" aria-live="polite"></h2>
                                                <button type="button" id="booking-week-next" class="booking-week-view__nav" aria-label="Semana seguinte">
                                                    <i class="bi bi-arrow-right"></i>
                                                </button>
                                            </div>
                                            <div id="booking-week-days" class="booking-week-view__days" role="listbox" aria-label="Selecionar dia da semana"></div>
                                        </div>
                                        <button type="button" id="booking-calendar-view-toggle" class="booking-calendar-view-toggle" aria-expanded="false" aria-controls="booking-week-view" aria-label="Alternar para vista semanal">
                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                        </button>

                                        <div id="booking-slots" class="booking-slots">
                                            <h2 id="booking-slots-day" class="booking-slots__day h6 fw-semibold mb-3">Seleciona um dia</h2>

                                            <div id="booking-slots-status" class="booking-slots__status small mb-3" aria-live="polite"></div>

                                            <div id="booking-slots-periods">
                                                <div class="booking-slots__period mb-3">
                                                    <h3 class="booking-slots__period-title small text-muted mb-2">Manhã</h3>
                                                    <div id="booking-slots-morning" class="booking-slots__list"></div>
                                                </div>

                                                <div class="booking-slots__period">
                                                    <h3 class="booking-slots__period-title small text-muted mb-2">Tarde</h3>
                                                    <div id="booking-slots-afternoon" class="booking-slots__list"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>

                    <div class="col-lg-4 booking-summary-column">
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => 'Resumo da marcação',
                            'showBackButton' => true,
                            'backUrl' => route('booking.technician'),
                            'showNextButton' => true,
                            'nextUrl' => route('booking.step3'),
                            'nextRequires' => 'datetime',
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
    <script src="{{ asset('template/vendor/flatpickr/flatpickr.min.js') }}" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js" defer></script>
@endpush
