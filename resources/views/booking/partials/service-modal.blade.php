{{-- Modal de serviço: adicionar (passo serviços) ou editar/remover (resumo em qualquer passo) --}}
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5 fw-semibold" id="bookingModalTitle"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.partials.service_modal_close') }}"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="booking-modal-service-meta" class="text-muted small mb-0"></p>
                {{-- Painel em dois momentos (tema Minimal): 1) opção e adicionais, 2) extras --}}
                <div id="booking-modal-stepper" class="booking-modal-stepper d-none" aria-hidden="true">
                    <span class="booking-modal-stepper__item" data-step="1">1 · {{ __('booking.partials.service_modal_step_option') }}</span>
                    <span class="booking-modal-stepper__item" data-step="2">2 · {{ __('booking.partials.service_modal_step_extras') }}</span>
                </div>
                {{-- Serviço sem variantes: o próprio serviço aparece como opção única, já escolhida --}}
                <div id="booking-modal-single-wrap" class="d-none mt-3">
                    <p class="small fw-semibold text-dark mb-2">{{ __('booking.partials.service_modal_options') }}</p>
                    <div id="booking-modal-single" class="booking-modal-options" role="radiogroup" aria-label="{{ __('booking.partials.service_modal_options_aria') }}"></div>
                </div>
                <div id="booking-modal-options-wrap" class="d-none mt-3">
                    <p class="small fw-semibold text-dark mb-2">{{ __('booking.partials.service_modal_options') }}</p>
                    <div id="booking-modal-options" class="booking-modal-options" role="radiogroup" aria-label="{{ __('booking.partials.service_modal_options_aria') }}"></div>
                    {{-- Variantes adicionais: somam-se à opção escolhida --}}
                    <div id="booking-modal-addons" class="booking-modal-options booking-modal-addons d-none mt-2" role="group" aria-label="{{ __('booking.partials.service_modal_addons') }}"></div>
                    <p id="booking-modal-options-error" class="booking-modal-options-error text-danger small mt-2 mb-0 d-none" role="alert"></p>
                </div>
                <div id="booking-modal-extras-wrap" class="d-none mt-3">
                    <p class="small fw-semibold text-dark mb-2 booking-modal-extras-title" data-label-question="{{ __('booking.partials.service_modal_extras_question') }}">{{ __('booking.partials.service_modal_extras') }}</p>
                    <div id="booking-modal-extras" class="booking-modal-options" role="group" aria-label="{{ __('booking.partials.service_modal_extras_aria') }}"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 booking-modal-footer">
                <button type="button" class="btn btn-outline-dark booking-modal-back d-none" id="booking-modal-back" aria-label="{{ __('booking.partials.service_modal_back') }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </button>
                <div id="booking-modal-footer-add" class="booking-modal-footer__pane w-100">
                    <button type="button" class="btn btn-dark w-100" id="booking-modal-confirm" data-label-continue="{{ __('booking.partials.service_modal_continue') }}">{{ __('booking.partials.service_modal_add') }}</button>
                </div>
                <div id="booking-modal-footer-edit" class="booking-modal-footer__pane booking-modal-footer__pane--edit w-100 d-none">
                    <button type="button" class="btn btn-outline-danger" id="booking-modal-remove-line" aria-label="{{ __('booking.partials.service_modal_remove') }}" title="{{ __('booking.partials.service_modal_remove') }}"><i class="bi bi-trash3 booking-modal-remove__icon" aria-hidden="true"></i><span class="booking-modal-remove__label">{{ __('booking.partials.service_modal_remove') }}</span></button>
                    <button type="button" class="btn btn-dark" id="booking-modal-apply-edit" data-label-continue="{{ __('booking.partials.service_modal_continue') }}">{{ __('booking.partials.service_modal_apply') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
