@php
    $notes = $notes ?? collect();
    
    // Função auxiliar para converter text-* para bg-*
    $getBgColor = function($textColor) {
        return str_replace('text-', 'bg-', $textColor);
    };
@endphp

@if($notes->count() > 0)
<div class="timeline2 icon-timeline">
    <ul>
        @foreach($notes as $note)
            @php
                $icon = \App\Models\Note::getIconForType($note->type);
                $color = \App\Models\Note::getColorForType($note->type);
                $bgColor = $getBgColor($color);
                $typeLabel = \App\Models\Note::types()[$note->type] ?? $note->type;
            @endphp
            <li class="box">
                <span class="{{ $bgColor }} text-white">
                    <i class="{{ $icon }}"></i>
                </span>
                <a href="#!" class="title d-block text-body fw-semibold mb-2">
                    <span>{{ $note->user ? $note->user->name : 'Sistema' }}</span>
                    <small class="text-muted ms-2">{{ $note->created_at->format('d/m/Y H:i') }}</small>
                </a>
                <div class="p-3 border rounded shadow-sm text-muted sub-title">
                    <span>{{ $note->note }}</span>
                    @if($note->reminder_at)
                        <div class="mt-2 pt-2 border-top">
                            <small class="text-info">
                                <i class="ph ph-clock me-1"></i>
                                Lembrete: {{ $note->reminder_at->format('d/m/Y H:i') }}
                                @if($note->reminder_advance_minutes)
                                    ({{ $note->reminder_advance_minutes }} min antes)
                                @endif
                            </small>
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
@else
    <p class="text-muted text-center py-3">Nenhuma nota adicionada ainda.</p>
@endif
