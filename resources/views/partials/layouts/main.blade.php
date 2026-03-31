<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', config('app.name'))</title>
  <meta name="description" content="@yield('meta-description', config('app.name') . ' - Admin')">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <!-- Favicons -->
  <link href="{{ asset('template/img/favicon.png') }}" rel="icon">
  <link href="{{ asset('template/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

  @yield('css')
  @include('partials.head-css')
</head>

<body class="{{ request()->routeIs('agenda.*') ? 'sidebar-panel-collapsed' : '' }} {{ request()->routeIs('definicoes.*') ? 'definicoes-sidebar-open' : '' }}">
  @include('partials.header')
  @include('partials.sidebar')

  <!-- Main Content -->
  <main class="main">
    <div class="main-content">
      @include('partials.page-title')

      @yield('content')
    </div>
  </main>

  <!-- Back to Top -->
  <a href="#" class="back-to-top" aria-label="Back to top">
    <i class="bi bi-arrow-up"></i>
  </a>

  @include('partials.vendor-scripts')
  <script>
    window.CrmNotifications = {
      listUrl: @json(route('notifications.api')),
      readAllUrl: @json(route('notifications.read-all')),
    };
  </script>
  <script src="{{ asset('template/js/crm-notifications.js') }}?v={{ file_exists(public_path('template/js/crm-notifications.js')) ? filemtime(public_path('template/js/crm-notifications.js')) : time() }}"></script>
  <script src="{{ asset('template/js/crm-flatpickr.js') }}?v={{ file_exists(public_path('template/js/crm-flatpickr.js')) ? filemtime(public_path('template/js/crm-flatpickr.js')) : time() }}"></script>
  @include('partials.toast-container')
  @yield('js')
</body>

</html>
