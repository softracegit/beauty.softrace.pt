@extends('partials.layouts.main-auth')
@section('title', 'Login - ' . config('app.name'))
@section('content')

<div class="auth-card">
  <div class="auth-card-header">
    <h1 class="auth-title">Bem-vindo de volta</h1>
    <p class="auth-subtitle">Introduza as suas credenciais para aceder à sua conta</p>
  </div>

  @if (session('error'))
    <div class="auth-alert auth-alert-error" role="alert">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="auth-alert auth-alert-error">
      <ul class="mb-0" style="list-style: none; padding-left: 0;">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form class="auth-form" action="{{ route('login') }}" method="POST">
    @csrf
    
    <div class="form-group">
      <label for="email" class="form-label">Endereço de email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="nome@exemplo.com" value="{{ old('email') }}" required autofocus>
      @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center">
        <label for="password" class="form-label">Palavra-passe</label>
        <a href="{{ route('password.request') }}" class="auth-link small">Esqueceu a palavra-passe?</a>
      </div>
      <div class="input-group">
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Introduza a sua palavra-passe" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password>
          <i class="ph ph-eye"></i>
        </button>
      </div>
      @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
      <label class="form-check-label" for="remember">Lembrar-me</label>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Iniciar sessão</button>
  </form>

  <p class="auth-footer-text">
    Não tem uma conta? <a href="{{ route('register') }}" class="auth-link">Criar uma conta</a>
  </p>
</div>

@endsection

@section('js')
@endsection
