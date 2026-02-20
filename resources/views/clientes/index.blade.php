@extends('partials.layouts.main')
@section('title', 'Clientes | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
        $totalClientes = $clients->total();
        $activeCount = \App\Models\Client::where('status', \App\Models\Client::STATUS_ACTIVE)->count();
        $availableCount = \App\Models\Client::where('status', \App\Models\Client::STATUS_AVAILABLE)->count();
        $unavailableCount = \App\Models\Client::where('status', \App\Models\Client::STATUS_UNAVAILABLE)->count();
    @endphp

    <!-- Clientes Stats Strip (based on users-stats) -->
    <div class="users-stats">
        <div class="users-stat-card">
            <div class="users-stat-icon primary"><i class="ph-duotone ph-users-three"></i></div>
            <div class="users-stat-body">
                <div class="users-stat-value">{{ $totalClientes }}</div>
                <div class="users-stat-label">Total Clientes</div>
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
            <div class="users-stat-icon warning"><i class="ph-duotone ph-user-circle"></i></div>
            <div class="users-stat-body">
                <div class="users-stat-value">{{ $availableCount }}</div>
                <div class="users-stat-label">Disponíveis</div>
            </div>
        </div>
        <div class="users-stat-card">
            <div class="users-stat-icon danger"><i class="ph-duotone ph-user-minus"></i></div>
            <div class="users-stat-body">
                <div class="users-stat-value">{{ $unavailableCount }}</div>
                <div class="users-stat-label">Indisponíveis</div>
            </div>
        </div>
    </div>

    <!-- Users Table Card (adapted as Clientes table) -->
    <div class="card">
        <div class="users-toolbar">
            <div class="users-toolbar-left">
                <form action="{{ route('clientes.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="users-search">
                        <i class="ph ph-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Pesquisar clientes..." value="{{ request('search') }}">
                    </div>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="ph ph-magnifying-glass me-1"></i> Pesquisar
                    </button>
                </form>
            </div>
            <a href="{{ route('clientes.create') }}" class="btn btn-primary">
                <i class="ph ph-plus me-1"></i> Adicionar Cliente
            </a>
        </div>

        <div class="users-table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>NIF</th>
                        <th>Localidade</th>
                        <th>Estado</th>
                        <th class="users-th-actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $avatarNum = ($client->id % 9) + 1;
                            $avatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
                            $statusClass = match($client->status) {
                                'active' => 'active',
                                'available' => 'pending',
                                'unavailable' => 'inactive',
                                default => 'inactive',
                            };
                            $statusLabel = \App\Models\Client::statusLabels()[$client->status] ?? $client->status;
                        @endphp
                        <tr>
                            <td>
                                <div class="users-cell-user">
                                    <img src="{{ $avatarSrc }}" alt="{{ $client->name }}">
                                    <div>
                                        <a href="{{ route('clientes.show', $client) }}" class="users-cell-name">{{ $client->name }}</a>
                                        <div class="users-cell-email">{{ $client->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($client->phone)
                                    <span class="users-cell-meta">{{ $client->phone }}</span>
                                @else
                                    <span class="users-cell-meta text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($client->nif)
                                    <span class="users-cell-meta">{{ $client->nif }}</span>
                                @else
                                    <span class="users-cell-meta text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($client->locality)
                                    <span class="users-cell-meta">{{ $client->locality }}</span>
                                @else
                                    <span class="users-cell-meta text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                            </td>
                            <td>
                                <div class="users-actions">
                                    <a href="{{ route('clientes.show', $client) }}" class="users-action-btn" title="Ver"><i class="ph ph-eye"></i></a>
                                    <a href="{{ route('clientes.edit', $client) }}" class="users-action-btn" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                    <form action="{{ route('clientes.destroy', $client) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem a certeza que deseja remover este cliente?');">
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
                                <i class="ph ph-user-circle display-4 text-muted"></i>
                                <h6 class="mt-3">Nenhum cliente encontrado</h6>
                                <p class="text-muted mb-3">Comece por adicionar o primeiro cliente.</p>
                                <a href="{{ route('clientes.create') }}" class="btn btn-primary btn-sm"><i class="ph ph-plus me-1"></i> Adicionar Cliente</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
        <!-- Pagination (based on users-pagination) -->
        <div class="users-pagination">
            <div class="users-pagination-info">
                A mostrar <strong>{{ $clients->firstItem() ?? 0 }}-{{ $clients->lastItem() ?? 0 }}</strong> de <strong>{{ $clients->total() }}</strong> clientes
            </div>
            <div class="users-pagination-nav">
                @if ($clients->onFirstPage())
                    <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
                @else
                    <a href="{{ $clients->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
                @endif

                @php
                    $start = max(1, $clients->currentPage() - 2);
                    $end = min($clients->lastPage(), $clients->currentPage() + 2);
                @endphp
                @foreach ($clients->getUrlRange($start, $end) as $page => $url)
                    @if ($page == $clients->currentPage())
                        <span class="users-page-btn active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($clients->hasMorePages())
                    <a href="{{ $clients->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
                @else
                    <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
                @endif
            </div>
        </div>
        @endif
    </div>

@endsection
