@extends('booking.layout')

@section('title', 'Marcação confirmada')

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
                        <h1 class="booking-services-heading h5 fw-semibold text-dark mb-2">Marcação confirmada</h1>
                        <p class="text-muted small mb-0">
                            Recebemos o teu pedido. A equipa da <span class="fw-semibold text-dark">{{ $businessName }}</span> pode contactar-te se for necessário.
                        </p>
                    </div>
                    @if (! empty($primeiraMarcacao))
                        <div class="alert alert-info small py-2 px-3 mb-4 text-start" role="status">
                            Foi criada a tua <strong>conta de marcação</strong>. Verifica o email — enviámos um <strong>link para iniciares sessão</strong>. Nas próximas vezes os teus dados aparecem automaticamente no checkout.
                            <div class="mt-2 mb-0">
                                <a href="{{ route('booking.acesso') }}" class="alert-link fw-semibold">Não recebeste? Pedir novo link</a>
                            </div>
                        </div>
                    @endif
                    <div class="d-grid gap-2">
                        <a href="{{ route('booking.index') }}" class="btn btn-dark">Nova marcação</a>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
