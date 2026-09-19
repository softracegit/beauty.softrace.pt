@extends('partials.layouts.main')
@section('title', 'Lojas | Beauty CRM')
@section('content')

<div class="users-stats">
    <div class="users-stat-card">
        <div class="users-stat-icon primary"><i class="ph-duotone ph-storefront"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $totalLojas }}</div>
            <div class="users-stat-label">Total Lojas</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon success"><i class="ph-duotone ph-users-three"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $comEquipa }}</div>
            <div class="users-stat-label">Com equipa</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon info"><i class="ph-duotone ph-scissors"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $comServicos }}</div>
            <div class="users-stat-label">Com serviços activos</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon warning"><i class="ph-duotone ph-warning-circle"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $semServicosActivos }}</div>
            <div class="users-stat-label">Sem serviços activos</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="users-toolbar">
        <div class="users-toolbar-left">
            <form action="{{ route('lojas.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <div class="users-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Pesquisar lojas..." value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-outline-secondary users-toolbar-submit">
                    <i class="ph ph-magnifying-glass me-1"></i> Pesquisar
                </button>
            </form>
        </div>
        <a href="{{ route('lojas.create') }}" class="btn btn-primary">
            <i class="ph ph-plus me-1"></i> Adicionar Loja
        </a>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Loja</th>
                    <th>Contacto</th>
                    <th>Cidade</th>
                    <th>Equipa</th>
                    <th>Serviços activos</th>
                    <th class="users-th-actions">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stores as $store)
                    @php
                        $initial = mb_strtoupper(mb_substr(trim((string) $store->name), 0, 1, 'UTF-8') ?: 'L', 'UTF-8');
                    @endphp
                    <tr>
                        <td>
                            <div class="users-cell-user">
                                <div class="users-cell-avatar-initial" aria-label="{{ $store->name }}">{{ $initial }}</div>
                                <div>
                                    <a href="{{ route('lojas.edit', $store) }}" class="users-cell-name">{{ $store->name }}</a>
                                    <div class="users-cell-email"><code>{{ $store->slug }}</code></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($store->phone || $store->email)
                                <span class="users-cell-meta">{{ $store->phone ?: '—' }}</span>
                                @if ($store->email)
                                    <div class="users-cell-email">{{ $store->email }}</div>
                                @endif
                            @else
                                <span class="users-cell-meta text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="users-cell-meta">{{ $store->city ?: '—' }}</span>
                        </td>
                        <td>
                            <span class="users-cell-meta">{{ $store->agents_count }}</span>
                        </td>
                        <td>
                            <span class="users-cell-meta">{{ $store->active_services_count }}</span>
                        </td>
                        <td>
                            <div class="users-actions">
                                <a href="{{ route('booking.index', ['store' => $store->slug]) }}" class="users-action-btn" title="Marcação online" target="_blank" rel="noopener"><i class="ph ph-calendar-blank"></i></a>
                                <a href="{{ route('lojas.edit', $store) }}" class="users-action-btn" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                <form method="POST" action="{{ route('lojas.destroy', $store) }}" class="d-inline" onsubmit="return confirm('Eliminar esta loja? Só é permitido sem dados.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="users-action-btn danger" title="Eliminar"><i class="ph ph-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="ph ph-storefront display-4 text-muted"></i>
                            <h6 class="mt-3">Nenhuma loja encontrada</h6>
                            <p class="text-muted mb-3">Comece por adicionar a primeira loja da organização.</p>
                            <a href="{{ route('lojas.create') }}" class="btn btn-primary btn-sm"><i class="ph ph-plus me-1"></i> Adicionar Loja</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($stores->hasPages())
    <div class="users-pagination">
        <div class="users-pagination-info">
            A mostrar <strong>{{ $stores->firstItem() ?? 0 }}-{{ $stores->lastItem() ?? 0 }}</strong> de <strong>{{ $stores->total() }}</strong> lojas
        </div>
        <div class="users-pagination-nav">
            @if ($stores->onFirstPage())
                <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
            @else
                <a href="{{ $stores->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
            @endif

            @php
                $start = max(1, $stores->currentPage() - 2);
                $end = min($stores->lastPage(), $stores->currentPage() + 2);
            @endphp
            @foreach ($stores->getUrlRange($start, $end) as $page => $url)
                @if ($page == $stores->currentPage())
                    <span class="users-page-btn active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                @endif
            @endforeach

            @if ($stores->hasMorePages())
                <a href="{{ $stores->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
            @else
                <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
            @endif
        </div>
    </div>
    @endif
</div>

@endsection
