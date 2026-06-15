@extends('booking.layout')

@section('title', __('booking.flow.step3_page_title'))

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
            <div @class(['container booking-container-wide px-3 pb-2 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
                <div @class(['row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row', 'booking-elegant-split' => ($bookingUsesRefinedLayout ?? false)])>
                    <div class="col-lg-8 booking-elegant-main">
                        <main class="pt-1">
                            @include('booking.partials.elegant-flow-header', [
                                'elegantActiveStep' => 4,
                                'elegantFlowTitle' => __('booking.flow.step3_heading'),
                            ])
                            <div @class(['d-flex align-items-center mb-3 ps-1 booking-page-main-heading', 'booking-elegant-hide-heading' => ($bookingUsesRefinedLayout ?? false)])>
                                <h1 class="booking-services-heading h6 fw-semibold text-dark mb-0 flex-grow-1 min-width-0">{{ __('booking.flow.step3_heading') }}</h1>
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
                                                $welcomeFirst = trim((string) ($bookingClient->name ?? ''));
                                                $welcomeFirst = $welcomeFirst !== '' ? explode(' ', $welcomeFirst, 2)[0] : '';
                                            @endphp
                                            <h2 class="h6 fw-semibold text-dark mb-2 booking-step3-welcome">
                                                @if($welcomeFirst !== '')
                                                    {{ __('booking.flow.welcome_name', ['name' => $welcomeFirst]) }}
                                                @else
                                                    {{ __('booking.flow.welcome') }}
                                                @endif
                                            </h2>
                                            @if($bookingClientHasFullProfile)
                                                <form id="booking-checkout-form" novalidate>
                                                    <input type="hidden" name="name" value="{{ e($bookingClient->name) }}">
                                                    <input type="hidden" name="email" value="{{ e($bookingClient->email ?? '') }}">
                                                    <input type="hidden" name="phone" value="{{ e($bookingClient->phone ?? '') }}">
                                                    <div class="rounded-3 bg-light border px-3 py-3 mb-3 small">
                                                        <div class="mb-2"><span class="text-dark fw-medium">{{ $bookingClient->email ?: '—' }}</span></div>
                                                        <div class="mb-0"><span class="text-dark fw-medium">{{ $bookingClient->formatted_phone ?? $bookingClient->phone ?? '—' }}</span></div>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label for="booking-contact-notes" class="form-label small text-muted mb-1">{{ __('booking.flow.notes_label') }}</label>
                                                        <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="{{ __('booking.flow.notes_placeholder_logged_in') }}"></textarea>
                                                    </div>
                                                </form>
                                            @else
                                                <form id="booking-checkout-form" novalidate>
                                                    <div class="alert alert-light border small mb-3" role="status">
                                                        {{ __('booking.flow.complete_profile_alert') }}
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="booking-contact-name" class="form-label small text-muted mb-1">{{ __('booking.flow.name_label') }}</label>
                                                        <input id="booking-contact-name" name="name" type="text" class="form-control" autocomplete="name" required value="{{ e($bookingClient->name ?? '') }}">
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-12 col-md-6">
                                                            <label for="booking-contact-phone" class="form-label small text-muted mb-1">{{ __('booking.flow.phone_label') }}</label>
                                                            <input id="booking-contact-phone" name="phone_display" type="tel" class="form-control booking-phone-input" autocomplete="tel" required value="{{ e($bookingClient->formatted_phone ?? $bookingClient->phone ?? '') }}">
                                                            <input id="booking-contact-phone-e164" name="phone" type="hidden" value="{{ e($bookingClient->phone ?? '') }}">
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label for="booking-contact-email" class="form-label small text-muted mb-1">{{ __('booking.flow.email_label') }}</label>
                                                            <input id="booking-contact-email" name="email" type="email" class="form-control" autocomplete="email" required value="{{ e($bookingClient->email ?? '') }}" @if(trim((string) ($bookingClient->email ?? '')) !== '') readonly @endif>
                                                        </div>
                                                    </div>

                                                    <div class="mb-0">
                                                        <label for="booking-contact-notes" class="form-label small text-muted mb-1">{{ __('booking.flow.notes_label') }}</label>
                                                        <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="{{ __('booking.flow.notes_placeholder') }}"></textarea>
                                                    </div>
                                                </form>
                                            @endif
                                        @else
                                            <h2 class="h6 fw-semibold text-dark mb-2 booking-step3-welcome">{{ __('booking.flow.welcome') }}</h2>
                                            <form id="booking-checkout-form" novalidate>
                                                <div class="mb-3">
                                                    <label for="booking-contact-name" class="form-label small text-muted mb-1">{{ __('booking.flow.name_label') }}</label>
                                                    <input id="booking-contact-name" name="name" type="text" class="form-control" autocomplete="name" required>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label for="booking-contact-phone" class="form-label small text-muted mb-1">{{ __('booking.flow.phone_label') }}</label>
                                                        <input id="booking-contact-phone" name="phone_display" type="tel" class="form-control booking-phone-input" autocomplete="tel" required>
                                                        <input id="booking-contact-phone-e164" name="phone" type="hidden">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label for="booking-contact-email" class="form-label small text-muted mb-1">{{ __('booking.flow.email_label') }}</label>
                                                        <input id="booking-contact-email" name="email" type="email" class="form-control" autocomplete="email" required>
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label for="booking-contact-notes" class="form-label small text-muted mb-1">{{ __('booking.flow.notes_label') }}</label>
                                                    <textarea id="booking-contact-notes" name="notes" class="form-control" rows="4" placeholder="{{ __('booking.flow.notes_placeholder') }}"></textarea>
                                                </div>
                                            </form>

                                            <p class="small text-muted mt-3 mb-0">
                                                {{ __('booking.flow.already_have_account') }}
                                                <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none fw-semibold js-booking-open-auth-modal">{{ __('booking.flow.login_with_code') }}</button>
                                            </p>
                                        @endif

                                    </div>
                                </div>
                            </section>

                            <section class="booking-category-section mb-4 pb-1"@if(!($onlineBookingPaymentRequired ?? true)) hidden @endif aria-label="{{ __('booking.flow.invoice_section_aria') }}">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <h2 class="h6 fw-semibold text-dark mb-2">{{ __('booking.flow.invoice_heading') }}</h2>
                                        @include('booking.partials.checkout-invoice-options', ['bookingClient' => $bookingClient ?? null])
                                    </div>
                                </div>
                            </section>

                            <section class="booking-category-section mb-4 pb-1"@if(!($onlineBookingPaymentRequired ?? true)) hidden @endif>
                                <div id="booking-payment-panel" class="card border shadow-sm rounded-3 booking-category-card d-none">
                                    <div class="card-body p-3 p-md-4">
                                        <header class="mb-3">
                                            <h2 class="h6 fw-semibold text-dark mb-2">{{ __('booking.flow.payment_heading') }}</h2>
                                            <p class="small text-muted mb-0">
                                                {{ __('booking.flow.payment_intro') }}
                                            </p>
                                        </header>

                                        <div class="booking-payment-breakdown rounded-3 mb-3">
                                            <div class="booking-payment-breakdown__row">
                                                <span class="booking-payment-breakdown__label">{{ __('booking.flow.payment_total') }}</span>
                                                <span class="booking-payment-breakdown__value" id="booking-pay-total-amount">—</span>
                                            </div>
                                            @php
                                                $depositLine = __('booking.flow.payment_deposit', ['percent' => 'DEPOSIT_PCT_PLACEHOLDER']);
                                                [$depositBefore, $depositAfter] = array_pad(explode('DEPOSIT_PCT_PLACEHOLDER', $depositLine, 2), 2, '');
                                            @endphp
                                            <div class="booking-payment-breakdown__row">
                                                <span class="booking-payment-breakdown__label">{!! $depositBefore !!}<span id="booking-pay-deposit-pct">—</span>{!! $depositAfter !!}</span>
                                                <span class="booking-payment-breakdown__value" id="booking-pay-deposit-amount">—</span>
                                            </div>
                                            <div class="booking-payment-breakdown__row booking-payment-breakdown__row--muted">
                                                <span class="booking-payment-breakdown__label">{{ __('booking.flow.payment_remaining') }}</span>
                                                <span class="booking-payment-breakdown__value" id="booking-pay-remaining-amount">—</span>
                                            </div>
                                        </div>
                                        @if(($walletBalanceCents ?? 0) > 0)
                                            <div class="booking-payment-wallet-option rounded-3 mb-3" id="booking-wallet-apply-wrap" data-balance-cents="{{ (int) $walletBalanceCents }}">
                                                <div class="form-check booking-payment-wallet-option__check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="booking-wallet-apply" value="1" checked>
                                                    <label class="form-check-label" for="booking-wallet-apply">
                                                        <span class="booking-payment-wallet-option__title d-block fw-semibold text-dark">
                                                            {{ __('booking.flow.wallet_use_title') }}
                                                        </span>
                                                        @php
                                                            $walletHint = __('booking.flow.wallet_use_hint', ['balance' => 'WALLET_BALANCE_PLACEHOLDER']);
                                                            [$walletHintBefore, $walletHintAfter] = array_pad(explode('WALLET_BALANCE_PLACEHOLDER', $walletHint, 2), 2, '');
                                                        @endphp
                                                        <span class="booking-payment-wallet-option__hint d-block small text-muted mt-1">
                                                            {!! $walletHintBefore !!}<strong id="booking-wallet-balance-display">{{ number_format($walletBalanceCents / 100, 2, ',', ' ') }} €</strong>{!! $walletHintAfter !!}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endif
                                        <div id="booking-wallet-covers-deposit-msg" class="booking-payment-credits-only rounded-3 mb-3 d-none" role="status">
                                            <div class="d-flex gap-2 align-items-start">
                                                <i class="bi bi-check-circle-fill text-success booking-payment-credits-only__icon" aria-hidden="true"></i>
                                                <div class="small">
                                                    <p class="fw-semibold text-dark mb-1">{{ __('booking.flow.wallet_covers_title') }}</p>
                                                    <p class="text-muted mb-0">
                                                        {{ __('booking.flow.wallet_covers_body') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="booking-stripe-payment-wrap" class="booking-payment-card-section">
                                            <p class="fw-semibold text-dark small mb-1">{{ __('booking.flow.payment_section_title') }}</p>
                                            <p id="booking-pay-wallet-used-line" class="small text-muted mb-1 d-none">
                                                {{ __('booking.flow.wallet_used_line') }}
                                                <strong class="text-success" id="booking-pay-wallet-used-amount">—</strong>
                                            </p>
                                            <p class="small text-muted mb-3">
                                                {{ __('booking.flow.pay_now_line') }}
                                                <strong id="booking-pay-card-amount">—</strong>
                                            </p>
                                            <div id="booking-stripe-mount" class="mb-2"></div>
                                            <p id="booking-stripe-error" class="small text-danger mb-0 d-none" role="alert"></p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="booking-category-section mb-4 pb-1" aria-label="{{ __('booking.flow.cancellation_heading') }}">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <h3 class="h6 fw-semibold text-dark mb-0 pb-3 border-bottom">{{ __('booking.flow.cancellation_heading') }}</h3>
                                        <div class="pt-3">
                                        @include('booking.partials.cancellation-policy-visual', [
                                            'bookingCancellationPreviewUrl' => $bookingCancellationPreviewUrl ?? null,
                                            'onlineBookingPaymentRequired' => $onlineBookingPaymentRequired ?? true,
                                        ])
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>

                    <div @class(['col-lg-4 booking-summary-column', 'booking-elegant-summary' => ($bookingUsesRefinedLayout ?? false)])>
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => __('booking.partials.summary_title'),
                            'showNextButton' => true,
                            'nextUrl' => '#',
                            'nextRequires' => 'checkout',
                            'nextLabel' => ($onlineBookingPaymentRequired ?? true) ? __('booking.flow.confirm_button') : __('booking.flow.book_button'),
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
