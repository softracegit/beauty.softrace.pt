@php
    $hasClient = ($bookingClient ?? null) !== null;
    $profileEmail = trim((string) (($bookingClient ?? null)?->email ?? ''));
    $profileNifDigits = preg_replace('/\D/', '', (string) (($bookingClient ?? null)?->nif ?? ''));
    $showSupplementEmailInput = $hasClient && $profileEmail === '';
@endphp
<div class="booking-invoice-options">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="booking-send-invoice-email" value="1">
        <label class="form-check-label small" for="booking-send-invoice-email">{{ __('booking.partials.invoice_send_email') }}</label>
    </div>
    <div id="booking-invoice-email-row" class="mb-3">
        <div id="booking-invoice-email-phrase">
            @if($showSupplementEmailInput)
                <p class="small text-muted mb-0">
                    {{ __('booking.partials.invoice_will_send_to') }}
                    <span id="booking-invoice-email-live" class="text-dark fw-medium">—</span>.
                </p>
            @elseif($hasClient && $profileEmail !== '')
                <p class="small text-muted mb-0">
                    {{ __('booking.partials.invoice_will_send_to') }}
                    <span class="text-dark fw-medium">{{ e($profileEmail) }}</span>.
                </p>
            @else
                {{-- Convidado: email vem do formulário principal --}}
                <p class="small text-muted mb-0">
                    {{ __('booking.partials.invoice_will_send_to') }}
                    <span id="booking-invoice-email-live" class="text-dark fw-medium">—</span>.
                </p>
            @endif
        </div>
        @if($showSupplementEmailInput)
            <label for="booking-invoice-supplement-email" class="form-label small text-muted mb-1">{{ __('booking.flow.email_label') }}</label>
            <input type="email"
                class="form-control form-control-sm"
                id="booking-invoice-supplement-email"
                name="invoice_email"
                autocomplete="email"
                placeholder="{{ __('booking.partials.invoice_email_placeholder') }}"
                value="{{ old('invoice_email', '') }}">
            <p class="small text-muted mb-0 mt-1">{{ __('booking.partials.invoice_supplement_email_hint') }}</p>
        @endif
    </div>

    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="booking-want-invoice-nif" value="1">
        <label class="form-check-label small" for="booking-want-invoice-nif">{{ __('booking.partials.invoice_want_nif') }}</label>
    </div>
    <div id="booking-nif-input-wrap" class="d-none">
        <input type="text"
            class="form-control form-control-sm booking-invoice-nif-input"
            id="booking-invoice-nif"
            name="billing_nif"
            inputmode="numeric"
            maxlength="32"
            autocomplete="off"
            placeholder="{{ __('booking.partials.invoice_nif_placeholder') }}"
            value="{{ old('billing_nif', $profileNifDigits) }}"
            aria-label="{{ __('booking.partials.invoice_nif_aria') }}">
    </div>
</div>
