{{-- Valor de campo tipo Material (snapshot / preferências) --}}
@php
    $m = is_array($value ?? null) ? $value : [];
    $name = trim((string) ($m['name'] ?? ''));
    $code = trim((string) ($m['code'] ?? ''));
    $brand = trim((string) ($m['brand'] ?? ''));
    $finish = trim((string) ($m['finish_label'] ?? $m['finish'] ?? ''));
    $hex = trim((string) ($m['hex'] ?? ''));
    $photo = trim((string) ($m['photo_url'] ?? ''));
    if ($photo === '' && ! empty($m['photo_path'])) {
        $photo = asset('storage/'.$m['photo_path']);
    }
    $sub = array_values(array_filter([$code !== '' ? $code : null, $brand !== '' ? $brand : null, $finish !== '' ? $finish : null]));
    $archived = ! empty($m['archived']);
    if ($photo !== '') {
        $swatchStyle = 'background-image:url('.e($photo).');background-size:cover;background-position:center;';
    } elseif ($hex !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
        $swatchStyle = 'background:'.e($hex).';';
    } else {
        $swatchStyle = 'background:transparent;border-style:dashed;';
    }
@endphp
<div class="csheet-material-value">
    <span class="csheet-material-value__swatch" style="{{ $swatchStyle }}" title="{{ $hex !== '' ? $hex : ($name !== '' ? $name : 'Material') }}" aria-hidden="true"></span>
    <span class="csheet-material-value__text">
        <span class="csheet-material-value__name">
            {{ $name !== '' ? $name : ($code !== '' ? $code : ('#'.($m['id'] ?? ''))) }}
            @if($archived)
                <span class="badge bg-secondary ms-1 align-middle">Arquivado</span>
            @endif
        </span>
        @if($sub !== [])
            <span class="csheet-material-value__sub">{{ implode(' · ', $sub) }}</span>
        @endif
    </span>
</div>
