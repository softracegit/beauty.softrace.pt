@extends('partials.layouts.super-admin')
@section('title', 'Editar loja')
@section('content')
  <div class="mb-4">
    <a href="{{ route('super-admin.organizations.show', $organization) }}" class="text-decoration-none small">← {{ $organization->name }}</a>
    <h1 class="h3 mt-2 mb-0">Editar loja: {{ $store->name }}</h1>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('super-admin.organizations.stores.update', [$organization, $store]) }}" class="row g-3">
        @csrf
        @method('PUT')
        <div class="col-md-6">
          <label class="form-label">Nome <span class="text-danger">*</span></label>
          <input type="text" name="name" value="{{ old('name', $store->name) }}" class="form-control @error('name') is-invalid @enderror" required>
          @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Slug <span class="text-danger">*</span></label>
          <input type="text" name="slug" value="{{ old('slug', $store->slug) }}" class="form-control @error('slug') is-invalid @enderror" required>
          @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Fuso horário</label>
          <input type="text" name="timezone" value="{{ old('timezone', $store->timezone) }}" class="form-control @error('timezone') is-invalid @enderror">
          @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Telefone</label>
          <input type="text" name="phone" value="{{ old('phone', $store->phone) }}" class="form-control @error('phone') is-invalid @enderror">
          @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email', $store->email) }}" class="form-control @error('email') is-invalid @enderror">
          @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
          <label class="form-label">Morada</label>
          <input type="text" name="address_line" value="{{ old('address_line', $store->address_line) }}" class="form-control @error('address_line') is-invalid @enderror">
          @error('address_line')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Cidade</label>
          <input type="text" name="city" value="{{ old('city', $store->city) }}" class="form-control @error('city') is-invalid @enderror">
          @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Código postal</label>
          <input type="text" name="postal_code" value="{{ old('postal_code', $store->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror">
          @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Guardar</button>
          <a href="{{ route('super-admin.organizations.show', $organization) }}" class="btn btn-link">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
@endsection
