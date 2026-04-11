@extends('booking.layout')

@section('title', 'Acesso à conta')

@section('body_class', 'booking-page booking-page--acesso')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 26rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3">Acesso por email</h1>
                    <p class="text-muted small mb-4">
                        Indica o email da tua conta de marcação. Se existir, enviamos um link para iniciares sessão (válido por cerca de {{ (int) config('booking.magic_link_ttl_minutes', 60) }} minutos).
                    </p>

                    @if (session('status'))
                        <div class="alert alert-success py-2 px-3 small mb-3" role="status">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">{{ session('error') }}</div>
                    @endif

                    <form method="post" action="{{ route('booking.acesso.link') }}" class="card border shadow-sm rounded-3">
                        @csrf
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="booking-acesso-email" class="form-label small text-muted mb-1">Email</label>
                                <input id="booking-acesso-email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" required autocomplete="email">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Enviar link</button>
                        </div>
                    </form>

                    <p class="small text-muted mt-4 mb-0">
                        <a href="{{ route('booking.index') }}" class="text-decoration-none">← Voltar à marcação</a>
                    </p>
                </main>
            </div>
        </div>
    </div>
@endsection
