<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Super Admin') — {{ config('app.name') }}</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="{{ asset('template/img/favicon.png') }}" rel="icon">
  @include('partials.head-css')
</head>
<body class="bg-light">
  @include('partials.super-admin-topbar')
  <main class="main">
    <div class="main-content py-4">
      <div class="container-fluid" style="max-width: 1200px;">
        @if (session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif
        @if (session('error'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif
        @yield('content')
      </div>
    </div>
  </main>
  @include('partials.vendor-scripts')
  @include('partials.toast-container')
  @yield('js')
</body>
</html>
