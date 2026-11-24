{{-- Frase curta na lista CRM (serviços / extras). Variantes: só no offcanvas. --}}
@php
    $short = trim((string) ($shortDescription ?? ''));
@endphp
@if($short !== '')
    <div class="catalog-item-short">{{ $short }}</div>
@endif
