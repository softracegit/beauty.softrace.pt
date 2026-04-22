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
                <p id="booking-auth-modal-subtitle" class="text-muted small mb-3">Entre ou registe-se para concluir a sua marcação.</p>
                <div id="booking-auth-modal-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>

                <div id="booking-auth-step-email">
                    <div class="mb-3">
                        <label for="booking-auth-email" class="form-label small text-muted mb-1">Email</label>
                        <input id="booking-auth-email" type="email" class="form-control" autocomplete="email" required>
                    </div>
                    <button type="button" id="booking-auth-email-next" class="btn btn-dark w-100">Seguinte</button>
                </div>

                <div id="booking-auth-step-login" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-login-password" class="form-label small text-muted mb-1">Password</label>
                        <div class="input-group">
                            <input id="booking-auth-login-password" type="password" class="form-control" autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" id="booking-auth-toggle-login-password" aria-label="Mostrar password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="booking-auth-login-password-error" class="invalid-feedback d-block d-none"></div>
                    </div>
                    <button type="button" id="booking-auth-forgot" class="btn btn-link btn-sm px-0 text-decoration-none mb-2">Esqueceu a password?</button>
                    <button type="button" id="booking-auth-login-submit" class="btn btn-dark w-100">Entrar</button>
                    <p id="booking-auth-forgot-msg" class="small text-muted d-none mb-0">Enviaremos um link seguro para este email para criar uma senha.</p>
                </div>

                <div id="booking-auth-step-forgot" class="d-none">
                    <div id="booking-auth-forgot-form" class="mb-3">
                        <label for="booking-auth-forgot-email" class="form-label small text-muted mb-1">Email</label>
                        <input id="booking-auth-forgot-email" type="email" class="form-control" autocomplete="email">
                    </div>
                    <button type="button" id="booking-auth-forgot-submit" class="btn btn-dark w-100 mb-2">Recuperar</button>
                    <div id="booking-auth-forgot-success" class="d-none">
                        <h3 class="h6 fw-semibold text-dark mb-2">Verifique seu e-mail</h3>
                        <p id="booking-auth-forgot-success-text" class="small text-muted mb-3"></p>
                        <button type="button" id="booking-auth-forgot-back-login" class="btn btn-outline-secondary w-100">Voltar ao login</button>
                    </div>
                </div>

                <div id="booking-auth-step-register" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-register-name" class="form-label small text-muted mb-1">Nome</label>
                        <input id="booking-auth-register-name" type="text" class="form-control" autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label for="booking-auth-register-phone" class="form-label small text-muted d-block mb-1">Telemóvel</label>
                        <input id="booking-auth-register-phone" type="tel" class="form-control" autocomplete="tel">
                        <input id="booking-auth-register-phone-e164" type="hidden">
                    </div>
                    <div class="mb-2">
                        <label for="booking-auth-register-password" class="form-label small text-muted mb-1">Password</label>
                        <div class="input-group">
                            <input id="booking-auth-register-password" type="password" class="form-control" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="booking-auth-toggle-register-password" aria-label="Mostrar password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <ul class="small text-muted ps-3 mb-3 booking-auth-password-rules">
                        <li id="booking-auth-pass-rule-len">Mínimo de 8 caracteres</li>
                        <li id="booking-auth-pass-rule-num">Pelo menos 1 número</li>
                    </ul>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="booking-auth-privacy">
                        <label class="form-check-label small" for="booking-auth-privacy">Eu concordo com a política de privacidade</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="booking-auth-terms">
                        <label class="form-check-label small" for="booking-auth-terms">Eu aceito os termos e condições</label>
                    </div>
                    <button type="button" id="booking-auth-register-submit" class="btn btn-dark w-100">Criar conta e continuar</button>
                </div>
            </div>
        </div>
    </div>
</div>
