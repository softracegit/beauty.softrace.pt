<!-- Header -->
@php
  $navUser = auth()->user();
  $crmPrivacyLock = app(\App\Support\CrmPrivacyLock::class);
  $crmPrivacyLockConfigured = $crmPrivacyLock->isConfigured();
  $crmPrivacyLocked = $crmPrivacyLock->isActive();
  $crmPrivacyIdleMinutes = \App\Models\CrmSetting::privacyLockIdleMinutes((int) current_store_id());
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
    @if(!$crmPrivacyLocked)
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
    @endif
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
    @if(!empty($cashRegisterCanManage) && !$crmPrivacyLocked)
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
    @if($crmPrivacyLockConfigured)
    <button
      type="button"
      class="header-action{{ $crmPrivacyLocked ? ' header-crm-privacy-lock-btn--locked' : '' }}"
      id="crmPrivacyLockToggle"
      title="{{ $crmPrivacyLocked ? 'Desbloquear CRM' : 'Bloquear CRM' }}"
      aria-label="{{ $crmPrivacyLocked ? 'Desbloquear CRM' : 'Bloquear CRM' }}"
    >
      <i class="bi {{ $crmPrivacyLocked ? 'bi-lock-fill text-danger' : 'bi-unlock' }}" id="crmPrivacyLockToggleIcon"></i>
    </button>
    @endif

    <button type="button" class="header-action d-none d-md-flex" id="headerFullscreenBtn" title="Ecrã inteiro" aria-label="Ecrã inteiro">
      <i class="bi bi-fullscreen" id="headerFullscreenIcon"></i>
    </button>

    <button class="header-action theme-toggle d-none d-md-flex" title="Toggle Theme">
      <i class="ph ph-moon theme-icon-dark"></i>
      <i class="ph ph-sun theme-icon-light"></i>
    </button>

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
          <a href="{{ route('notifications.index') }}" id="headerNotificationViewAll">Ver todas as notificações <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>

    <!-- User Dropdown -->
    <div class="header-action dropdown user-dropdown d-none d-md-flex">
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
          @if($authUser->canAccessDashboard())
          <a class="dropdown-item" href="{{ route($authUser->backofficeHomeRoute()) }}">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
          @endif
          @if(!$crmPrivacyLocked && $authUser->canAccessDefinicoes())
          <a class="dropdown-item" href="{{ route('definicoes.index') }}">
            <i class="ph ph-gear"></i> Definições
          </a>
          @endif
          @if(!$crmPrivacyLocked && $authUser->canAccessRoute('activity.index'))
          <a class="dropdown-item" href="{{ route('activity.index') }}">
            <i class="ph ph-clock-counter-clockwise"></i> Activity Log
          </a>
          @endif
          <a class="dropdown-item" href="{{ route('agenda.index') }}">
            <i class="ph ph-calendar-blank"></i> Agenda
          </a>
        </div>
        @if(!$crmPrivacyLocked)
        <div class="user-dropdown-footer">
          <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="dropdown-item dropdown-item-danger w-100 text-start border-0 bg-transparent">
              <i class="bi bi-box-arrow-right"></i> Terminar sessão
            </button>
          </form>
        </div>
        @endif
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
    @if(!$crmPrivacyLocked && $navUser->canAccessDefinicoes())
    <a href="{{ route('definicoes.index') }}" class="mobile-menu-item">
      <i class="ph ph-gear"></i>
      <span class="mobile-menu-label">Definições</span>
    </a>
    @endif
    @if(!$crmPrivacyLocked && $navUser->canAccessRoute('activity.index'))
    <a href="{{ route('activity.index') }}" class="mobile-menu-item">
      <i class="ph ph-clock-counter-clockwise"></i>
      <span class="mobile-menu-label">Activity Log</span>
    </a>
    @endif
    @if(!$crmPrivacyLocked)
    <form method="POST" action="{{ route('logout') }}" class="mobile-menu-logout-form">
      @csrf
      <button type="submit" class="mobile-menu-item mobile-menu-item-danger border-0 bg-transparent">
        <i class="bi bi-box-arrow-right"></i>
        <span class="mobile-menu-label">Terminar sessão</span>
      </button>
    </form>
    @endif
  </div>
</div>

@if($crmPrivacyLockConfigured)
<div class="modal fade" id="crmPrivacyUnlockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desbloquear CRM</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="otp-input-group crm-privacy-pin-inputs my-2" role="group" aria-label="PIN de 4 dígitos">
          @for ($i = 0; $i < 4; $i++)
          <input
            type="password"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="1"
            class="form-control otp-input crm-privacy-pin-digit"
            data-idx="{{ $i }}"
            placeholder="*"
            @if($i === 0) autofocus @endif
            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="{{ $i === 3 ? 'done' : 'next' }}"
          >
          @endfor
        </div>
        <div class="text-center mt-3">
          @if(!$crmPrivacyLocked && $navUser->canAccessDefinicoes())
          <a href="{{ route('definicoes.negocio') }}#privacy-lock-pin" class="small text-muted">Não se lembra do PIN?</a>
          @else
          <a href="#" class="small text-muted" id="crmPrivacyPinForgotLink">Não se lembra do PIN?</a>
          @endif
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="crmPrivacyUnlockSubmit">Desbloquear</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="crmPrivacyPinForgotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Recuperar PIN</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">O PIN é guardado de forma segura e <strong>não pode ser consultado</strong> depois de definido.</p>
        <p class="mb-0">Peça a um responsável com acesso às definições para definir um <strong>novo PIN</strong> em <strong>Definições → Negócio → Privacidade no posto</strong>.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
      </div>
    </div>
  </div>
</div>

<style>
  .crm-privacy-pin-inputs .otp-input {
    width: 56px !important;
    height: 64px !important;
    font-size: 1.75rem;
    letter-spacing: 0.05em;
  }
  .crm-privacy-pin-inputs .otp-input::placeholder {
    color: var(--muted-color);
    opacity: 0.65;
  }
  @media (max-width: 575.98px) {
    .crm-privacy-pin-inputs .otp-input {
      width: 48px !important;
      height: 56px !important;
      font-size: 1.5rem;
    }
  }
</style>
@endif

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
@if($crmPrivacyLockConfigured)
<script>
(function () {
  var lockState = {
    locked: @json($crmPrivacyLocked),
    idleMinutes: @json($crmPrivacyIdleMinutes),
    statusUrl: @json(route('crm-privacy-lock.status')),
    lockUrl: @json(route('crm-privacy-lock.lock')),
    unlockUrl: @json(route('crm-privacy-lock.unlock'))
  };
  var lockBtn = document.getElementById('crmPrivacyLockToggle');
  var lockIcon = document.getElementById('crmPrivacyLockToggleIcon');
  var unlockModalEl = document.getElementById('crmPrivacyUnlockModal');
  var unlockModal = null;
  var pinForgotModalEl = document.getElementById('crmPrivacyPinForgotModal');
  var pinForgotModal = null;
  var pinDigits = Array.prototype.slice.call(document.querySelectorAll('.crm-privacy-pin-digit'));
  var unlockSubmit = document.getElementById('crmPrivacyUnlockSubmit');
  var pinForgotLink = document.getElementById('crmPrivacyPinForgotLink');
  var idleTimer = null;
  var idleEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function toast(message, type) {
    if (typeof window.showToast === 'function') window.showToast(message, type || 'info');
  }

  function updateLockUi() {
    if (!lockBtn) return;
    var title = lockState.locked ? 'Desbloquear CRM' : 'Bloquear CRM';
    lockBtn.title = title;
    lockBtn.setAttribute('aria-label', title);
    lockBtn.classList.toggle('header-crm-privacy-lock-btn--locked', lockState.locked);
    var iconClass = lockState.locked ? 'bi bi-lock-fill text-danger' : 'bi bi-unlock';
    if (lockIcon) lockIcon.className = iconClass;
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
  }

  function refreshStatus() {
    return fetch(lockState.statusUrl, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        lockState.locked = !!(data && data.locked);
        updateLockUi();
      });
  }

  function getPinValue() {
    return pinDigits.map(function (el) { return String(el.value || '').replace(/\D/g, ''); }).join('');
  }

  function clearPinInputs() {
    pinDigits.forEach(function (el) { el.value = ''; });
  }

  function focusPinDigit(index) {
    var el = pinDigits[index];
    if (el) {
      el.focus();
      el.select();
    }
  }

  function fillPinFromString(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 4);
    pinDigits.forEach(function (el, idx) {
      el.value = digits.charAt(idx) || '';
    });
    if (digits.length >= 4) focusPinDigit(3);
    else focusPinDigit(digits.length);
  }

  function setupPinInputs() {
    pinDigits.forEach(function (input, idx) {
      input.addEventListener('input', function () {
        var digit = String(input.value || '').replace(/\D/g, '').slice(-1);
        input.value = digit;
        if (digit && idx < pinDigits.length - 1) focusPinDigit(idx + 1);
        if (getPinValue().length === 4) unlockNow();
      });
      input.addEventListener('keydown', function (ev) {
        if (ev.key === 'Backspace' && !input.value && idx > 0) {
          ev.preventDefault();
          focusPinDigit(idx - 1);
        }
        if (ev.key === 'Enter') unlockNow();
      });
      input.addEventListener('paste', function (ev) {
        ev.preventDefault();
        var pasted = (ev.clipboardData || window.clipboardData)?.getData('text') || '';
        fillPinFromString(pasted);
        if (getPinValue().length === 4) unlockNow();
      });
    });
  }

  function openUnlockModal() {
    if (!unlockModal && unlockModalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
      unlockModal = new window.bootstrap.Modal(unlockModalEl);
    }
    if (!unlockModal) return;
    clearPinInputs();
    unlockModal.show();
  }

  if (unlockModalEl && !unlockModalEl.dataset.pinFocusBound) {
    unlockModalEl.dataset.pinFocusBound = '1';
    unlockModalEl.addEventListener('shown.bs.modal', function () {
      window.setTimeout(function () { focusPinDigit(0); }, 0);
    });
  }

  function openPinForgotModal() {
    if (!pinForgotModal && pinForgotModalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
      pinForgotModal = new window.bootstrap.Modal(pinForgotModalEl);
    }
    pinForgotModal?.show();
  }

  function lockNow(manual) {
    return postJson(lockState.lockUrl).then(function (res) {
      if (!res.ok) return;
      lockState.locked = true;
      updateLockUi();
      if (manual) toast('CRM bloqueado neste posto.', 'success');
      window.location.href = @json(route('agenda.index'));
    });
  }

  function unlockNow() {
    var pin = getPinValue();
    if (!/^\d{4}$/.test(pin)) {
      toast('Indique um PIN válido de 4 dígitos.', 'warning');
      return;
    }
    unlockSubmit.disabled = true;
    postJson(lockState.unlockUrl, { pin: pin }).then(function (res) {
      unlockSubmit.disabled = false;
      if (!res.ok) {
        toast(res.data?.message || 'Não foi possível desbloquear.', 'danger');
        clearPinInputs();
        focusPinDigit(0);
        return;
      }
      lockState.locked = false;
      updateLockUi();
      unlockModal?.hide();
      toast('CRM desbloqueado.', 'success');
      window.location.reload();
    });
  }

  function scheduleIdleLock() {
    if (!lockState.idleMinutes || lockState.idleMinutes <= 0) return;
    if (lockState.locked) return;
    if (idleTimer) window.clearTimeout(idleTimer);
    idleTimer = window.setTimeout(function () { lockNow(false); }, lockState.idleMinutes * 60 * 1000);
  }

  lockBtn?.addEventListener('click', function () {
    if (lockState.locked) openUnlockModal();
    else lockNow(true);
  });
  unlockSubmit?.addEventListener('click', unlockNow);
  pinForgotLink?.addEventListener('click', function (ev) {
    ev.preventDefault();
    openPinForgotModal();
  });
  setupPinInputs();
  idleEvents.forEach(function (eventName) {
    document.addEventListener(eventName, scheduleIdleLock, { passive: true });
  });

  refreshStatus().finally(function () {
    updateLockUi();
    scheduleIdleLock();
  });
})();
</script>
@endif
