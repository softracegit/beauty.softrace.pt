@extends('booking.layout')

@section('title', $service->name)

@section('body_class', 'booking-page booking-page--step')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <main class="container booking-container-wide pb-4 flex-grow-1 booking-main-body px-3">
            <div class="mx-auto" style="max-width: 32rem;">
                <h1 class="h2 fw-semibold mb-2">{{ $service->name }}</h1>
                <p class="text-muted mb-4">
                    {{ $service->formatted_duration }}
                    ·
                    @php
                        $p = $service->online_price ?? $service->price;
                    @endphp
                    {{ number_format((float) $p, 2, ',', '.') }}&nbsp;€
                </p>
                <div class="card border shadow-sm rounded-3">
                    <div class="card-body">
                        <p class="fw-semibold mb-2">{{ __('booking.flow.service_datetime_heading') }}</p>
                        <p class="text-muted small mb-0">{{ __('booking.flow.service_placeholder') }}</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
