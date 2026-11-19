@php
    $members = $service->relationLoaded('agents')
        ? $service->agents->values()
        : collect();
    $agentsN = (int) ($service->agents_count ?? $members->count());
    $maxVisible = 5;
    $membersPayload = $members->map(static function ($agent) {
        return [
            'id' => (int) $agent->id,
            'name' => (string) $agent->name,
            'avatar' => $agent->avatar,
            'store_id' => (int) $agent->store_id,
            'store_name' => (string) ($agent->store?->name ?: 'Sem loja'),
        ];
    })->values()->all();
@endphp
<button
    type="button"
    class="service-item-members-trigger"
    data-service-name="{{ $service->name }}"
    data-members='@json($membersPayload)'
    title="Ver membros associados"
    aria-label="Ver {{ $agentsN }} membro{{ $agentsN === 1 ? '' : 's' }} associados"
>
    <span class="badge text-bg-light">{{ $agentsN }} membro{{ $agentsN === 1 ? '' : 's' }}</span>
    @if($members->isNotEmpty())
        <span class="service-item-members avatar-group" aria-hidden="true">
            @foreach($members->take($maxVisible) as $agent)
                @php
                    $nameParts = preg_split('/\s+/u', trim((string) $agent->name)) ?: [];
                    $nameParts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));
                    if (count($nameParts) >= 2) {
                        $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8').mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8'), 'UTF-8');
                    } elseif (count($nameParts) === 1) {
                        $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
                    } else {
                        $initials = '?';
                    }
                    $avatarSrc = $agent->avatar ? asset('storage/'.$agent->avatar) : null;
                @endphp
                @if($avatarSrc)
                    <img src="{{ $avatarSrc }}" alt="" class="avatar" loading="lazy">
                @else
                    <span class="avatar service-item-member-initial">{{ $initials }}</span>
                @endif
            @endforeach
            @if($members->count() > $maxVisible)
                <span class="avatar-more">+{{ $members->count() - $maxVisible }}</span>
            @endif
        </span>
    @endif
</button>
