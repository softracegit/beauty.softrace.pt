@extends('booking.layout')

@section('title', 'Recuperar password')

@section('body_class', 'booking-page booking-page--password-reset')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 28rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3">Definir nova password</h1>
                    <p class="text-muted small mb-4">
                        Conta: <span class="fw-semibold text-dark">{{ $email }}</span>
                    </p>

                    @if (session('error'))
                        <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">{{ session('error') }}</div>
                    @endif

                    <form method="post" action="{{ route('booking.password.reset.perform') }}" class="card border shadow-sm rounded-3">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="booking-reset-password" class="form-label small text-muted mb-1">Nova password</label>
                                <input id="booking-reset-password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="booking-reset-password-confirmation" class="form-label small text-muted mb-1">Confirmar nova password</label>
                                <input id="booking-reset-password-confirmation" name="password_confirmation" type="password" class="form-control @error('password_confirmation') is-invalid @enderror" required autocomplete="new-password">
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <ul class="small text-muted ps-3 mb-3" id="booking-reset-password-rules">
                                <li id="booking-reset-pass-rule-len">Mínimo de 8 caracteres</li>
                                <li id="booking-reset-pass-rule-num">Pelo menos 1 número</li>
                            </ul>
                            <button type="submit" class="btn btn-dark w-100">Guardar nova password</button>
                        </div>
                    </form>

                    <p class="small text-muted mt-4 mb-0">
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none js-booking-open-auth-modal">← Voltar ao login</button>
                    </p>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    <script>
        (function () {
            function initResetPasswordRules() {
                var passInput = document.getElementById('booking-reset-password');
                var ruleLen = document.getElementById('booking-reset-pass-rule-len');
                var ruleNum = document.getElementById('booking-reset-pass-rule-num');
                if (!passInput || !ruleLen || !ruleNum) {
                    return;
                }

                function syncRules() {
                    var value = passInput.value || '';
                    var hasLen = value.length >= 8;
                    var hasNum = /\d/.test(value);

                    ruleLen.classList.toggle('text-success', hasLen);
                    ruleLen.classList.toggle('text-muted', !hasLen);
                    ruleNum.classList.toggle('text-success', hasNum);
                    ruleNum.classList.toggle('text-muted', !hasNum);
                }

                passInput.addEventListener('input', syncRules);
                syncRules();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initResetPasswordRules);
            } else {
                initResetPasswordRules();
            }
        })();
    </script>
@endpush
