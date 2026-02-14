@extends('partials.layouts.main-auth')
@section('title', 'Recuperar Palavra-passe - ' . config('app.name'))
@section('content')

<div class="auth-card">
  <div class="auth-card-header">
    <div class="auth-icon">
      <i class="ph-duotone ph-key"></i>
    </div>
    <h1 class="auth-title">Esqueceu a palavra-passe?</h1>
    <p class="auth-subtitle">Sem problemas, enviaremos instruções para redefinir.</p>
  </div>

  @if (session('status'))
    <div class="auth-alert auth-alert-success">
      {{ session('status') }}
    </div>
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

  <form class="auth-form" action="{{ route('password.email') }}" method="POST">
    @csrf
    
    <div class="form-group">
      <label for="email" class="form-label">Endereço de email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="nome@exemplo.com" value="{{ old('email') }}" required autofocus>
      @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <button type="submit" class="btn btn-primary btn-block">Enviar link de redefinição</button>
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
