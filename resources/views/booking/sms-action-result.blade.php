@extends('booking.layout')

@section('title', $title)

@section('body_class', 'booking-page booking-page--confirm')

@section('content')
    @php
        $isCancel = ($resultType ?? '') === 'cancel';
        $isError = ! $ok;
        $iconWrapClass = $isError
            ? 'bg-danger bg-opacity-10 text-danger'
            : ($isCancel ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success');
        $iconClass = $isError
            ? 'bi bi-exclamation-triangle fs-4'
            : ($isCancel ? 'bi bi-x-lg fs-4' : 'bi bi-check-lg fs-4');
    @endphp

    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar', ['bookingDisableAuthModal' => true])

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 32rem;">
                    <div class="text-center mb-4">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $iconWrapClass }} mb-3" style="width: 3.5rem; height: 3.5rem;" aria-hidden="true">
                            <i class="{{ $iconClass }}"></i>
                        </span>
                        <h1 class="booking-services-heading h5 fw-semibold text-dark mb-2">{{ $title }}</h1>
                        <p class="text-muted small mb-0">
                            {{ $message }}
                        </p>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
