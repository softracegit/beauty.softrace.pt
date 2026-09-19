@extends('partials.layouts.main')
@section('title', 'Equipa | Beauty CRM')
@section('content')

@php
    $currentStatusFilter = request('status', '');
    $showStoreColumn = $showStoreColumn ?? false;
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
            <form action="{{ route('equipa.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap flex-grow-1 min-w-0" id="equipa-filter-form">
                <div class="users-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Pesquisar..." value="{{ request('search') }}">
                </div>
                @include('partials.store-context-filter', [
                    'selected' => ($equipaStoreFilter['store_id'] ?? null) === null
                        ? \App\Support\StoreContextPreference::SCOPE_ALL
                        : $equipaStoreFilter['store_id'],
                    'allowAll' => true,
                    'allLabel' => 'Todas as lojas',
                    'formId' => 'equipa-filter-form',
                    'inputId' => 'equipa_store_filter',
                    'class' => 'users-toolbar-select equipa-store-filter',
                    'style' => '',
                ])
                <select name="status" class="form-select users-toolbar-select equipa-status-filter" onchange="this.form.submit()" aria-label="Filtrar por estado">
                    <option value="" {{ $currentStatusFilter === '' ? 'selected' : '' }}>Ativos</option>
                    <option value="inactive" {{ $currentStatusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    <option value="all" {{ $currentStatusFilter === 'all' ? 'selected' : '' }}>Todos</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary users-toolbar-submit">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
            </form>
        </div>
        <a href="{{ route('equipa.create') }}" class="btn btn-primary text-nowrap flex-shrink-0">
            <i class="ph ph-plus"></i> Adicionar
        </a>
    </div>

    <style>
      #equipa_store_filter.equipa-store-filter,
      .equipa-status-filter {
        min-width: 0 !important;
        max-width: 9.5rem;
        width: auto;
      }
      #equipa_store_filter.equipa-store-filter {
        max-width: 11rem;
      }
    </style>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Membro</th>
                    @if ($showStoreColumn)
                      <th>Loja</th>
                    @endif
                    <th>Contacto</th>
                    <th>Especialização</th>
                    <th>Comissão</th>
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
                        @if ($showStoreColumn)
                          <td>
                            <span class="users-cell-meta">{{ $agent->store?->name ?? '—' }}</span>
                          </td>
                        @endif
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
                            <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                        </td>
                        <td>
                            <div class="users-actions">
                                <a href="{{ route('equipa.show', $agent) }}" class="users-action-btn" title="Ver"><i class="ph ph-eye"></i></a>
                                <a href="{{ route('equipa.edit', $agent) }}" class="users-action-btn" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                @can('migrateStore', $agent)
                                    @if(($migrateStores ?? collect())->isNotEmpty())
                                        <button
                                            type="button"
                                            class="users-action-btn"
                                            title="Mudar de loja"
                                            data-bs-toggle="modal"
                                            data-bs-target="#migrateStoreModal"
                                            data-agent-id="{{ $agent->id }}"
                                            data-agent-name="{{ $agent->name }}"
                                            data-action="{{ route('equipa.migrate-store', $agent) }}"
                                        >
                                            <i class="ph ph-arrows-left-right"></i>
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
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

@if(($migrateStores ?? collect())->isNotEmpty())
    @include('agentes.partials.migrate-store-modal')
@endif

@endsection
