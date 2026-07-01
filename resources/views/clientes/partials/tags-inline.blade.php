@php
    $pickerTags = ($tags ?? $client->tags ?? collect())->map(fn ($t) => $t->toPickerArray())->values()->all();
    $isReadonly = !empty($readonly);
    $syncRoute = $syncUrl ?? route('clientes.tags.sync', $client);
    $variant = $variant ?? '';
@endphp
<div
    class="client-tags-inline{{ $variant ? ' client-tags-inline--' . $variant : '' }}"
    data-client-id="{{ $client->id }}"
    data-sync-url="{{ $syncRoute }}"
    data-readonly="{{ $isReadonly ? '1' : '0' }}"
    data-variant="{{ $variant }}"
    data-tags='@json($pickerTags)'
></div>
