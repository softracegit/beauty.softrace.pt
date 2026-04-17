@extends('booking.layout')

@section('title', 'Serviços')

@section('body_class', 'booking-page booking-page--services')

@section('content')
    @php
        $hasCategories = isset($categories) && $categories->isNotEmpty();
    @endphp
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-3 pt-0">
                @if(!$hasCategories)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-light border text-center" role="status">
                                <p class="fw-semibold mb-1">Nenhum serviço disponível.</p>
                                <p class="text-muted small mb-0">Cria categorias e serviços no CRM para os veres listados aqui.</p>
                            </div>
                        </div>
                    </div>
                @else
                    @php
                        $bookingFmtMinutes = static function (int $minutes): string {
                            $hours = intdiv($minutes, 60);
                            $mins = $minutes % 60;
                            if ($hours > 0 && $mins > 0) {
                                return $hours.'h '.$mins.' min';
                            }
                            if ($hours > 0) {
                                return $hours.'h';
                            }

                            return $mins.' min';
                        };
                    @endphp
                    <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                        <div class="col-lg-8">
                            <main class="pt-1">
                                <h2 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1">Adicione os serviços que pretende</h2>
                                <nav class="booking-category-chips" aria-label="Categorias de serviço">
                                    <div class="booking-category-chips__scroll">
                                        @foreach($categories as $category)
                                            <a
                                                href="#cat-{{ $category->id }}"
                                                class="booking-category-chip"
                                            >{{ $category->name }}</a>
                                        @endforeach
                                    </div>
                                </nav>
                                @foreach($categories as $category)
                                    <section
                                        id="cat-{{ $category->id }}"
                                        class="booking-category-section mb-4 pb-1"
                                        aria-labelledby="cat-heading-{{ $category->id }}"
                                    >
                                        <div class="card border shadow-sm rounded-3 overflow-hidden booking-category-card">
                                            <div class="booking-category-card__header">
                                                <h2 id="cat-heading-{{ $category->id }}" class="booking-category-heading h6 small fw-semibold text-muted mb-0">{{ $category->name }}</h2>
                                            </div>
                                            <ul class="list-group list-group-flush" role="list">
                                                @foreach($category->services as $service)
                                                    @php
                                                        $hasOptions = $service->options->isNotEmpty();
                                                        if ($hasOptions) {
                                                            $minPrice = (float) $service->options->min(fn ($o) => (float) ($o->online_price ?? $o->price));
                                                            $minDur = (int) $service->options->min('duration');
                                                            $maxDur = (int) $service->options->max('duration');
                                                            $rowDurationLabel = $minDur === $maxDur
                                                                ? $bookingFmtMinutes($minDur)
                                                                : $bookingFmtMinutes($minDur).'+';
                                                            $rowPriceLabel = 'Desde '.number_format($minPrice, 2, ',', '.')."\u{00A0}€";
                                                            $optionsPayload = $service->options->map(static function ($opt) {
                                                                $p = $opt->online_price ?? $opt->price;

                                                                return [
                                                                    'id' => $opt->id,
                                                                    'name' => $opt->name,
                                                                    'duration' => $opt->formatted_duration,
                                                                    'durationMinutes' => (int) $opt->duration,
                                                                    'price' => round((float) $p, 2),
                                                                    'priceFormatted' => number_format((float) $p, 2, ',', '.')."\u{00A0}€",
                                                                ];
                                                            })->values()->all();
                                                            $payload = [
                                                                'id' => $service->id,
                                                                'name' => $service->name,
                                                                'hasOptions' => true,
                                                                'summaryPriceLabel' => $rowPriceLabel,
                                                                'summaryDurationLabel' => $rowDurationLabel,
                                                                'options' => $optionsPayload,
                                                            ];
                                                        } else {
                                                            $price = $service->online_price ?? $service->price;
                                                            $rowDurationLabel = $service->formatted_duration;
                                                            $rowPriceLabel = number_format((float) $price, 2, ',', '.')."\u{00A0}€";
                                                            $payload = [
                                                                'id' => $service->id,
                                                                'name' => $service->name,
                                                                'duration' => $service->formatted_duration,
                                                                'durationMinutes' => (int) ($service->duration ?? 0),
                                                                'price' => round((float) $price, 2),
                                                                'priceFormatted' => number_format((float) $price, 2, ',', '.')."\u{00A0}€",
                                                            ];
                                                        }
                                                    @endphp
                                                    <li class="list-group-item p-0 border-start-0 border-end-0">
                                                        <button
                                                            type="button"
                                                            class="booking-row booking-row--btn w-100 text-start"
                                                            data-service='@json($payload)'
                                                        >
                                                            <span class="booking-row__text">
                                                                <span class="booking-row__name">{{ $service->name }}</span>
                                                                <span class="booking-row__duration">{{ $rowDurationLabel }}</span>
                                                                @if($service->description)
                                                                    <span class="booking-row__meta">{{ \Illuminate\Support\Str::limit(strip_tags($service->description), 80) }}</span>
                                                                @endif
                                                            </span>
                                                            <span class="booking-row__aside">
                                                                <span class="booking-row__price">{{ $rowPriceLabel }}</span>
                                                                <span class="booking-row__chevron" aria-hidden="true">
                                                                    <i class="bi bi-chevron-right"></i>
                                                                </span>
                                                            </span>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </section>
                                @endforeach
                            </main>
                        </div>

                        <div class="col-lg-4 booking-summary-column">
                            @include('booking.partials.summary-panel', [
                                'summaryTitle' => 'Resumo da marcação',
                                'showNextButton' => true,
                                'nextUrl' => route('booking.technician'),
                            ])
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow rounded-3">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title h5 fw-semibold" id="bookingModalTitle"></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <p id="booking-modal-service-meta" class="text-muted small mb-0"></p>
                    <div id="booking-modal-options-wrap" class="d-none mt-3">
                        <p class="small fw-semibold text-dark mb-2">Opções</p>
                        <div id="booking-modal-options" class="booking-modal-options" role="radiogroup" aria-label="Variante do serviço"></div>
                        <p id="booking-modal-options-error" class="booking-modal-options-error text-danger small mt-2 mb-0 d-none" role="alert"></p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-dark w-100" id="booking-modal-confirm">Adicionar serviço</button>
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
