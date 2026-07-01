@php
    /** @var array<int, array{id: string, question: string, answer: string}> $items */
    $accordionId = $accordionId ?? 'ajudaFaqAccordion';
@endphp

<div class="accordion" id="{{ $accordionId }}">
    @foreach ($items as $index => $item)
        @php
            $headingId = $accordionId . '-heading-' . $index;
            $collapseId = $accordionId . '-collapse-' . $index;
        @endphp
        <div class="accordion-item">
            <h3 class="accordion-header" id="{{ $headingId }}">
                <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $collapseId }}"
                        aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                        aria-controls="{{ $collapseId }}">
                    {{ $item['question'] }}
                </button>
            </h3>
            <div id="{{ $collapseId }}"
                 class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                 aria-labelledby="{{ $headingId }}"
                 data-bs-parent="#{{ $accordionId }}">
                <div class="accordion-body text-muted">
                    {{ $item['answer'] }}
                </div>
            </div>
        </div>
    @endforeach
</div>
