<div class="modal fade booking-auth-modal" id="booking-auth-modal" tabindex="-1" aria-labelledby="booking-auth-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" id="booking-auth-modal-back" class="booking-auth-modal__back d-none" aria-label="{{ __('booking.auth.back_aria') }}">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <h2 id="booking-auth-modal-title" class="h5 mb-0">{{ __('booking.auth.title_login_register') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.auth.close_aria') }}"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="booking-auth-modal-subtitle" class="text-muted small mb-3">{{ __('booking.auth.subtitle_otp') }}</p>
                <div id="booking-auth-modal-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>

                <div id="booking-auth-step-ident">
                    <div class="booking-auth-channel-tabs" role="tablist" aria-label="{{ __('booking.auth.channel_tabs_aria') }}">
                        <button
                            type="button"
                            class="booking-auth-channel-tab is-active"
                            id="booking-auth-tab-phone"
                            role="tab"
                            aria-selected="true"
                            aria-controls="booking-auth-panel-phone"
                            data-auth-channel="phone"
                        >{{ __('booking.auth.channel_phone') }}</button>
                        <button
                            type="button"
                            class="booking-auth-channel-tab"
                            id="booking-auth-tab-email"
                            role="tab"
                            aria-selected="false"
                            aria-controls="booking-auth-panel-email"
                            data-auth-channel="email"
                        >{{ __('booking.auth.channel_email') }}</button>
                    </div>

                    <div id="booking-auth-panel-phone" role="tabpanel" aria-labelledby="booking-auth-tab-phone">
                        <div class="mb-3">
                            <label for="booking-auth-login-phone" class="form-label small text-muted mb-1">{{ __('booking.auth.phone_label') }}</label>
                            <input id="booking-auth-login-phone" type="tel" class="form-control" autocomplete="tel">
                        </div>
                    </div>

                    <div id="booking-auth-panel-email" class="d-none" role="tabpanel" aria-labelledby="booking-auth-tab-email">
                        <div class="mb-3">
                            <label for="booking-auth-login-email" class="form-label small text-muted mb-1">{{ __('booking.auth.email_label') }}</label>
                            <input id="booking-auth-login-email" type="email" class="form-control" autocomplete="email" inputmode="email">
                        </div>
                    </div>

                    <button type="button" id="booking-auth-email-next" class="btn btn-dark w-100 booking-auth-ident-submit">{{ __('booking.auth.receive_code') }}</button>
                </div>

                <div id="booking-auth-step-code" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-code" class="form-label small text-muted mb-1">{{ __('booking.auth.code_label') }}</label>
                        <input id="booking-auth-code" type="hidden" autocomplete="one-time-code">
                        <div class="booking-auth-otp-inputs" aria-label="{{ __('booking.auth.code_digits_aria') }}">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="0" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="1" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="2" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="3" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="4" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-auth-code-digit" data-idx="5" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done">
                        </div>
                    </div>
                    <button type="button" id="booking-auth-code-submit" class="btn btn-dark w-100 mb-2">{{ __('booking.auth.enter') }}</button>
                    <button type="button" id="booking-auth-code-resend" class="btn btn-outline-secondary w-100">{{ __('booking.auth.resend_code') }}</button>
                    <div id="booking-auth-code-status" class="small text-muted mt-2 d-none"></div>
                </div>

                <div id="booking-auth-step-register" class="d-none">
                    <div class="mb-3">
                        <label for="booking-auth-register-name" class="form-label small text-muted mb-1">{{ __('booking.auth.name_label') }}</label>
                        <input id="booking-auth-register-name" type="text" class="form-control" autocomplete="name">
                    </div>
                    <div class="mb-3 d-none" id="booking-auth-register-email-wrap">
                        <label for="booking-auth-register-email" class="form-label small text-muted mb-1">{{ __('booking.flow.email_label') }}</label>
                        <input id="booking-auth-register-email" type="email" class="form-control" autocomplete="email">
                    </div>
                    <div class="mb-3 d-none" id="booking-auth-register-phone-wrap">
                        <label for="booking-auth-register-phone" class="form-label small text-muted mb-1">{{ __('booking.flow.phone_label') }}</label>
                        <input id="booking-auth-register-phone" type="tel" class="form-control" autocomplete="tel">
                    </div>
                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="booking-auth-register-terms"
                            value="1"
                        >
                        <label class="form-check-label small text-muted" for="booking-auth-register-terms">
                            {{ __('booking.auth.terms_prefix') }}
                            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('booking.auth.terms_link') }}</a>
                            {{ __('booking.auth.terms_and') }}
                            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('booking.auth.privacy_link') }}</a>.
                        </label>
                    </div>
                    <button type="button" id="booking-auth-register-submit" class="btn btn-dark w-100" disabled>{{ __('booking.auth.create_account') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
