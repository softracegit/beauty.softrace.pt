<div
    class="modal fade"
    id="booking-slot-hold-expired-modal"
    tabindex="-1"
    aria-labelledby="booking-slot-hold-expired-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-1">
                <h2 class="h5 mb-0" id="booking-slot-hold-expired-title">{{ __('booking.slot_hold.modal_title') }}</h2>
            </div>
            <div class="modal-body pt-1">
                <p class="text-muted small mb-0">
                    {{ __('booking.slot_hold.modal_body') }}<br />
                    {{ __('booking.slot_hold.modal_body_continue') }}
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <p id="booking-slot-hold-feedback" class="small text-danger mb-2 w-100 d-none"></p>
                <button type="button" class="btn btn-outline-secondary" id="booking-slot-hold-restart">
                    {{ __('booking.slot_hold.restart') }}
                </button>
                <button type="button" class="btn btn-dark" id="booking-slot-hold-extend">
                    {{ __('booking.slot_hold.extend') }}
                </button>
            </div>
        </div>
    </div>
</div>
