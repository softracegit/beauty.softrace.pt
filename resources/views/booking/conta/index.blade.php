@extends('booking.layout')

@section('title', 'A minha conta')

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 28rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-2">A minha conta</h1>
                    <p class="text-muted small mb-4">
                        Sessão iniciada como <span class="fw-semibold text-dark">{{ $user->email }}</span>
                    </p>

                    <div class="card border shadow-sm rounded-3 mb-3">
                        <div class="card-body py-3">
                            <p class="small fw-semibold text-uppercase text-muted mb-2">Conta</p>
                            <ul class="list-unstyled mb-0">
                                <li>
                                    <a href="{{ route('booking.index') }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>Nova marcação</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <form method="post" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Terminar sessão</button>
                    </form>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
