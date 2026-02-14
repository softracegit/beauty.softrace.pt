@extends('partials.layouts.main')
@section('title', $property->reference . ' | Imobiliária')
@section('page-heading-title', $property->reference)
@section('page-heading-sub-title', 'Imóveis')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $statusColor = $property->status_color;
@endphp

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Informações do Imóvel</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('properties.edit', $property) }}" class="btn btn-primary btn-sm"><i class="ph ph-pencil-simple me-1"></i> Editar</a>
                    <form action="{{ route('properties.destroy', $property) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem a certeza que deseja remover este imóvel?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><i class="ph ph-trash me-1"></i> Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Referência</label>
                        <p class="mb-0 fw-medium">{{ $property->reference }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Estado</label>
                        <p class="mb-0">
                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ $statuses[$property->status] ?? $property->status }}</span>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Tipo de Transação</label>
                        <p class="mb-0">
                            <span class="badge bg-info-subtle text-info">{{ $property->transactionType->name ?? '—' }}</span>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Preço</label>
                        <p class="mb-0 fw-medium fs-5 text-primary">{{ $property->formatted_price }}</p>
                    </div>
                    @if($property->description)
                    <div class="col-12">
                        <label class="form-label text-muted small text-uppercase">Descrição</label>
                        <p class="mb-0 text-muted">{{ $property->description }}</p>
                    </div>
                    @endif
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Localização</h6>
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label text-muted small text-uppercase">Morada Completa</label>
                        <p class="mb-0">{{ $property->full_address }}</p>
                    </div>
                    @if($property->latitude && $property->longitude)
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Coordenadas</label>
                        <p class="mb-0"><small>{{ $property->latitude }}, {{ $property->longitude }}</small></p>
                    </div>
                    @endif
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Características</h6>
                <div class="row g-4">
                    @if($property->propertyTypology)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Tipologia</label>
                        <p class="mb-0 fw-medium">{{ $property->propertyTypology->name }}</p>
                    </div>
                    @endif
                    @if($property->area_total)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Área Total</label>
                        <p class="mb-0">{{ number_format($property->area_total, 2, ',', '.') }} m²</p>
                    </div>
                    @endif
                    @if($property->area_private)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Área Privada</label>
                        <p class="mb-0">{{ number_format($property->area_private, 2, ',', '.') }} m²</p>
                    </div>
                    @endif
                    @if($property->bedrooms)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Quartos</label>
                        <p class="mb-0">{{ $property->bedrooms }}</p>
                    </div>
                    @endif
                    @if($property->bathrooms)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Casas de Banho</label>
                        <p class="mb-0">{{ $property->bathrooms }}</p>
                    </div>
                    @endif
                    @if($property->garages)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Garagens</label>
                        <p class="mb-0">{{ $property->garages }}</p>
                    </div>
                    @endif
                    @if($property->parking_spaces)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Lugares Estacionamento</label>
                        <p class="mb-0">{{ $property->parking_spaces }}</p>
                    </div>
                    @endif
                    @if($property->floor !== null)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Andar</label>
                        <p class="mb-0">{{ $property->floor }}</p>
                    </div>
                    @endif
                    @if($property->year_built)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Ano de Construção</label>
                        <p class="mb-0">{{ $property->year_built }}</p>
                    </div>
                    @endif
                    @if($property->energy_certificate)
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small text-uppercase">Certificado Energético</label>
                        <p class="mb-0"><span class="badge bg-primary">{{ $property->energy_certificate }}</span></p>
                    </div>
                    @endif
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Detalhes</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3">
                            @if($property->elevator)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Elevador</span>
                            @endif
                            @if($property->furnished)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Mobilado</span>
                            @endif
                            @if($property->balcony)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Varanda</span>
                            @endif
                            @if($property->terrace)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Terraço</span>
                            @endif
                            @if($property->storage)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Arrumos</span>
                            @endif
                            @if($property->air_conditioning)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Ar Condicionado</span>
                            @endif
                            @if($property->heating)
                                <span class="badge bg-success-subtle text-success"><i class="ph ph-check me-1"></i> Aquecimento</span>
                            @endif
                            @if($property->orientation)
                                <span class="badge bg-info-subtle text-info">Orientação: {{ $orientations[$property->orientation] ?? $property->orientation }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($property->condominium_fee || $property->imi_value || $property->commission_percentage)
                <hr class="my-4">

                <h6 class="mb-3">Informações Financeiras</h6>
                <div class="row g-4">
                    @if($property->condominium_fee)
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Condomínio</label>
                        <p class="mb-0">{{ number_format($property->condominium_fee, 2, ',', '.') }} €</p>
                    </div>
                    @endif
                    @if($property->imi_value)
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">IMI</label>
                        <p class="mb-0">{{ number_format($property->imi_value, 2, ',', '.') }} €</p>
                    </div>
                    @endif
                    @if($property->commission_percentage)
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Comissão</label>
                        <p class="mb-0">{{ $property->commission_percentage }}% 
                            @if($property->commission_value)
                                ({{ number_format($property->commission_value, 2, ',', '.') }} €)
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Estado</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase">Estado</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} fs-6">{{ $statuses[$property->status] ?? $property->status }}</span>
                    </p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase">Ativo</label>
                    <p class="mb-0">
                        @if($property->active)
                            <span class="badge bg-success-subtle text-success">Sim</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Não</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="form-label text-muted small text-uppercase">Criado em</label>
                    <p class="mb-0"><small>{{ $property->created_at->format('d/m/Y H:i') }}</small></p>
                </div>
                @if($property->updated_at != $property->created_at)
                <div>
                    <label class="form-label text-muted small text-uppercase">Atualizado em</label>
                    <p class="mb-0"><small>{{ $property->updated_at->format('d/m/Y H:i') }}</small></p>
                </div>
                @endif
            </div>
        </div>

        @if($property->images->count() > 0)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Imagens</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($property->images as $image)
                    <div class="col-6">
                        <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $property->title }}" class="img-fluid rounded">
                    </div>
                    @endforeach
                </div>
                <p class="text-muted small mt-2 mb-0">Gestão de imagens será implementada em breve.</p>
            </div>
        </div>
        @endif

        <!-- Notas em Timeline -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notas</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="ph ph-plus me-1"></i> Adicionar Nota
                </button>
            </div>
            <div class="card-body">
                @php
                    // Garantir que usamos o relacionamento, não o atributo 'notes'
                    $propertyNotes = $property->getRelationValue('notes') ?: $property->notes()->with('user')->get();
                @endphp
                @include('partials.notes-timeline', ['notes' => $propertyNotes])
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('properties.storeNote', $property), 'modelName' => 'imóvel'])

@endsection
