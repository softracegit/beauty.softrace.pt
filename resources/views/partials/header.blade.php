<!-- Header -->
@php
  $navUser = auth()->user();
@endphp
<header class="header">
  <!-- Header Left -->
  <div class="header-left">
    <a href="{{ route($navUser->backofficeHomeRoute()) }}" class="header-logo">
      <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}">
      <span>{{ config('app.name') }}</span>
    </a>
    <button class="sidebar-toggle" title="Toggle Sidebar">
      <i class="bi bi-list"></i>
    </button>
    <!-- Quick Access -->
    <div class="header-action dropdown quickaccess-dropdown">
      <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Acesso rápido">
        <i class="bi bi-grid"></i>
      </button>
      <div class="dropdown-menu">
        <div class="quickaccess-header">
          <h6>Acesso rápido</h6>
        </div>
        <div class="quickaccess-grid">
          @if($navUser->canAccessDashboard())
          <a href="{{ route('dashboard') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-house"></i>
            </span>
            <span class="quickaccess-label">Dashboard</span>
          </a>
          @endif
          <a href="{{ route('agenda.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-calendar-blank"></i>
            </span>
            <span class="quickaccess-label">Agenda</span>
          </a>
          @if(!$navUser->isPrestador())
          <a href="{{ route('agenda.index', ['novaMarcacao' => 1]) }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-calendar-plus"></i>
            </span>
            <span class="quickaccess-label">Nova marcação</span>
          </a>
          @endif
          @if($navUser->canAccessClientes())
          <a href="{{ route('clientes.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-smiley"></i>
            </span>
            <span class="quickaccess-label">Clientes</span>
          </a>
          @endif
          @if($navUser->canAccessCatalog())
          <a href="{{ route('services.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-book-open"></i>
            </span>
            <span class="quickaccess-label">Serviços</span>
          </a>
          @endif
          @if($navUser->canAccessEquipa())
          <a href="{{ route('equipa.index') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-users"></i>
            </span>
            <span class="quickaccess-label">Equipa</span>
          </a>
          @endif
          @if($navUser->canAccessRelatorios())
          <a href="{{ route('relatorios.vendas') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-chart-line-up"></i>
            </span>
            <span class="quickaccess-label">Vendas</span>
          </a>
          <a href="{{ route('relatorios.marcacoes') }}" class="quickaccess-item">
            <span class="quickaccess-icon" style="--qa-color: var(--accent-color)">
              <i class="ph ph-chart-line-up"></i>
            </span>
            <span class="quickaccess-label">Marcações</span>
          </a>
          @endif
        </div>
      </div>
    </div>
    @isset($activeStore, $selectableStores)
    @if($navUser->canSwitchStore())
    <div class="header-action dropdown store-selector-dropdown d-none">
      <button
        class="dropdown-toggle d-inline-flex align-items-center gap-1"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-display="static"
        aria-expanded="false"
        title="Loja activa"
      >
        <i class="ph ph-storefront" aria-hidden="true"></i>
        <span class="d-none d-xl-inline text-truncate" style="max-width: 12rem">{{ $activeStore->name }}</span>
      </button>
      <div class="dropdown-menu">
        <div class="px-3 py-2 small text-muted text-uppercase">Loja</div>
        @if ($selectableStores->count() > 1)
          @foreach ($selectableStores as $store)
            @if ((int) $store->id === (int) $activeStore->id)
              <span class="dropdown-item active d-flex align-items-center gap-2" aria-current="true">
                <i class="ph ph-check fw-bold" aria-hidden="true"></i>
                <span class="text-truncate">{{ $store->name }}</span>
              </span>
            @else
              <form method="POST" action="{{ route('current-store.update') }}" class="m-0">
                @csrf
                <input type="hidden" name="store_id" value="{{ $store->id }}">
                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 text-start border-0 bg-transparent">
                  <span class="visually-hidden">Mudar para </span>
                  <span class="text-truncate">{{ $store->name }}</span>
                </button>
              </form>
            @endif
          @endforeach
        @else
          <div class="dropdown-item-text small text-body-secondary text-truncate">{{ $activeStore->name }}</div>
        @endif
      </div>
    </div>
    @endif
    @endisset
    @if(!empty($cashRegisterCanManage))
    @php
      $cashRegisterIsOpen = ! empty($cashRegisterSession);
    @endphp
    <button
      type="button"
      class="header-action header-cash-register-btn {{ $cashRegisterIsOpen ? 'header-cash-register-btn--open' : 'header-cash-register-btn--closed' }}"
      data-crm-cash-register-trigger="{{ $cashRegisterIsOpen ? 'close' : 'open' }}"
      data-crm-cash-register-title-open="Caixa fechada — abrir caixa"
      data-crm-cash-register-title-closed="Caixa aberta — fechar o dia"
      title="{{ $cashRegisterIsOpen ? 'Caixa aberta — fechar o dia' : 'Caixa fechada — abrir caixa' }}"
      aria-label="{{ $cashRegisterIsOpen ? 'Caixa aberta — fechar o dia' : 'Caixa fechada — abrir caixa' }}"
    >
      <i class="ph ph-cash-register" aria-hidden="true"></i>
    </button>
    @endif
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
    <!-- Notifications (desktop + mobile) -->
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
          <a href="{{ route('notifications.index') }}" id="headerNotificationViewAll">Ver todas as notificações <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>

    <div class="header-actions-desktop">
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
        @php
          $authUser = auth()->user();
          $authAgent = $authUser?->agent;
          if ($authAgent && $authAgent->avatar) {
              $userAvatar = asset('storage/' . $authAgent->avatar);
          } else {
              $avatarNum = $authUser ? (($authUser->id % 9) + 1) : 1;
              $userAvatar = asset("template/img/avatars/avatar-{$avatarNum}.webp");
          }
        @endphp
        <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="{{ $userAvatar }}" alt="{{ $authUser->name ?? 'User' }}" class="avatar">
          <span class="avatar-status"></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <div class="user-dropdown-header">
            <img src="{{ $userAvatar }}" alt="{{ $authUser->name ?? 'User' }}" class="user-dropdown-avatar">
            <div class="user-dropdown-info">
              <h6>{{ $authUser->name ?? 'User' }}</h6>
              <span>{{ $authUser->email ?? '' }}</span>
            </div>
          </div>
          <div class="user-dropdown-body">
            <a class="dropdown-item" href="{{ route($authUser->backofficeHomeRoute()) }}">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            @if($authUser->canAccessDefinicoes())
            <a class="dropdown-item" href="{{ route('definicoes.index') }}">
              <i class="ph ph-gear"></i> Definições
            </a>
            @endif
            @if($authUser->canAccessRoute('activity.index'))
            <a class="dropdown-item" href="{{ route('activity.index') }}">
              <i class="ph ph-clock-counter-clockwise"></i> Activity Log
            </a>
            @endif
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
      <button class="header-action mobile-menu-toggle" title="Mais opções" aria-label="Mais opções">
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
    @if($navUser->canAccessDashboard())
    <a href="{{ route('dashboard') }}" class="mobile-menu-item">
      <i class="bi bi-speedometer2"></i>
      <span class="mobile-menu-label">Dashboard</span>
    </a>
    @endif
    <a href="{{ route('agenda.index') }}" class="mobile-menu-item">
      <i class="bi bi-calendar3"></i>
      <span class="mobile-menu-label">Agenda</span>
    </a>
    <button class="mobile-menu-item theme-toggle" title="Toggle Theme">
      <i class="ph ph-moon theme-icon-dark"></i>
      <i class="ph ph-sun theme-icon-light"></i>
      <span class="mobile-menu-label">Tema</span>
    </button>
    @if($navUser->canAccessDefinicoes())
    <a href="{{ route('definicoes.index') }}" class="mobile-menu-item">
      <i class="ph ph-gear"></i>
      <span class="mobile-menu-label">Definições</span>
    </a>
    @endif
    @if($navUser->canAccessRoute('activity.index'))
    <a href="{{ route('activity.index') }}" class="mobile-menu-item">
      <i class="ph ph-clock-counter-clockwise"></i>
      <span class="mobile-menu-label">Activity Log</span>
    </a>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="mobile-menu-logout-form">
      @csrf
      <button type="submit" class="mobile-menu-item mobile-menu-item-danger border-0 bg-transparent">
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
    if (icon) {
      icon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
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
  document.addEventListener('fullscreenchange', updateFullscreenIcon);
  document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
  document.addEventListener('MSFullscreenChange', updateFullscreenIcon);
})();
</script>
