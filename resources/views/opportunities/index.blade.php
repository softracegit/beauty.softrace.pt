@extends('partials.layouts.main')
@section('title', 'Oportunidades | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route('opportunities.index') }}" method="GET" class="row g-3">
            <div class="col-12 col-md-3">
                <div class="form-icon right">
                    <input type="text" name="search" class="form-control form-control-icon" placeholder="Pesquisar..." value="{{ request('search') }}">
                    <i class="ph ph-magnifying-glass text-muted"></i>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <select name="status" class="form-select">
                    <option value="">Todos os Estados</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="transaction_type_id" class="form-select">
                    <option value="">Todos os Tipos</option>
                    @foreach($transactionTypes as $id => $name)
                        <option value="{{ $id }}" {{ request('transaction_type_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="client_id" class="form-select">
                    <option value="">Todos os Clientes</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="agent_id" class="form-select">
                    <option value="">Todos os Agentes</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="ph ph-magnifying-glass me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Lista de Oportunidades</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('opportunities.kanban') }}" class="btn btn-light"><i class="ph ph-squares-four me-1"></i> Kanban</a>
            <a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Nova Oportunidade</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Título</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Agente</th>
                        <th>Lead</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($opportunities as $opportunity)
                        @php
                            $statusColor = $opportunity->status_color;
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $opportunity->reference }}</span>
                            </td>
                            <td>
                                <a href="{{ route('opportunities.show', $opportunity) }}" class="text-body fw-medium">{{ $opportunity->title }}</a>
                            </td>
                            <td>
                                <a href="{{ route('clientes.show', $opportunity->client) }}" class="text-body">{{ $opportunity->client->name }}</a>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info">{{ $opportunity->transactionType->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ $statuses[$opportunity->status] ?? $opportunity->status }}</span>
                            </td>
                            <td>
                                @if($opportunity->agent)
                                    {{ $opportunity->agent->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($opportunity->lead)
                                    <a href="{{ route('leads.show', $opportunity->lead) }}" class="text-body">{{ $opportunity->lead->lead_id }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('opportunities.show', $opportunity) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                                    <a href="{{ route('opportunities.edit', $opportunity) }}" class="btn btn-sm btn-light" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="ph-duotone ph-briefcase fs-1 text-muted"></i>
                                <p class="text-muted mt-2">Nenhuma oportunidade encontrada</p>
                                <a href="{{ route('opportunities.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Criar Primeira Oportunidade</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($opportunities->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $opportunities->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
