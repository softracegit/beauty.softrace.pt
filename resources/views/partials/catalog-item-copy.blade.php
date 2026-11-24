{{-- Frase curta na lista CRM (serviços / extras / variantes). --}}
@php
    $short = trim((string) ($shortDescription ?? ''));
@endphp
@if($short !== '')
    <div class="catalog-item-short">{{ $short }}</div>
@endif
