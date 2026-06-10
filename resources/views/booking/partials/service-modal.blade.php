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
                <div id="booking-modal-options-wrap" class="d-none mt-3">
                    <p class="small fw-semibold text-dark mb-2">{{ __('booking.partials.service_modal_options') }}</p>
                    <div id="booking-modal-options" class="booking-modal-options" role="radiogroup" aria-label="{{ __('booking.partials.service_modal_options_aria') }}"></div>
                    <p id="booking-modal-options-error" class="booking-modal-options-error text-danger small mt-2 mb-0 d-none" role="alert"></p>
                </div>
                <div id="booking-modal-extras-wrap" class="d-none mt-3">
                    <p class="small fw-semibold text-dark mb-2">{{ __('booking.partials.service_modal_extras') }}</p>
                    <div id="booking-modal-extras" class="booking-modal-options" role="group" aria-label="{{ __('booking.partials.service_modal_extras_aria') }}"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 booking-modal-footer">
                <div id="booking-modal-footer-add" class="booking-modal-footer__pane w-100">
                    <button type="button" class="btn btn-dark w-100" id="booking-modal-confirm">{{ __('booking.partials.service_modal_add') }}</button>
                </div>
                <div id="booking-modal-footer-edit" class="booking-modal-footer__pane booking-modal-footer__pane--edit w-100 d-none">
                    <button type="button" class="btn btn-outline-danger" id="booking-modal-remove-line">{{ __('booking.partials.service_modal_remove') }}</button>
                    <button type="button" class="btn btn-dark" id="booking-modal-apply-edit">{{ __('booking.partials.service_modal_apply') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
