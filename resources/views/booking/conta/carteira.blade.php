@extends('booking.layout')

@section('title', __('booking.account.wallet_page_title'))

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div @class(['container booking-container-wide px-3 pb-4 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
                <main @class(['pt-1 booking-account-layout', 'booking-elegant-account-layout' => ($bookingUsesRefinedLayout ?? false)])>
                    @if ($errors->any())
                        <div class="alert alert-danger small mb-3">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    @if (session('success'))
                        <div class="alert alert-success small mb-3">
                            {{ session('success') }}
                        </div>
                    @endif

                    @include('booking.conta.partials.sidebar', ['accountNavActive' => 'carteira'])

                    <div class="booking-account-content">
                        @include('booking.partials.elegant-account-header', [
                            'elegantAccountEyebrow' => __('booking.elegant.account_wallet_eyebrow'),
                            'elegantAccountTitle' => __('booking.nav.wallet'),
                            'elegantAccountSubtitle' => __('booking.elegant.account_wallet_subtitle'),
                        ])
                        @include('booking.conta.partials.carteira', [
                            'balanceCents' => $balanceCents,
                            'transactions' => $transactions,
                            'businessName' => $businessName,
                        ])
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
