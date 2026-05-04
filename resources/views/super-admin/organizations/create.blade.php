@extends('partials.layouts.super-admin')
@section('title', 'Nova organização')
@section('content')
  <div class="mb-4">
    <a href="{{ route('super-admin.organizations.index') }}" class="text-decoration-none small">← Organizações</a>
    <h1 class="h3 mt-2 mb-0">Nova organização</h1>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('super-admin.organizations.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
          <label class="form-label">Nome <span class="text-danger">*</span></label>
          <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
          @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Slug</label>
          <input type="text" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="Opcional (gerado a partir do nome)">
          @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">NIF</label>
          <input type="text" name="nif" value="{{ old('nif') }}" class="form-control @error('nif') is-invalid @enderror">
          @error('nif')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Telefone</label>
          <input type="text" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror">
          @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
          @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado <span class="text-danger">*</span></label>
          <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" @selected(old('status', 'active') === 'active')>Activa</option>
            <option value="suspended" @selected(old('status') === 'suspended')>Suspensa</option>
          </select>
          @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Criar</button>
          <a href="{{ route('super-admin.organizations.index') }}" class="btn btn-link">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
@endsection
