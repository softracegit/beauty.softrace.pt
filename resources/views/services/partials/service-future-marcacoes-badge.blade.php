@php
    $count = (int) ($count ?? 0);
    $serviceId = (int) ($serviceId ?? ($service->id ?? 0));
    $serviceName = (string) ($serviceName ?? ($service->name ?? ''));
    $title = 'Marcações futuras activas (ainda não terminadas / pagas / canceladas)';
@endphp
<button
    type="button"
    class="service-item-future-marcacoes-trigger"
    data-service-id="{{ $serviceId }}"
    data-service-name="{{ $serviceName }}"
    title="{{ $title }}"
    aria-label="Ver {{ $count }} marca{{ $count === 1 ? 'ção' : 'ções' }} futura{{ $count === 1 ? '' : 's' }}"
    @if($count < 1) disabled @endif
>
    <span class="badge text-bg-light">{{ $count }} marca{{ $count === 1 ? 'ção' : 'ções' }}</span>
</button>
