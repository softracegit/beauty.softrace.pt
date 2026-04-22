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
                            Foi criada a tua <strong>conta de marcação</strong>. Nas próximas vezes inicia sessão com email e password para preencheres os dados mais rapidamente.
                        </div>
                    @endif
                    <div class="card border shadow-sm rounded-3 mb-3">
                        <div class="card-body py-3">
                            <ul class="list-unstyled mb-0">
                                <li class="border-bottom pb-2 mb-2">
                                    <a href="{{ route('booking.conta.index') }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>A minha conta</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('booking.conta.index') }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>As minhas marcações</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="{{ route('booking.index') }}" class="btn btn-dark">Nova marcação</a>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
