@extends('partials.layouts.main')
@section('title', 'Novo Agente | Imobiliária')
@section('page-heading-title', 'Novo Agente')
@section('page-heading-sub-title', 'Real Estate')
@section('content')

<div class="row">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Dados do Agente</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('agentes.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="name" class="form-label">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: João Silva" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Dados de Acesso ao Sistema</h6>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" placeholder="email@exemplo.com" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Mínimo 8 caracteres" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Repita a password" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="role" class="form-label">Tipo de Utilizador <span class="text-danger">*</span></label>
                            <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
                                @foreach(\App\Models\User::roles() as $value => $label)
                                    <option value="{{ $value }}" {{ old('role', 'consultor') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Dados Pessoais</h6>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="+351 912 345 678" value="{{ old('phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="nif" class="form-label">NIF</label>
                            <input type="text" name="nif" id="nif" class="form-control @error('nif') is-invalid @enderror" placeholder="123456789" value="{{ old('nif') }}" maxlength="20">
                            @error('nif')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="birth_date" class="form-label">Data de Nascimento</label>
                            <input type="date" name="birth_date" id="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date') }}" max="{{ date('Y-m-d', strtotime('-18 years')) }}">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gender" class="form-label">Género</label>
                            <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Agent::genders() as $value => $label)
                                    <option value="{{ $value }}" {{ old('gender') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="nationality" class="form-label">Nacionalidade</label>
                            <input type="text" name="nationality" id="nationality" class="form-control @error('nationality') is-invalid @enderror" placeholder="Ex: Portuguesa" value="{{ old('nationality') }}">
                            @error('nationality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="marital_status" class="form-label">Estado Civil</label>
                            <select name="marital_status" id="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Agent::maritalStatuses() as $value => $label)
                                    <option value="{{ $value }}" {{ old('marital_status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('marital_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Morada</h6>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Morada</label>
                            <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror" placeholder="Ex: Rua das Flores, 123" value="{{ old('address') }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="door" class="form-label">Porta</label>
                            <input type="text" name="door" id="door" class="form-control @error('door') is-invalid @enderror" placeholder="Ex: 1A" value="{{ old('door') }}" maxlength="10">
                            @error('door')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="floor" class="form-label">Andar</label>
                            <input type="text" name="floor" id="floor" class="form-control @error('floor') is-invalid @enderror" placeholder="Ex: 2º" value="{{ old('floor') }}" maxlength="10">
                            @error('floor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="side" class="form-label">Lado</label>
                            <input type="text" name="side" id="side" class="form-control @error('side') is-invalid @enderror" placeholder="Ex: Esq." value="{{ old('side') }}" maxlength="10">
                            @error('side')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="postal_code" class="form-label">Código Postal</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" placeholder="Ex: 3800-000" value="{{ old('postal_code') }}" maxlength="20">
                            @error('postal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locality" class="form-label">Localidade</label>
                            <input type="text" name="locality" id="locality" class="form-control @error('locality') is-invalid @enderror" placeholder="Ex: Aveiro" value="{{ old('locality') }}">
                            @error('locality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <hr class="my-3">
                            <h6 class="mb-3">Dados Profissionais</h6>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="specialization" class="form-label">Especialização</label>
                            <input type="text" name="specialization" id="specialization" class="form-control @error('specialization') is-invalid @enderror" placeholder="Ex: Apartamentos, Moradias" value="{{ old('specialization') }}">
                            @error('specialization')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="commission_rate" class="form-label">Taxa de Comissão (%)</label>
                            <input type="number" name="commission_rate" id="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" placeholder="Ex: 5.00" value="{{ old('commission_rate') }}" step="0.01" min="0" max="100">
                            @error('commission_rate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach(\App\Models\Agent::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', 'active') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Observações sobre o agente...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <a href="{{ route('agentes.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
