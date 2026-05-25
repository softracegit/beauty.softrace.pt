@php
    $previewUrl = $bookingCancellationPreviewUrl
        ?? route('booking.cancellation.preview', ['store' => $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug()]);
@endphp

<div
    id="booking-cancellation-policy"
    class="booking-cancel-policy"
    data-preview-url="{{ $previewUrl }}"
    aria-live="polite"
>
    <div id="booking-cancel-policy-empty" class="booking-cancel-policy__empty small text-muted">
        Seleciona data e hora da marcação para ver até quando podes cancelar sem perder o pré-pagamento.
    </div>

    <div id="booking-cancel-policy-timeline" class="booking-cancel-policy__timeline d-none">
        <div
            id="booking-cancel-policy-warning"
            class="booking-cancel-policy__warning alert alert-warning py-2 px-3 small mb-3 d-none"
            role="status"
        ></div>

        <div class="booking-cancel-policy__track-wrap">
            <div class="booking-cancel-policy__track" aria-hidden="true">
                <div id="booking-cancel-policy-pin" class="booking-cancel-policy__deadline-point" style="left: 62%;">
                    <div class="booking-cancel-policy__badge-stack">
                        <span id="booking-cancel-policy-badge" class="booking-cancel-policy__badge"></span>
                        <span id="booking-cancel-policy-badge-limit" class="booking-cancel-policy__badge-limit d-none"></span>
                    </div>
                    <span class="booking-cancel-policy__pin">
                        <i class="bi bi-x-lg"></i>
                    </span>
                </div>
            </div>
            <div class="booking-cancel-policy__axis">
                <span id="booking-cancel-policy-now-label" class="booking-cancel-policy__axis-label">Hoje</span>
                <span id="booking-cancel-policy-appt-label" class="booking-cancel-policy__axis-label booking-cancel-policy__axis-end"></span>
            </div>
        </div>
        <p id="booking-cancel-policy-description" class="booking-cancel-policy__description small text-muted mb-0"></p>
    </div>
</div>
