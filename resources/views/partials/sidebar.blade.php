<!-- Sidebar -->
<aside class="sidebar">
  <!-- Icon Bar (Left narrow panel) -->
  <div class="sidebar-iconbar">
    <div class="sidebar-iconbar-logo">
      <a href="{{ route('dashboard') }}">
        <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}">
      </a>
    </div>

    <nav class="sidebar-iconbar-nav">
      <ul class="iconbar-menu">
        <li>
          <a href="{{ route('dashboard') }}" class="iconbar-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" data-panel="dashboard" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
            <i class="ph ph-house"></i>
          </a>
        </li>
        <li>
          <a href="{{ route('agenda.index') }}" class="iconbar-item {{ request()->routeIs('agenda.*') ? 'active' : '' }}" data-panel="agenda" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Agenda" aria-label="Agenda">
            <i class="ph ph-calendar-blank"></i>
          </a>
        </li>
        <li>
          <button class="iconbar-item" data-panel="sales" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Vendas" aria-label="Vendas">
            <i class="ph ph-tag"></i>
          </button>
        </li>
        <li>
          <a href="{{ route('clientes.index') }}" class="iconbar-item {{ request()->routeIs('clientes.*') ? 'active' : '' }}" data-panel="clientes" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Clientes" aria-label="Clientes">
            <i class="ph ph-smiley"></i>
          </a>
        </li>
        <li>
          <a href="{{ route('services.index') }}" class="iconbar-item {{ request()->routeIs('services.*') || request()->routeIs('categories.*') || request()->routeIs('extras.*') ? 'active' : '' }}" data-panel="catalogue" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Catálogo" aria-label="Catálogo">
            <i class="ph ph-book-open"></i>
          </a>
        </li>
        <li>
          <button class="iconbar-item" data-panel="marketing" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Marketing" aria-label="Marketing">
            <i class="ph ph-megaphone"></i>
          </button>
        </li>
        <li>
          <a href="{{ route('equipa.index') }}" class="iconbar-item {{ request()->routeIs('equipa.*') ? 'active' : '' }}" data-panel="agentes" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Equipa" aria-label="Equipa">
            <i class="ph ph-users"></i>
          </a>
        </li>
        <li>
          <button class="iconbar-item" data-panel="reports" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Relatórios" aria-label="Relatórios">
            <i class="ph ph-chart-line-up"></i>
          </button>
        </li>
      </ul>
    </nav>

    <div class="sidebar-iconbar-bottom">
      <a href="#!" class="iconbar-item iconbar-bottom-item" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Definições">
        <i class="ph ph-gear"></i>
      </a>
      <a href="{{ route('dashboard') }}" class="iconbar-bottom-avatar" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
        <img src="{{ asset('template/img/profile-img.webp') }}" alt="User">
      </a>
    </div>
  </div>

  <!-- Nav Panel -->
  <div class="sidebar-panel">

    <div class="sidebar-panel-section {{ request()->routeIs('dashboard*') ? 'active' : '' }}" data-section="dashboard">
      <div class="sidebar-panel-header">
        <h6>Dashboard</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard') && !request()->routeIs('dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            Marcações e Serviços
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.clientes') ? 'active' : '' }}" href="{{ route('dashboard.clientes') }}">
            Clientes
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.ocupacao') ? 'active' : '' }}" href="{{ route('dashboard.ocupacao') }}">
            Ocupação
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section" data-section="sales">
      <div class="sidebar-panel-header">
        <h6>Vendas</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link" href="#!">
            Resumo diário
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Vendas
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Pagamentos
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
        <!-- Botão \"Novo evento\" removido da sidebar da agenda -->
      </ul>
    </div>

    <div class="sidebar-panel-section {{ request()->routeIs('services.*') || request()->routeIs('categories.*') || request()->routeIs('extras.*') ? 'active' : '' }}" data-section="catalogue">
      <div class="sidebar-panel-header">
        <h6>Catálogo</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('services.index') ? 'active' : '' }}" href="{{ route('services.index') }}">
            Serviços
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('services.tecnicos') ? 'active' : '' }}" href="{{ route('services.tecnicos') }}">
            Serviços por equipa
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('extras.*') ? 'active' : '' }}" href="{{ route('extras.index') }}">
            Extras / Add-ons
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Pacotes
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Recursos
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Profissionais
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
            Lista de clientes
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('clientes.create') ? 'active' : '' }}" href="{{ route('clientes.create') }}">
            Novo cliente
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.clientes') ? 'active' : '' }}" href="{{ route('dashboard.clientes') }}">
            Estatísticas
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section" data-section="marketing">
      <div class="sidebar-panel-header">
        <h6>Marketing</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link" href="#!">
            Campanhas SMS
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Histórico de campanhas
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section {{ request()->routeIs('equipa.*') ? 'active' : '' }}" data-section="agentes">
      <div class="sidebar-panel-header">
        <h6>Equipa</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('equipa.index') ? 'active' : '' }}" href="{{ route('equipa.index') }}">
            Membros
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('equipa.create') ? 'active' : '' }}" href="{{ route('equipa.create') }}">
            Novo membro
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Folhas de ponto
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Pagamentos
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-panel-section" data-section="reports">
      <div class="sidebar-panel-header">
        <h6>Relatórios</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link" href="#!">
            Todos os relatórios
          </a>
        </li>
        <li>
          <a class="panel-link" href="#!">
            Favoritos
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay"></div>