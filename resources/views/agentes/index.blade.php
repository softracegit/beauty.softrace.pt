@extends('partials.layouts.main')
@section('title', 'Equipa | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $storeAgentsQuery = \App\Models\Agent::query()->forStore(current_store_id());
    $totalAgentes = (clone $storeAgentsQuery)->count();
    $activeCount = (clone $storeAgentsQuery)->where('status', \App\Models\Agent::STATUS_ACTIVE)->count();
    $inactiveCount = (clone $storeAgentsQuery)->where('status', \App\Models\Agent::STATUS_INACTIVE)->count();
    $onLeaveCount = (clone $storeAgentsQuery)->where('status', \App\Models\Agent::STATUS_ON_LEAVE)->count();
    $currentStatusFilter = request('status', '');
@endphp

<!-- Agentes Stats Strip (based on users-stats) -->
<div class="users-stats">
    <div class="users-stat-card">
        <div class="users-stat-icon primary"><i class="ph-duotone ph-users-three"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $totalAgentes }}</div>
            <div class="users-stat-label">Total Membros</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon success"><i class="ph-duotone ph-user-check"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $activeCount }}</div>
            <div class="users-stat-label">Ativos</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon danger"><i class="ph-duotone ph-user-minus"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $inactiveCount }}</div>
            <div class="users-stat-label">Inativos</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon warning"><i class="ph-duotone ph-user-circle"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $onLeaveCount }}</div>
            <div class="users-stat-label">Em Licença</div>
        </div>
    </div>
</div>

<!-- Users Table Card (adapted as Agentes table) -->
<div class="card">
    <div class="users-toolbar">
        <div class="users-toolbar-left">
            <form action="{{ route('equipa.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <div class="users-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Pesquisar membros..." value="{{ request('search') }}">
                </div>
                <select name="status" class="form-select users-toolbar-select" onchange="this.form.submit()" aria-label="Filtrar por estado">
                    <option value="" {{ $currentStatusFilter === '' ? 'selected' : '' }}>Ativos</option>
                    <option value="inactive" {{ $currentStatusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    <option value="all" {{ $currentStatusFilter === 'all' ? 'selected' : '' }}>Todos</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary users-toolbar-submit">
                    <i class="ph ph-magnifying-glass me-1"></i> Pesquisar
                </button>
            </form>
        </div>
        <a href="{{ route('equipa.create') }}" class="btn btn-primary">
            <i class="ph ph-plus me-1"></i> Adicionar Membro
        </a>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Membro</th>
                    <th>Contacto</th>
                    <th>Especialização</th>
                    <th>Comissão</th>
                    <th>Localidade</th>
                    <th>Estado</th>
                    <th class="users-th-actions">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    @php
                        $avatarNum = ($agent->id % 9) + 1;
                        $avatarSrc = $agent->avatar
                            ? asset('storage/' . $agent->avatar)
                            : asset("template/img/avatars/avatar-{$avatarNum}.webp");
                        $statusClass = match($agent->status) {
                            'active' => 'active',
                            'inactive' => 'inactive',
                            'on_leave' => 'pending',
                            default => 'inactive',
                        };
                        $statusLabel = \App\Models\Agent::statusLabels()[$agent->status] ?? $agent->status;
                    @endphp
                    <tr>
                        <td>
                            <div class="users-cell-user">
                                <img src="{{ $avatarSrc }}" alt="{{ $agent->name }}">
                                <div>
                                    <a href="{{ route('equipa.show', $agent) }}" class="users-cell-name">{{ $agent->name }}</a>
                                    <div class="users-cell-email">{{ $agent->user->email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($agent->phone)
                                <span class="users-cell-meta">{{ $agent->formatted_phone }}</span>
                            @else
                                <span class="users-cell-meta text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $specList = $agent->specialization && in_array($agent->user->role ?? '', \App\Models\User::rolesWithSpecialization(), true)
                                    ? \App\Models\Agent::specializationLabel($agent->specialization)
                                    : null;
                            @endphp
                            @if($specList)
                                <span class="users-cell-meta">{{ $specList }}</span>
                            @else
                                <span class="users-cell-meta text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($agent->commission_rate !== null)
                                <span class="users-cell-meta">{{ $agent->formatCommissionDisplay() }}</span>
                            @else
                                <span class="users-cell-meta text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($agent->locality)
                                <span class="users-cell-meta">{{ $agent->locality }}</span>
                            @else
                                <span class="users-cell-meta text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                        </td>
                        <td>
                            <div class="users-actions">
                                <a href="{{ route('equipa.show', $agent) }}" class="users-action-btn" title="Ver"><i class="ph ph-eye"></i></a>
                                <a href="{{ route('equipa.edit', $agent) }}" class="users-action-btn" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                <form action="{{ route('equipa.destroy', $agent) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem a certeza que deseja remover este membro?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="users-action-btn danger" title="Eliminar"><i class="ph ph-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="ph ph-user-circle display-4 text-muted"></i>
                            <h6 class="mt-3">Nenhum membro encontrado</h6>
                            <p class="text-muted mb-3">Comece por adicionar o primeiro membro.</p>
                            <a href="{{ route('equipa.create') }}" class="btn btn-primary btn-sm"><i class="ph ph-plus me-1"></i> Adicionar Membro</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($agents->hasPages())
    <!-- Pagination (based on users-pagination) -->
    <div class="users-pagination">
        <div class="users-pagination-info">
            A mostrar <strong>{{ $agents->firstItem() ?? 0 }}-{{ $agents->lastItem() ?? 0 }}</strong> de <strong>{{ $agents->total() }}</strong> membros
        </div>
        <div class="users-pagination-nav">
            @if ($agents->onFirstPage())
                <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
            @else
                <a href="{{ $agents->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
            @endif

            @php
                $start = max(1, $agents->currentPage() - 2);
                $end = min($agents->lastPage(), $agents->currentPage() + 2);
            @endphp
            @foreach ($agents->getUrlRange($start, $end) as $page => $url)
                @if ($page == $agents->currentPage())
                    <span class="users-page-btn active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                @endif
            @endforeach

            @if ($agents->hasMorePages())
                <a href="{{ $agents->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
            @else
                <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
            @endif
        </div>
    </div>
    @endif
</div>

@endsection
