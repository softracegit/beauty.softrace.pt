{{-- Observações no checkout (partilhado pelos 3 modos do formulário) --}}
@php
    $notesPlaceholder = $notesPlaceholder ?? __('booking.flow.notes_placeholder');
@endphp
<div class="mb-0">
    <label for="booking-contact-notes" class="form-label small text-muted mb-1">{{ __('booking.flow.notes_label') }}</label>
    <textarea id="booking-contact-notes" name="notes" class="form-control" rows="2" placeholder="{{ $notesPlaceholder }}"></textarea>
</div>
