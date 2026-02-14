@extends('partials.layouts.main-auth')
@section('title', 'Criar Conta - ' . config('app.name'))
@section('content')

<div class="auth-card">
  <div class="auth-card-header">
    <h1 class="auth-title">Criar uma conta</h1>
    <p class="auth-subtitle">Comece a sua experiência gratuita</p>
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

  <form class="auth-form" action="{{ route('register') }}" method="POST">
    @csrf
    
    <div class="form-group">
      <label for="name" class="form-label">Nome completo</label>
      <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" placeholder="João Silva" value="{{ old('name') }}" required autofocus>
      @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label for="email" class="form-label">Endereço de email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="nome@exemplo.com" value="{{ old('email') }}" required>
      @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label for="password" class="form-label">Palavra-passe</label>
      <div class="input-group">
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Crie uma palavra-passe" required minlength="8">
        <button class="btn btn-outline-secondary" type="button" data-toggle-password>
          <i class="ph ph-eye"></i>
        </button>
      </div>
      <div class="form-text">Mínimo de 8 caracteres.</div>
      @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label for="password_confirmation" class="form-label">Confirmar palavra-passe</label>
      <div class="input-group">
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Confirme a palavra-passe" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password>
          <i class="ph ph-eye"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Criar conta</button>
  </form>

  <p class="auth-footer-text">
    Já tem uma conta? <a href="{{ route('login') }}" class="auth-link">Iniciar sessão</a>
  </p>
</div>

@endsection

@section('js')
@endsection
