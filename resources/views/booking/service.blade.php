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
                        <p class="fw-semibold mb-2">Data e hora</p>
                        <p class="text-muted small mb-0">O calendário e a escolha de técnico serão configurados no próximo passo.</p>
                    </div>
                </div>
            </div>
        </main>
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
