<header class="header border-bottom d-flex align-items-center justify-content-between flex-wrap bg-white">
  <div class="header-left px-3 py-2 d-flex align-items-center gap-3 flex-wrap">
    <a href="{{ route('super-admin.dashboard') }}" class="header-logo d-inline-flex align-items-center gap-2 text-decoration-none text-body">
      <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="" width="32" height="32">
      <span class="fw-semibold">Super Admin</span>
    </a>
    <nav class="d-flex align-items-center gap-2 small">
      <a href="{{ route('super-admin.organizations.index') }}" class="text-decoration-none {{ request()->routeIs('super-admin.organizations*') ? 'fw-bold text-primary' : 'text-body-secondary' }}">
        Organizações
      </a>
    </nav>
  </div>
  <div class="header-right px-3 py-2">
    <form method="POST" action="{{ route('logout') }}" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm btn-outline-secondary">Sair</button>
    </form>
  </div>
</header>
