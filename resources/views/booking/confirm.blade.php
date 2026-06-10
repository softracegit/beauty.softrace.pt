@extends('booking.layout')

@section('title', __('booking.flow.confirm_page_title'))

@section('body_class', 'booking-page booking-page--confirm')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 32rem;">
                    <div class="text-center mb-4">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 3.5rem; height: 3.5rem;" aria-hidden="true">
                            <i class="bi bi-check-lg fs-4"></i>
                        </span>
                        <h1 class="booking-services-heading h5 fw-semibold text-dark mb-2">{{ __('booking.flow.confirm_heading') }}</h1>
                        <p class="text-muted small mb-0">
                            {{ __('booking.flow.confirm_body', ['business' => $businessName]) }}
                        </p>
                    </div>
                    @if (! empty($primeiraMarcacao))
                        <div class="alert alert-info small py-2 px-3 mb-4 text-start" role="status">
                            {{ __('booking.flow.confirm_first_booking_alert') }}
                        </div>
                    @endif
                    <div class="card border shadow-sm rounded-3 mb-3">
                        <div class="card-body py-3">
                            <ul class="list-unstyled mb-0">
                                <li class="border-bottom pb-2 mb-2">
                                    <a href="{{ route('booking.conta.index', ['store' => $bookingStoreSlug], false) }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>{{ __('booking.flow.confirm_my_account') }}</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('booking.conta.marcacoes', ['store' => $bookingStoreSlug], false) }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>{{ __('booking.flow.confirm_my_appointments') }}</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="{{ route('booking.index', ['store' => $bookingStoreSlug], false) }}" class="btn btn-dark">{{ __('booking.flow.confirm_new_booking') }}</a>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
