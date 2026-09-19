@extends('partials.layouts.main')
@section('title', 'Nova Loja | Beauty CRM')
@section('content')

<form method="POST" action="{{ route('lojas.store') }}">
    @csrf

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-0 text-muted small">
                Contactos, logótipos e horário nascem iguais aos de <strong>Definições → Empresa</strong>.
                Aqui só precisa da identidade e localização desta loja. O PIN do posto define-se depois na ficha da loja.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="uedit-section mb-4">
                <div class="uedit-section-title">Identidade</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: Fadastudio Aveiro" required maxlength="255">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Slug (URL pública) <span class="text-danger">*</span></label>
                        <input type="text" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="fadastudio-aveiro" required maxlength="255">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="uedit-section">
                <div class="uedit-section-title">Morada e mapa</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Rua / número <span class="text-danger">*</span></label>
                        <input type="text" name="address_line" value="{{ old('address_line') }}" class="form-control @error('address_line') is-invalid @enderror" required maxlength="255">
                        @error('address_line')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Código postal <span class="text-danger">*</span></label>
                        <input type="text" name="postal_code" value="{{ old('postal_code') }}" class="form-control @error('postal_code') is-invalid @enderror" required maxlength="32">
                        @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Localidade <span class="text-danger">*</span></label>
                        <input type="text" name="city" value="{{ old('city') }}" class="form-control @error('city') is-invalid @enderror" required maxlength="120">
                        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Link Google Maps <span class="text-danger">*</span></label>
                        <input type="url" name="maps_url" value="{{ old('maps_url') }}" class="form-control @error('maps_url') is-invalid @enderror" placeholder="https://maps.google.com/..." required maxlength="512">
                        @error('maps_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="uedit-form-actions mt-3">
        <a href="{{ route('lojas.index') }}" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Criar Loja
        </button>
    </div>
</form>

@endsection
