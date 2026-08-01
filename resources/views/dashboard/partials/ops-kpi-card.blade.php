@php
    $href = $href ?? null;
    $iconClass = $iconClass ?? 'primary';
    $icon = $icon ?? 'ph-duotone ph-calendar-dot';
    $value = $value ?? 0;
    $label = $label ?? '';
    $ariaLabel = $ariaLabel ?? $label;
@endphp
@if($href)
    <a href="{{ $href }}" class="dash-kpi dash-kpi--link" aria-label="{{ $ariaLabel }}">
        <div class="dash-kpi-icon {{ $iconClass }}">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{!! $valueHtml ?? e($value) !!}</div>
            <div class="dash-kpi-label">{{ $label }}</div>
        </div>
    </a>
@else
    <div class="dash-kpi">
        <div class="dash-kpi-icon {{ $iconClass }}">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{!! $valueHtml ?? e($value) !!}</div>
            <div class="dash-kpi-label">{{ $label }}</div>
        </div>
    </div>
@endif
