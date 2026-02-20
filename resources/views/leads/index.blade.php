@extends('partials.layouts.main')
@section('title', 'Leads | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <form action="{{ route('leads.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                <div class="form-icon right">
                    <input type="text" name="search" class="form-control form-control-icon" placeholder="Pesquisar..." value="{{ request('search') }}">
                    <i class="ph ph-magnifying-glass text-muted"></i>
                </div>
                <select name="type" class="form-select" style="width: auto;">
                    <option value="">Todos os Tipos</option>
                    @foreach(\App\Models\Lead::types() as $value => $label)
                        <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select" style="width: auto;">
                    <option value="">Todos os Estados</option>
                    @foreach(\App\Models\Lead::statuses() as $value => $label)
                        <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-light"><i class="ph ph-magnifying-glass me-1"></i> Pesquisar</button>
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('leads.kanban') }}" class="btn btn-light"><i class="ph ph-squares-four me-1"></i> Kanban</a>
                <a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Nova Lead</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Origem</th>
                        <th>Estado</th>
                        <th>Prioridade</th>
                        <th>Responsável</th>
                        <th>Última Atualização</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $lead)
                        @php
                            $statusColor = $lead->status_color;
                            $priorityColor = $lead->priority_color;
                        @endphp
                        <tr>
                            <td>{{ $lead->lead_id }}</td>
                            <td>
                                <a href="{{ route('leads.show', $lead) }}" class="text-body fw-medium">{{ $lead->name }}</a>
                                @if($lead->email)
                                    <br><small class="text-muted">{{ $lead->email }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info">{{ \App\Models\Lead::types()[$lead->type] ?? $lead->type }}</span>
                            </td>
                            <td>{{ \App\Models\Lead::origins()[$lead->origin] ?? $lead->origin }}</td>
                            <td>
                                <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ \App\Models\Lead::statuses()[$lead->status] ?? $lead->status }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $priorityColor }}-subtle text-{{ $priorityColor }}">{{ \App\Models\Lead::priorities()[$lead->priority] ?? $lead->priority }}</span>
                            </td>
                            <td>
                                @if($lead->agent)
                                    {{ $lead->agent->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">{{ $lead->status_changed_at ? $lead->status_changed_at->diffForHumans() : $lead->updated_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                                    <a href="{{ route('leads.edit', $lead) }}" class="btn btn-sm btn-light" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="ph-duotone ph-tray fs-1 text-muted"></i>
                                <p class="text-muted mt-2">Nenhuma lead encontrada</p>
                                <a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Criar Primeira Lead</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($leads->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $leads->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
