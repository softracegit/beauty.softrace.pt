@if(isset($activities) && $activities->hasPages())
    <div class="users-pagination mt-3">
        <div class="users-pagination-info">
            <span>Mostrando <strong>{{ $activities->firstItem() ?? 0 }}-{{ $activities->lastItem() ?? 0 }}</strong> de <strong>{{ $activities->total() }}</strong> registos</span>
        </div>
        <div class="users-pagination-nav">
            @if ($activities->onFirstPage())
                <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
            @else
                <a href="{{ $activities->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
            @endif

            @php
                $start = max(1, $activities->currentPage() - 2);
                $end = min($activities->lastPage(), $activities->currentPage() + 2);
            @endphp

            @foreach ($activities->getUrlRange($start, $end) as $page => $url)
                @if ($page == $activities->currentPage())
                    <span class="users-page-btn active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                @endif
            @endforeach

            @if ($activities->hasMorePages())
                <a href="{{ $activities->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
            @else
                <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
            @endif
        </div>
    </div>
@endif
