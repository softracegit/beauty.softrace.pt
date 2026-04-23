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
                <p id="booking-auth-modal-subtitle" class="text-muted small mb-3">Recebe um código por email para entrar sem password.</p>
                <div id="booking-auth-modal-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>

                <div id="booking-auth-step-email">
                    <div class="mb-3">
                        <label for="booking-auth-email" class="form-label small text-muted mb-1">Email</label>
                        <input id="booking-auth-email" type="email" class="form-control" autocomplete="email" required>
                    </div>
                    <button type="button" id="booking-auth-email-next" class="btn btn-dark w-100">Enviar código</button>
                </div>

                <div id="booking-auth-step-code" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-code" class="form-label small text-muted mb-1">Código de acesso</label>
                        <input id="booking-auth-code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" placeholder="000000">
                        <div class="form-text">Introduza o código de 6 dígitos enviado para o seu email.</div>
                    </div>
                    <button type="button" id="booking-auth-code-submit" class="btn btn-dark w-100 mb-2">Entrar</button>
                    <button type="button" id="booking-auth-code-resend" class="btn btn-outline-secondary w-100">Reenviar código</button>
                    <div id="booking-auth-code-status" class="small text-muted mt-2 d-none"></div>
                </div>

                <div id="booking-auth-step-register" class="d-none">
                    <div class="alert alert-light border small mb-0">
                        Conta criada sem password. Pode concluir os dados no checkout.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
