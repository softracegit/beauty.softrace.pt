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
            <div @class(['container booking-container-wide px-3 pb-2 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
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
                                @if($bookingAgentEmpty ?? false)
                                    <p class="fw-semibold mb-0">{{ __('booking.services.agent_empty_title') }}</p>
                                @else
                                    <p class="fw-semibold mb-1">{{ __('booking.services.empty_title') }}</p>
                                    <p class="text-muted small mb-0">{{ __('booking.services.empty_hint') }}</p>
                                @endif
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
                        $bookingPriceStoreId = (int) ($bookingStore?->id ?? 0);
                        $bookingIssuePriceToken = static function (string $kind, int $id, float $price) use ($bookingPriceStoreId): string {
                            return \App\Support\BookingCartPriceToken::issue($kind, $id, $price, null, $bookingPriceStoreId > 0 ? $bookingPriceStoreId : null);
                        };
                    @endphp
                    <div @class(['row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row', 'booking-elegant-split' => ($bookingUsesRefinedLayout ?? false)])>
                        <div class="col-lg-8 booking-elegant-main">
                            <main class="pt-1">
                                @include('booking.partials.elegant-flow-header', ['elegantActiveStep' => 1])
                                <h2
                                    id="booking-services-page-heading"
                                    @class([
                                        'booking-services-heading h6 fw-semibold text-dark mb-3 ps-1',
                                        'booking-elegant-hide-heading' => ($bookingUsesRefinedLayout ?? false),
                                    ])
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
                                                        $feesPayload = $service->fees->map(static function ($fee) use ($bookingIssuePriceToken) {
                                                            $feePrice = round((float) $fee->price, 2);

                                                            return [
                                                                'fee_id' => (int) $fee->id,
                                                                'name' => $fee->name,
                                                                'price' => $feePrice,
                                                                'priceFormatted' => $fee->formatted_price,
                                                                'price_token' => $bookingIssuePriceToken(\App\Support\BookingCartPriceToken::KIND_FEE, (int) $fee->id, $feePrice),
                                                            ];
                                                        })->values()->all();
                                                        $extrasPayload = $service->extras->map(static function ($extra) use ($bookingIssuePriceToken) {
                                                            $extraPrice = round((float) $extra->price, 2);
                                                            $extraCategory = $extra->extraCategory;

                                                            return [
                                                                'id' => (int) $extra->id,
                                                                'name' => $extra->name,
                                                                'shortDescription' => $extra->short_description ? (string) $extra->short_description : null,
                                                                'description' => $extra->description ? strip_tags((string) $extra->description) : null,
                                                                'duration' => $extra->formatted_duration,
                                                                'durationMinutes' => (int) ($extra->duration ?? 0),
                                                                'price' => $extraPrice,
                                                                'priceFormatted' => $extra->formatted_price,
                                                                'price_token' => $bookingIssuePriceToken(\App\Support\BookingCartPriceToken::KIND_EXTRA, (int) $extra->id, $extraPrice),
                                                                'categoryId' => $extra->extra_category_id ? (int) $extra->extra_category_id : null,
                                                                'categoryName' => $extraCategory?->name ? (string) $extraCategory->name : null,
                                                                'categorySortOrder' => (int) ($extraCategory?->sort_order ?? 0),
                                                            ];
                                                        })->values()->all();
                                                        $hasOptions = $service->options->isNotEmpty();
                                                        if ($hasOptions) {
                                                            // "Desde" e duração só contam as variantes de escolha única (as adicionais somam-se)
                                                            $choiceOptions = $service->options->reject(fn ($o) => $o->isAddon());
                                                            if ($choiceOptions->isEmpty()) {
                                                                $choiceOptions = $service->options;
                                                            }
                                                            $minPrice = (float) $choiceOptions->min(fn ($o) => (float) ($o->online_price ?? $o->price));
                                                            $minDur = (int) $choiceOptions->min('duration');
                                                            $maxDur = (int) $choiceOptions->max('duration');
                                                            $rowDurationLabel = $minDur === $maxDur
                                                                ? $bookingFmtMinutes($minDur)
                                                                : $bookingFmtMinutes($minDur).'+';
                                                            // Só há uma escolha (o próprio serviço + adicionais): o preço é fixo, sem "Desde"
                                                            $rowChoiceCount = $choiceOptions->count();
                                                            $rowPriceLabel = ($rowChoiceCount > 1 ? __('booking.services.price_from').' ' : '').number_format($minPrice, 2, ',', '.')."\u{00A0}€";
                                                            $optionsPayload = $service->options->map(static function ($opt) use ($bookingIssuePriceToken) {
                                                                $p = round((float) ($opt->online_price ?? $opt->price), 2);

                                                                return [
                                                                    'id' => $opt->id,
                                                                    'name' => $opt->name,
                                                                    'type' => \App\Models\ServiceOption::normalizeType($opt->type),
                                                                    'shortDescription' => $opt->short_description ? (string) $opt->short_description : null,
                                                                    'description' => $opt->description ? strip_tags((string) $opt->description) : null,
                                                                    'duration' => $opt->formatted_duration,
                                                                    'durationMinutes' => (int) $opt->duration,
                                                                    'price' => $p,
                                                                    'priceFormatted' => number_format($p, 2, ',', '.')."\u{00A0}€",
                                                                    'price_token' => $bookingIssuePriceToken(\App\Support\BookingCartPriceToken::KIND_OPTION, (int) $opt->id, $p),
                                                                ];
                                                            })->values()->all();
                                                            $payload = [
                                                                'id' => $service->id,
                                                                'name' => $service->name,
                                                                'shortDescription' => $service->short_description ? (string) $service->short_description : null,
                                                                'description' => $service->description ? strip_tags((string) $service->description) : null,
                                                                'hasOptions' => true,
                                                                'summaryPriceLabel' => $rowPriceLabel,
                                                                'summaryDurationLabel' => $rowDurationLabel,
                                                                'catalogMinPrice' => round($minPrice, 2),
                                                                'options' => $optionsPayload,
                                                                'extras' => $extrasPayload,
                                                                'fees' => $feesPayload,
                                                            ];
                                                            $bookingServiceOptionsCatalog[(int) $service->id] = $payload;
                                                        } else {
                                                            $price = round((float) ($service->online_price ?? $service->price), 2);
                                                            $rowDurationLabel = $service->formatted_duration;
                                                            $rowPriceLabel = number_format($price, 2, ',', '.')."\u{00A0}€";
                                                            $payload = [
                                                                'id' => $service->id,
                                                                'name' => $service->name,
                                                                'shortDescription' => $service->short_description ? (string) $service->short_description : null,
                                                                'description' => $service->description ? strip_tags((string) $service->description) : null,
                                                                'duration' => $service->formatted_duration,
                                                                'durationMinutes' => (int) ($service->duration ?? 0),
                                                                'price' => $price,
                                                                'priceFormatted' => number_format($price, 2, ',', '.')."\u{00A0}€",
                                                                'price_token' => $bookingIssuePriceToken(\App\Support\BookingCartPriceToken::KIND_SERVICE, (int) $service->id, $price),
                                                                'extras' => $extrasPayload,
                                                                'fees' => $feesPayload,
                                                            ];
                                                        }
                                                        $serviceShortDescription = trim((string) ($service->short_description ?? ''));
                                                        $serviceFullDescription = trim(strip_tags((string) ($service->description ?? '')));
                                                        $serviceHasCopy = $serviceShortDescription !== '' || $serviceFullDescription !== '';
                                                    @endphp
                                                    <li class="list-group-item p-0 border-start-0 border-end-0">
                                                        <div
                                                            class="booking-row booking-row--btn w-100 text-start{{ $serviceHasCopy ? ' booking-row--has-copy' : '' }}"
                                                            role="button"
                                                            tabindex="0"
                                                            data-service='@json($payload)'
                                                        >
                                                            <span class="booking-row__top">
                                                                <span class="booking-row__text">
                                                                    <span class="booking-row__name">{{ $service->name }}</span>
                                                                    @if($serviceShortDescription !== '')
                                                                        <span class="booking-row__short">{{ $serviceShortDescription }}</span>
                                                                    @endif
                                                                    <span class="booking-row__price-nudge d-none" hidden></span>
                                                                    @if($serviceFullDescription !== '')
                                                                        <button
                                                                            type="button"
                                                                            class="booking-row__included-toggle"
                                                                            aria-expanded="false"
                                                                        >
                                                                            <span>{{ __('booking.services.whats_included') }}</span>
                                                                            <i class="bi bi-caret-down-fill booking-row__included-caret" aria-hidden="true"></i>
                                                                        </button>
                                                                    @endif
                                                                </span>
                                                                <span class="booking-row__aside">
                                                                    <span class="booking-row__aside-meta">
                                                                        @if($rowDurationLabel)
                                                                            <span class="booking-row__duration">{{ $rowDurationLabel }}</span>
                                                                            <span class="booking-row__aside-sep" aria-hidden="true">·</span>
                                                                        @endif
                                                                        <span class="booking-row__price">{{ $rowPriceLabel }}</span>
                                                                    </span>
                                                                    <span class="booking-row__chevron booking-modal-option__radio" aria-hidden="true">
                                                                        {{-- Sem name: é só indicador visual; o estado vem do resumo via JS (name partilhado só deixava 1 checked). --}}
                                                                        <input type="radio" value="{{ $service->id }}" tabindex="-1">
                                                                    </span>
                                                                </span>
                                                            </span>
                                                            @if($serviceFullDescription !== '')
                                                                <span class="booking-row__description is-collapsed">{{ $serviceFullDescription }}</span>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </section>
                                @endforeach
                            </main>
                        </div>

                        <div @class(['col-lg-4 booking-summary-column', 'booking-elegant-summary' => ($bookingUsesRefinedLayout ?? false)])>
                            @include('booking.partials.summary-panel', [
                                'summaryTitle' => __('booking.services.summary_title'),
                                'showNextButton' => true,
                                'nextUrl' => ($bookingSkipStaffStep ?? false)
                                    ? route('booking.datetime', ['store' => $bookingStoreSlug], false)
                                    : route('booking.technician', ['store' => $bookingStoreSlug], false),
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
