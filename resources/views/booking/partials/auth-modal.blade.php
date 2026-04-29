<div class="modal fade booking-auth-modal" id="booking-auth-modal" tabindex="-1" aria-labelledby="booking-auth-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" id="booking-auth-modal-back" class="booking-auth-modal__back d-none" aria-label="Voltar">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <h2 id="booking-auth-modal-title" class="h5 mb-0">Iniciar sessão ou registar-se</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="booking-auth-modal-subtitle" class="text-muted small mb-3">Recebe um código por email ou SMS para entrar sem password.</p>
                <div id="booking-auth-modal-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>

                <div id="booking-auth-step-email">
                    <div class="mb-3">
                        <label for="booking-auth-login" class="form-label small text-muted mb-1">Email ou Telemóvel</label>
                        <input id="booking-auth-login" type="text" class="form-control" autocomplete="email tel">
                    </div>
                    <button type="button" id="booking-auth-email-next" class="btn btn-dark w-100">Enviar código</button>
                </div>

                <div id="booking-auth-step-code" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-code" class="form-label small text-muted mb-1">Código de acesso</label>
                        <input id="booking-auth-code" type="hidden" autocomplete="one-time-code">
                        <div class="booking-auth-otp-inputs" aria-label="Código de 6 dígitos">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="0" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="1" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="2" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="3" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="4" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="5" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done">
                        </div>
                    </div>
                    <button type="button" id="booking-auth-code-submit" class="btn btn-dark w-100 mb-2">Entrar</button>
                    <button type="button" id="booking-auth-code-resend" class="btn btn-outline-secondary w-100">Reenviar código</button>
                    <div id="booking-auth-code-status" class="small text-muted mt-2 d-none"></div>
                </div>

                <div id="booking-auth-step-register" class="d-none">
                    <div class="alert alert-light border small mb-3">
                        Quase pronto. Complete os dados para criar a conta.
                    </div>
                    <div class="mb-3">
                        <label for="booking-auth-register-name" class="form-label small text-muted mb-1">Nome</label>
                        <input id="booking-auth-register-name" type="text" class="form-control" autocomplete="name">
                    </div>
                    <div class="mb-3 d-none" id="booking-auth-register-email-wrap">
                        <label for="booking-auth-register-email" class="form-label small text-muted mb-1">Email</label>
                        <input id="booking-auth-register-email" type="email" class="form-control" autocomplete="email">
                    </div>
                    <div class="mb-3 d-none" id="booking-auth-register-phone-wrap">
                        <label for="booking-auth-register-phone" class="form-label small text-muted mb-1">Telemóvel</label>
                        <input id="booking-auth-register-phone" type="tel" class="form-control" autocomplete="tel">
                    </div>
                    <button type="button" id="booking-auth-register-submit" class="btn btn-dark w-100">Criar conta</button>
                </div>
            </div>
        </div>
    </div>
</div>
