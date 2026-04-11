@extends('booking.layout')

@section('title', 'Definir password')

@section('body_class', 'booking-page booking-page--password')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 26rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3">
                        {{ $mustSetPassword ? 'Definir password' : 'Alterar password' }}
                    </h1>
                    <p class="text-muted small mb-4">
                        @if ($mustSetPassword)
                            Opcional: após definires password, podes iniciar sessão em <a href="{{ route('login') }}">login da equipa</a> com email e password (o mesmo email da marcação).
                        @else
                            Introduz a password atual e a nova password.
                        @endif
                    </p>

                    @if (session('status'))
                        <div class="alert alert-success py-2 px-3 small mb-3" role="status">{{ session('status') }}</div>
                    @endif

                    <form method="post" action="{{ route('booking.conta.password.update') }}" class="card border shadow-sm rounded-3">
                        @csrf
                        <div class="card-body">
                            @if (! $mustSetPassword)
                                <div class="mb-3">
                                    <label for="current_password" class="form-label small text-muted mb-1">Password atual</label>
                                    <input id="current_password" name="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="password" class="form-label small text-muted mb-1">Nova password</label>
                                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label small text-muted mb-1">Confirmar</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Guardar</button>
                        </div>
                    </form>

                    <p class="small text-muted mt-4 mb-0">
                        <a href="{{ route('booking.step3') }}" class="text-decoration-none">← Voltar ao checkout</a>
                    </p>
                </main>
            </div>
        </div>
    </div>
@endsection
