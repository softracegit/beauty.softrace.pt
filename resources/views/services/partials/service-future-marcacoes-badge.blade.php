@php
    $count = (int) ($count ?? 0);
    $title = 'Marcações futuras activas (ainda não terminadas / pagas / canceladas)';
@endphp
<span class="badge text-bg-light" title="{{ $title }}">
    {{ $count }} marca{{ $count === 1 ? 'ção' : 'ções' }}
</span>
