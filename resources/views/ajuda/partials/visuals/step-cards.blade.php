@php
    /** @var array<int, array{title: string, text: string, icon: string}> $steps */
@endphp

<figure class="ajuda-figure">
    <div class="ajuda-visual-steps">
        @foreach ($steps as $index => $step)
            <div class="ajuda-visual-step">
                <div class="ajuda-visual-step__head">
                    <span class="ajuda-visual-step__num">{{ $index + 1 }}</span>
                    <span class="ajuda-visual-step__icon" aria-hidden="true">
                        <i class="{{ $step['icon'] }}"></i>
                    </span>
                </div>
                <h3 class="ajuda-visual-step__title">{{ $step['title'] }}</h3>
                <p class="ajuda-visual-step__text">{{ $step['text'] }}</p>
            </div>
        @endforeach
    </div>
    @if (!empty($caption))
        <figcaption class="ajuda-figure__caption">{{ $caption }}</figcaption>
    @endif
</figure>
