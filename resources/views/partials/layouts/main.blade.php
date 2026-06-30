<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', config('app.name'))</title>
  <meta name="description" content="@yield('meta-description', config('app.name') . ' - Admin')">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link href="{{ asset('template/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

  @yield('css')
  @include('partials.head-css')
</head>

@php
  $sidebarPanelCollapsedByDefault = request()->routeIs('agenda.*')
    || (request()->routeIs('dashboard*') && (auth()->user()?->isPrestador() || auth()->user()?->isRececao()));
@endphp
<body class="{{ $sidebarPanelCollapsedByDefault ? 'sidebar-panel-collapsed' : '' }} {{ request()->routeIs('agenda.index') ? 'page-agenda' : '' }} {{ request()->routeIs('relatorios.*') ? 'page-relatorios' : '' }} {{ request()->routeIs('definicoes.*') ? 'definicoes-sidebar-open' : '' }}">
  @include('partials.header')
  @include('partials.sidebar')

  <!-- Main Content -->
  <main class="main">
    <div class="main-content">
      @include('partials.page-title')

      @yield('content')
    </div>
  </main>

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
  @if(!empty($cashRegisterCanManage))
    @include('partials.cash-register-modals')
    <script>
      window.CrmCashRegister = {
        isOpen: @json(!empty($cashRegisterSession)),
        openUrl: @json(route('caixa.open')),
        openPreviewUrl: @json(route('caixa.open.preview')),
        closeUrl: @json(route('caixa.close.store')),
        summaryUrl: @json(route('caixa.close.summary')),
      };
    </script>
    <script src="{{ asset('template/js/cash-register.js') }}?v={{ file_exists(public_path('template/js/cash-register.js')) ? filemtime(public_path('template/js/cash-register.js')) : time() }}"></script>
  @endif
  @yield('js')
</body>

</html>
