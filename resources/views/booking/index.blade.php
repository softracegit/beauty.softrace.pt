@extends('booking.layout')

@section('title', __('booking.services.page_title'))

@section('body_class', 'booking-page booking-page--services')

@section('content')
    @php
        $hasCategories = isset($categories) && $categories->isNotEmpty();
    @endphp
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-2 pt-0">
                @if (session('status'))
                    <div class="alert alert-success py-2 px-3 small mt-2 mb-2" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger py-2 px-3 small mt-2 mb-2" role="alert">{{ session('error') }}</div>
                @endif
                @if(!$hasCategories)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-light border text-center" role="status">
                                <p class="fw-semibold mb-1">{{ __('booking.services.empty_title') }}</p>
                                <p class="text-muted small mb-0">{{ __('booking.services.empty_hint') }}</p>
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
                        $bookingServiceOptionsCatalog = [];
                    @endphp
                    <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                        <div class="col-lg-8">
                            <main class="pt-1">
                                <h2
                                    id="booking-services-page-heading"
                                    class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1"
                                    data-heading-empty="{{ __('booking.services.heading_empty') }}"
                                    data-heading-has-items="{{ __('booking.services.heading_has_items') }}"
                                >{{ __('booking.services.heading_empty') }}</h2>
                                <section class="booking-category-section booking-category-section--chips mb-4 pb-1" aria-label="{{ __('booking.services.category_shortcuts_aria') }}">
                                    <div class="card border shadow-sm rounded-3 booking-category-card booking-category-card--chips">
                                        <nav class="booking-category-chips" aria-label="{{ __('booking.services.categories_nav_aria') }}">
                                            <button type="button" class="booking-category-chips__arrow booking-category-chips__arrow--left" aria-label="{{ __('booking.services.scroll_categories_prev_aria') }}">
                                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                            </button>
                                            <div class="booking-category-chips__scroll">
                                                @foreach($categories as $category)
                                                    @php
                                                        $categorySlug = \Illuminate\Support\Str::slug($category->name);
                                                        $categoryAnchorId = 'cat-'.$categorySlug.'-'.$category->id;
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="booking-category-chip"
                                                        data-scroll-target="{{ $categoryAnchorId }}"
                                                        aria-controls="{{ $categoryAnchorId }}"
                                                    >{{ $category->name }}</button>
                                                @endforeach
                                            </div>
                                            <button type="button" class="booking-category-chips__arrow booking-category-chips__arrow--right" aria-label="{{ __('booking.services.scroll_categories_next_aria') }}">
                                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                            </button>
                                        </nav>
                                    </div>
                                </section>
                                @foreach($categories as $category)
                                    @php
                                        $categorySlug = \Illuminate\Support\Str::slug($category->name);
                                        $categoryAnchorId = 'cat-'.$categorySlug.'-'.$category->id;
                                        $categoryHeadingId = 'cat-heading-'.$categorySlug.'-'.$category->id;
                                    @endphp
                                    <section
                                        id="{{ $categoryAnchorId }}"
                                        class="booking-category-section mb-4 pb-1"
                                        aria-labelledby="{{ $categoryHeadingId }}"
                                    >
                                        <div class="card border shadow-sm rounded-3 overflow-hidden booking-category-card">
                                            <div class="booking-category-card__header">
                                                <h2 id="{{ $categoryHeadingId }}" class="booking-category-heading h6 small fw-semibold text-muted mb-0">{{ $category->name }}</h2>
                                            </div>
                                            <ul class="list-group list-group-flush" role="list">
                                                @foreach($category->services as $service)
                                                    @php
                                                        $feesPayload = $service->fees->map(static function ($fee) {
                                                            return [
                                                                'fee_id' => (int) $fee->id,
                                                                'name' => $fee->name,
                                                                'price' => round((float) $fee->price, 2),
                                                                'priceFormatted' => $fee->formatted_price,
                                                            ];
                                                        })->values()->all();
                                                        $extrasPayload = $service->extras->map(static function ($extra) {
                                                            return [
                                                                'id' => (int) $extra->id,
                                                                'name' => $extra->name,
                                                                'duration' => $extra->formatted_duration,
                                                                'durationMinutes' => (int) ($extra->duration ?? 0),
                                                                'price' => round((float) $extra->price, 2),
                                                                'priceFormatted' => $extra->formatted_price,
                                                            ];
                                                        })->values()->all();
                                                        $hasOptions = $service->options->isNotEmpty();
                                                        if ($hasOptions) {
                                                            $minPrice = (float) $service->options->min(fn ($o) => (float) ($o->online_price ?? $o->price));
                                                            $minDur = (int) $service->options->min('duration');
                                                            $maxDur = (int) $service->options->max('duration');
                                                            $rowDurationLabel = $minDur === $maxDur
                                                                ? $bookingFmtMinutes($minDur)
                                                                : $bookingFmtMinutes($minDur).'+';
                                                            $rowPriceLabel = __('booking.services.price_from').' '.number_format($minPrice, 2, ',', '.')."\u{00A0}€";
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
                                                                'extras' => $extrasPayload,
                                                                'fees' => $feesPayload,
                                                            ];
                                                            $bookingServiceOptionsCatalog[(int) $service->id] = $payload;
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
                                                                'extras' => $extrasPayload,
                                                                'fees' => $feesPayload,
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
                                'summaryTitle' => __('booking.services.summary_title'),
                                'showNextButton' => true,
                                'nextUrl' => route('booking.technician', ['store' => $bookingStoreSlug], false),
                            ])
                        </div>
                    </div>
                    @if(! empty($bookingServiceOptionsCatalog))
                        <script type="application/json" id="booking-services-options-catalog">@json($bookingServiceOptionsCatalog)</script>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
