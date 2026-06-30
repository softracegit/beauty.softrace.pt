<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Login - ' . config('app.name'))</title>
  <meta name="description" content="@yield('meta-description', config('app.name') . ' - Admin')">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link href="{{ asset('template/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

  @yield('css')
  @include('partials.head-css')
</head>

<body>
  <div class="auth-layout">
    <!-- Branding Panel -->
    <div class="auth-brand-panel">
      <div class="auth-brand-content">
        <a href="{{ route('dashboard') }}" class="auth-brand-logo">
          <img src="{{ asset('template/img/logo-color-white.png') }}" alt="{{ config('app.name') }}">
        </a>
        <div class="auth-brand-text">
          <h2>Soluções inteligentes para equipas modernas</h2>
          <p>Simplifique o seu fluxo de trabalho, gere a sua equipa e tome decisões baseadas em dados — tudo a partir de um dashboard poderoso.</p>
        </div>
        <div class="auth-brand-features">
          <div class="auth-brand-feature">
            <i class="ph-duotone ph-chart-line-up"></i>
            <span>Análise em Tempo Real</span>
          </div>
          <div class="auth-brand-feature">
            <i class="ph-duotone ph-users-three"></i>
            <span>Gestão de Equipa</span>
          </div>
          <div class="auth-brand-feature">
            <i class="ph-duotone ph-shield-check"></i>
            <span>Segurança Empresarial</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Panel -->
    <div class="auth-form-panel">
      <div class="auth-container">
        <!-- Mobile Logo (hidden on desktop) -->
        <a href="{{ route('dashboard') }}" class="auth-logo">
          <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}">
        </a>

        @yield('content')

        <!-- Footer -->
        <footer class="footer-centered">
          <div class="footer-copyright">
            &copy; {{ date('Y') }} <a href="#">{{ config('app.name') }}</a>. Todos os direitos reservados.
          </div>
          <div class="footer-links">
            <a href="#">Política de Privacidade</a>
            <a href="#">Termos de Serviço</a>
            <a href="#">Ajuda</a>
          </div>
        </footer>
      </div>
    </div>
  </div>

  @include('partials.vendor-scripts')
  @yield('js')
</body>

</html>
