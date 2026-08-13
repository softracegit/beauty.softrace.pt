@php
  $provider = $method['provider'] ?? 'manual';
  $glyphClass = match ($provider) {
    'stripe' => 'pay-method__glyph--stripe',
    'wallet' => 'pay-method__glyph--wallet',
    default => 'pay-method__glyph--manual',
  };
  $dim = $provider === 'stripe' && ! $stripeReady;
@endphp
<article class="pay-method {{ $dim ? 'pay-method--dim' : '' }}">
  <input type="hidden" name="methods[{{ $index }}][code]" value="{{ $method['code'] }}">
  <input type="hidden" name="methods[{{ $index }}][sort]" value="{{ $method['sort'] }}">

  <div class="pay-method__main">
    <span class="pay-method__glyph {{ $glyphClass }}" aria-hidden="true">
      <i class="ph {{ $method['icon'] }}"></i>
    </span>
    <div class="min-w-0">
      <p class="pay-method__name">{{ $method['label'] }}</p>
      <p class="pay-method__desc">{{ $method['description'] }}</p>
    </div>
  </div>

  <div class="pay-channels" role="group" aria-label="Canais para {{ $method['label'] }}">
    @foreach($channelMeta as $channel => $meta)
      <label class="pay-channel" for="method_{{ $method['code'] }}_{{ $channel }}">
        <span class="pay-channel__label">{{ $meta['label'] }}</span>
        <input type="hidden" name="methods[{{ $index }}][{{ $channel }}]" value="0">
        <div class="form-check form-switch m-0">
          <input
            class="form-check-input"
            type="checkbox"
            name="methods[{{ $index }}][{{ $channel }}]"
            value="1"
            id="method_{{ $method['code'] }}_{{ $channel }}"
            @checked($method[$channel] ?? false)
            @if($dim)
              title="Ative o Stripe e configure as chaves para usar este método"
            @endif
          >
        </div>
      </label>
    @endforeach
  </div>
</article>
