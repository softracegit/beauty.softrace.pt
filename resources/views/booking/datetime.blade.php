@extends('booking.layout')

@section('title', 'Data e hora')

@section('body_class', 'booking-page booking-page--datetime')

@push('head')
    <link rel="stylesheet" href="{{ asset('template/vendor/flatpickr/flatpickr.min.css') }}">
@endpush

@section('content')
    <div
        class="booking-app d-flex flex-column min-vh-100"
        data-booking-availability-url="{{ route('booking.availability', ['store' => $bookingStoreSlug], false) }}"
        data-booking-valid-agent-ids="{{ implode(',', $bookingValidAgentIds ?? []) }}"
    >
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-2 pt-0">
                <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                    <div class="col-lg-8">
                        <main class="pt-1">
                            <div class="d-flex align-items-center mb-3 ps-1 booking-page-main-heading">
                                <h1 class="booking-services-heading h6 fw-semibold text-dark mb-0 flex-grow-1 min-width-0">Dia e hora da marcação</h1>
                            </div>

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
                                        <button type="button" id="booking-calendar-view-toggle" class="booking-calendar-view-toggle is-week" aria-expanded="true" aria-controls="booking-week-view" aria-label="Alternar para vista mensal">
                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                        </button>

                                        <div id="booking-slots" class="booking-slots">
                                            <h2 id="booking-slots-day" class="booking-slots__day h6 fw-semibold mb-3">Seleciona um dia</h2>

                                            <div id="booking-slots-status" class="booking-slots__status small mb-3" aria-live="polite"></div>

                                            <div id="booking-slots-suggested-wrap" class="booking-slots-suggested-wrap mb-3 d-none">
                                                <p class="booking-slots-suggested-wrap__label small fw-semibold text-dark mb-2">Próximos horários sugeridos</p>
                                                <div id="booking-slots-suggested-list" class="booking-slots__list"></div>
                                            </div>

                                            <div id="booking-slots-periods" class="d-none">
                                                <div class="booking-slots__period mb-3">
                                                    <h3 class="booking-slots__period-title small text-muted mb-2">Manhã</h3>
                                                    <div id="booking-slots-morning" class="booking-slots__list"></div>
                                                </div>

                                                <div class="booking-slots__period">
                                                    <h3 class="booking-slots__period-title small text-muted mb-2">Tarde</h3>
                                                    <div id="booking-slots-afternoon" class="booking-slots__list"></div>
                                                </div>
                                            </div>

                                            <button type="button" id="booking-slots-more" class="btn btn-link btn-sm px-0 text-decoration-none booking-slots-more d-none" aria-expanded="false" aria-controls="booking-slots-periods">
                                                Ver mais horários
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>

                    <div class="col-lg-4 booking-summary-column">
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => 'Resumo da marcação',
                            'showNextButton' => true,
                            'nextUrl' => route('booking.step3', ['store' => $bookingStoreSlug], false),
                            'nextRequires' => 'datetime',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    <script src="{{ asset('template/vendor/flatpickr/flatpickr.min.js') }}" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js" defer></script>
@endpush
