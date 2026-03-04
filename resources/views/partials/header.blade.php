<!-- Header -->
<header class="header">
  <!-- Header Left -->
  <div class="header-left">
    <a href="{{ route('dashboard') }}" class="header-logo">
      <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}">
      <span>{{ config('app.name') }}</span>
    </a>
    <!--<button class="sidebar-toggle" title="Toggle Sidebar">
      <i class="bi bi-list"></i>
    </button>-->
    <!-- Quick Access -->
    <div class="header-action dropdown quickaccess-dropdown">
      <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Quick Access">
        <i class="bi bi-grid"></i>
      </button>
      <div class="dropdown-menu">
        <div class="quickaccess-header">
          <h6>Quick Access</h6>
          <span class="quickaccess-subtitle">Frequently used</span>
        </div>
        <div class="quickaccess-grid">
          <a href="{{ route('dashboard') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="bi bi-speedometer2"></i>
            </span>
            <span class="quickaccess-label">Dashboard</span>
          </a>
          <a href="{{ route('leads.kanban') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--success-color)">
              <i class="bi bi-kanban"></i>
            </span>
            <span class="quickaccess-label">Leads</span>
          </a>
          <a href="{{ route('opportunities.kanban') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--info-color)">
              <i class="bi bi-briefcase"></i>
            </span>
            <span class="quickaccess-label">Oportunidades</span>
          </a>
          <a href="{{ route('agenda.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: #ec4899">
              <i class="bi bi-calendar3"></i>
            </span>
            <span class="quickaccess-label">Agenda</span>
          </a>
          <a href="{{ route('clientes.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--danger-color)">
              <i class="bi bi-people"></i>
            </span>
            <span class="quickaccess-label">Clientes</span>
          </a>
          <a href="{{ route('properties.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: #8b5cf6">
              <i class="bi bi-house-door"></i>
            </span>
            <span class="quickaccess-label">Imóveis</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Header Search -->
  <div class="header-search">
    <form class="search-form" action="{{ url('/') }}" method="GET">
      <i class="bi bi-search search-icon"></i>
      <input type="search" name="q" placeholder="Pesquisar..." autocomplete="off">
      <kbd class="search-shortcut">/</kbd>
    </form>
  </div>

  <!-- Header Right -->
  <div class="header-right">
    <div class="header-actions-desktop">
      <!-- Notifications -->
      <div class="header-action dropdown notification-dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações">
          <i class="bi bi-bell"></i>
          <span class="badge d-none" id="headerNotificationBadge">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <div class="notification-header">
            <div class="notification-header-left">
              <h6>Notificações</h6>
              <span class="notification-count d-none" id="headerNotificationCount">0 new</span>
            </div>
            <a href="#" class="notification-mark-read d-none" id="headerNotificationMarkRead" data-notification-action="mark-all-read">
              <i class="bi bi-check2-all"></i> Marcar todas como lidas
            </a>
          </div>
          <div class="notification-list">
            <div class="notification-empty text-muted small text-center py-4 px-3" id="headerNotificationEmpty">
              Nenhuma notificação.
            </div>
            <div id="headerNotificationItems" class="d-none">
              {{-- Items dinâmicos ou estáticos podem ser injetados aqui --}}
            </div>
          </div>
          <div class="notification-footer">
            <a href="#" id="headerNotificationViewAll">Ver todas as notificações <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>

      <!-- Fullscreen -->
      <button type="button" class="header-action" id="headerFullscreenBtn" title="Ecrã inteiro" aria-label="Ecrã inteiro">
        <i class="bi bi-fullscreen" id="headerFullscreenIcon"></i>
      </button>

      <!-- Theme Toggle -->
      <button class="header-action theme-toggle" title="Toggle Theme">
        <i class="ph ph-moon theme-icon-dark"></i>
        <i class="ph ph-sun theme-icon-light"></i>
      </button>

      <!-- User Dropdown -->
      <div class="header-action dropdown user-dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="{{ asset('template/img/profile-img.webp') }}" alt="User" class="avatar">
          <span class="avatar-status"></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <div class="user-dropdown-header">
            <img src="{{ asset('template/img/profile-img.webp') }}" alt="User" class="user-dropdown-avatar">
            <div class="user-dropdown-info">
              <h6>{{ auth()->user()->name ?? 'User' }}</h6>
              <span>{{ auth()->user()->email ?? '' }}</span>
            </div>
          </div>
          <div class="user-dropdown-body">
            <a class="dropdown-item" href="{{ route('dashboard') }}">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
          </div>
          <div class="user-dropdown-footer">
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
              @csrf
              <button type="submit" class="dropdown-item dropdown-item-danger w-100 text-start border-0 bg-transparent">
                <i class="bi bi-box-arrow-right"></i> Terminar sessão
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="header-actions-mobile">
      <button class="header-action search-toggle" title="Search">
        <i class="bi bi-search"></i>
      </button>
      <button class="header-action mobile-menu-toggle" title="More">
        <i class="bi bi-three-dots-vertical"></i>
      </button>
    </div>
  </div>
</header>

<!-- Mobile Search -->
<div class="mobile-search">
  <form class="search-form" action="{{ url('/') }}" method="GET">
    <i class="bi bi-search search-icon"></i>
    <input type="search" name="q" placeholder="Pesquisar..." autocomplete="off">
  </form>
</div>

<!-- Mobile Header Menu -->
<div class="mobile-header-menu">
  <div class="mobile-header-menu-content">
    <button class="mobile-menu-item theme-toggle" title="Toggle Theme">
      <i class="ph ph-moon theme-icon-dark"></i>
      <i class="ph ph-sun theme-icon-light"></i>
      <span class="mobile-menu-label">Tema</span>
    </button>
    <button type="button" class="mobile-menu-item" id="mobileFullscreenBtn" title="Ecrã inteiro">
      <i class="bi bi-fullscreen"></i>
      <span class="mobile-menu-label">Ecrã inteiro</span>
    </button>
    <a href="{{ route('dashboard') }}" class="mobile-menu-item">
      <i class="bi bi-speedometer2"></i>
      <span class="mobile-menu-label">Dashboard</span>
    </a>
    <a href="{{ route('agenda.index') }}" class="mobile-menu-item">
      <i class="bi bi-calendar3"></i>
      <span class="mobile-menu-label">Agenda</span>
    </a>
    <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
      @csrf
      <button type="submit" class="mobile-menu-item mobile-menu-item-danger w-100 text-start border-0 bg-transparent">
        <i class="bi bi-box-arrow-right"></i>
        <span class="mobile-menu-label">Terminar sessão</span>
      </button>
    </form>
  </div>
</div>

<script>
(function() {
  function updateFullscreenIcon() {
    var isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
    var icon = document.getElementById('headerFullscreenIcon');
    var mobileBtn = document.getElementById('mobileFullscreenBtn');
    if (icon) {
      icon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
    }
    if (mobileBtn) {
      var mobileIcon = mobileBtn.querySelector('i.bi');
      if (mobileIcon) mobileIcon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
      var label = mobileBtn.querySelector('.mobile-menu-label');
      if (label) label.textContent = isFullscreen ? 'Sair de ecrã inteiro' : 'Ecrã inteiro';
    }
  }
  function toggleFullscreen() {
    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
      var doc = document.documentElement;
      if (doc.requestFullscreen) doc.requestFullscreen();
      else if (doc.webkitRequestFullscreen) doc.webkitRequestFullscreen();
      else if (doc.msRequestFullscreen) doc.msRequestFullscreen();
    } else {
      if (document.exitFullscreen) document.exitFullscreen();
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
      else if (document.msExitFullscreen) document.msExitFullscreen();
    }
  }
  document.getElementById('headerFullscreenBtn')?.addEventListener('click', toggleFullscreen);
  document.getElementById('mobileFullscreenBtn')?.addEventListener('click', function() {
    toggleFullscreen();
    document.querySelector('.mobile-header-menu')?.classList.remove('show');
    document.querySelector('.mobile-menu-toggle')?.click();
  });
  document.addEventListener('fullscreenchange', updateFullscreenIcon);
  document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
  document.addEventListener('MSFullscreenChange', updateFullscreenIcon);
})();
</script>
