@extends('partials.layouts.main-auth')
@section('title', 'Redefinir palavra-passe - ' . config('app.name'))
@section('content')

<div class="auth-card">
  <div class="auth-card-header">
    <div class="auth-icon">
      <i class="ph-duotone ph-lock-key"></i>
    </div>
    <h1 class="auth-title">Redefinir palavra-passe</h1>
    <p class="auth-subtitle">Escolha uma nova palavra-passe para a sua conta.</p>
  </div>

  @if ($errors->any())
    <div class="auth-alert auth-alert-error">
      <ul class="mb-0" style="list-style: none; padding-left: 0;">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form class="auth-form" action="{{ route('password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="form-group">
      <label for="email" class="form-label">Endereço de email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
        value="{{ old('email', $email ?? '') }}" required autofocus>
      @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label for="password" class="form-label">Nova palavra-passe</label>
      <div class="input-group">
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password>
          <i class="ph ph-eye"></i>
        </button>
      </div>
      @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label for="password_confirmation" class="form-label">Confirmar palavra-passe</label>
      <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Guardar nova palavra-passe</button>
  </form>

  <p class="auth-footer-text">
    <a href="{{ route('login') }}" class="auth-link">
      <i class="ph ph-arrow-left"></i> Voltar ao login
    </a>
  </p>
</div>

@endsection

@section('js')
@endsection
