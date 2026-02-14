<!-- Sidebar -->
<aside class="sidebar">
  <!-- Icon Bar (Left narrow panel) -->
  <div class="sidebar-iconbar">
    <div class="sidebar-iconbar-logo">
      <a href="{{ route('dashboard') }}">
        <img src="{{ asset('template/img/logo.webp') }}" alt="{{ config('app.name') }}">
      </a>
    </div>

    <nav class="sidebar-iconbar-nav">
      <ul class="iconbar-menu">
        <li>
          <a href="#!" class="iconbar-item" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
            <i class="ph-duotone ph-house"></i>
          </a>
        </li>
        <li>
          <a href="{{ route('agenda.index') }}" class="iconbar-item {{ request()->routeIs('agenda.*') ? 'active' : '' }}" data-panel="agenda" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Agenda" aria-label="Agenda">
            <i class="ph-duotone ph-calendar-blank"></i>
          </a>
        </li>
        <li>
          <a href="{{ route('clientes.index') }}" class="iconbar-item {{ request()->routeIs('clientes.*') ? 'active' : '' }}" data-panel="clientes" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Clientes" aria-label="Clientes">
            <i class="ph-duotone ph-smiley"></i>
          </a>
        </li>
        <li>
          <button class="iconbar-item" data-panel="catalogue" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Catálogo" aria-label="Catálogo">
            <i class="ph-duotone ph-book-open"></i>
          </button>
        </li>
        <li>
          <a href="{{ route('agentes.index') }}" class="iconbar-item {{ request()->routeIs('agentes.*') ? 'active' : '' }}" data-panel="agentes" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Equipa" aria-label="Equipa">
            <i class="ph-duotone ph-users"></i>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-iconbar-bottom">
      <a href="{{ route('dashboard') }}" class="iconbar-bottom-avatar" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
        <img src="{{ asset('template/img/profile-img.webp') }}" alt="User">
      </a>
    </div>
  </div>

  <!-- Nav Panel -->
  <div class="sidebar-panel">
    <div class="sidebar-panel-section" data-section="catalogue">
      <div class="sidebar-panel-header">
        <h6>Catálogo</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            Serviços
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section {{ request()->routeIs('agenda.*') ? 'active' : '' }}" data-section="agenda">
      <div class="sidebar-panel-header">
        <h6>Agenda</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('agenda.index') ? 'active' : '' }}" href="{{ route('agenda.index') }}">
            Ver agenda
          </a>
        </li>
        <li>
          <a class="panel-link agenda-sidebar-novo-evento" href="#" data-agenda-novo-evento>
            Novo evento
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section {{ request()->routeIs('clientes.*') ? 'active' : '' }}" data-section="clientes">
      <div class="sidebar-panel-header">
        <h6>Clientes</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('clientes.index') ? 'active' : '' }}" href="{{ route('clientes.index') }}">
            Ver clientes
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('clientes.create') ? 'active' : '' }}" href="{{ route('clientes.create') }}">
            Novo cliente
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section {{ request()->routeIs('agentes.*') ? 'active' : '' }}" data-section="agentes">
      <div class="sidebar-panel-header">
        <h6>Equipa</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('agentes.index') ? 'active' : '' }}" href="{{ route('agentes.index') }}">
            Ver equipa
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('agentes.create') ? 'active' : '' }}" href="{{ route('agentes.create') }}">
            Novo membro
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Horários
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay"></div>