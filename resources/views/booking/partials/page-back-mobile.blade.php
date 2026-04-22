@php
    $backUrl = $backUrl ?? route('booking.index');
@endphp
<a
    href="{{ $backUrl }}"
    class="btn btn-outline-secondary d-inline-flex d-lg-none justify-content-center align-items-center booking-summary-back-btn booking-page-back-mobile flex-shrink-0"
    aria-label="Voltar"
>
    <i class="bi bi-chevron-left" aria-hidden="true"></i>
</a>
