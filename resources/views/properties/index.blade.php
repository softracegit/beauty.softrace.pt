@extends('partials.layouts.main')
@section('title', 'Imóveis | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route('properties.index') }}" method="GET" class="row g-3">
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
                <select name="id_district" class="form-select">
                    <option value="">Todos os Distritos</option>
                    @foreach($districts as $district)
                        <option value="{{ $district['id'] }}" {{ request('id_district') == $district['id'] ? 'selected' : '' }}>{{ $district['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="active" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Ativos</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inativos</option>
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
        <h5 class="card-title mb-0">Lista de Imóveis</h5>
        <a href="{{ route('properties.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Novo Imóvel</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Localização</th>
                        <th>Preço</th>
                        <th>Estado</th>
                        <th>Ativo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($properties as $property)
                        @php
                            $statusColor = $property->status_color;
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $property->reference }}</span>
                            </td>
                            <td>
                                <a href="{{ route('properties.show', $property) }}" class="text-body fw-medium">{{ $property->title }}</a>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info">{{ $property->transactionType->name ?? '—' }}</span>
                            </td>
                            <td>
                                <small>{{ $property->full_address }}</small>
                            </td>
                            <td>
                                <span class="fw-medium">{{ $property->formatted_price }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ $statuses[$property->status] ?? $property->status }}</span>
                            </td>
                            <td>
                                @if($property->active)
                                    <span class="badge bg-success-subtle text-success">Sim</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Não</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                                    <a href="{{ route('properties.edit', $property) }}" class="btn btn-sm btn-light" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="ph-duotone ph-house fs-1 text-muted"></i>
                                <p class="text-muted mt-2">Nenhum imóvel encontrado</p>
                                <a href="{{ route('properties.create') }}" class="btn btn-primary"><i class="ph ph-plus me-1"></i> Criar Primeiro Imóvel</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($properties->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $properties->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
