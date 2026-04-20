@extends('booking.layout')

@section('title', 'Iniciar sessão')

@section('body_class', 'booking-page booking-page--login')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 26rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3">Iniciar sessão</h1>
                    <p class="text-muted small mb-4">
                        Usa o email e a password da tua conta de marcação online.
                    </p>

                    @if (session('error'))
                        <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">{{ session('error') }}</div>
                    @endif

                    <form method="post" action="{{ route('booking.login.perform') }}" class="card border shadow-sm rounded-3">
                        @csrf
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="booking-login-email" class="form-label small text-muted mb-1">Email</label>
                                <input id="booking-login-email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" required autocomplete="email" autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="booking-login-password" class="form-label small text-muted mb-1">Password</label>
                                <input id="booking-login-password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remember" id="booking-login-remember" value="1" @checked(old('remember'))>
                                <label class="form-check-label small" for="booking-login-remember">Manter sessão neste dispositivo</label>
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Entrar</button>
                        </div>
                    </form>

                    <p class="small text-muted mt-4 mb-2">
                        Preferes receber um link por email?
                        <a href="{{ route('booking.acesso') }}" class="text-decoration-none fw-semibold">Acesso por link</a>
                    </p>
                    <p class="small text-muted mb-0">
                        <a href="{{ route('booking.index') }}" class="text-decoration-none">← Voltar à marcação</a>
                    </p>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
