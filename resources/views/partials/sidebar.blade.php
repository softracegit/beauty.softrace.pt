@php
  $navUser = auth()->user();
  $crmPrivacyLocked = app(\App\Support\CrmPrivacyLock::class)->isActive();
@endphp
<!-- Sidebar -->
<aside class="sidebar">
  <!-- Icon Bar (Left narrow panel) -->
  <div class="sidebar-iconbar">
    <div class="sidebar-iconbar-logo">
      <a href="{{ route($navUser->backofficeHomeRoute()) }}">
        <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}">
      </a>
    </div>

    <nav class="sidebar-iconbar-nav">
      <ul class="iconbar-menu">
        @if($navUser->canAccessDashboard())
        <li>
          @if($navUser->isPrestador() || $navUser->isRececao() || $crmPrivacyLocked)
          <a href="{{ route('dashboard') }}" class="iconbar-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" data-panel="dashboard" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
            <i class="ph ph-house"></i>
          </a>
          @else
          <a href="#!" class="iconbar-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" data-panel="dashboard" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard">
            <i class="ph ph-house"></i>
          </a>
          @endif
        </li>
        @endif
        <li>
          <a href="{{ route('agenda.index') }}" class="iconbar-item {{ request()->routeIs('agenda.*') ? 'active' : '' }}" data-panel="agenda" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Agenda" aria-label="Agenda">
            <i class="ph ph-calendar-blank"></i>
          </a>
        </li>
        @if(!$crmPrivacyLocked && $navUser->canAccessClientes())
        <li>
          <a href="#!" class="iconbar-item {{ request()->routeIs('clientes.*') ? 'active' : '' }}" data-panel="clientes" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Clientes" aria-label="Clientes">
            <i class="ph ph-smiley"></i>
          </a>
        </li>
        @endif
        @if(!$crmPrivacyLocked && $navUser->canAccessCatalog())
        <li>
          <a href="#!" class="iconbar-item {{ request()->routeIs('services.*') || request()->routeIs('categories.*') || request()->routeIs('extras.*') || request()->routeIs('fees.*') ? 'active' : '' }}" data-panel="catalogue" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Catálogo" aria-label="Catálogo">
            <i class="ph ph-book-open"></i>
          </a>
        </li>
        @endif
        @if(!$crmPrivacyLocked && $navUser->canAccessMarketing())
        <li>
          <a href="#!" class="iconbar-item {{ request()->routeIs('marketing.*') ? 'active' : '' }}" data-panel="marketing" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Marketing" aria-label="Marketing">
            <i class="ph ph-megaphone"></i>
          </a>
        </li>
        @endif
        @if(!$crmPrivacyLocked && $navUser->canAccessEquipa())
        <li>
          <a href="#!" class="iconbar-item {{ request()->routeIs('equipa.*') ? 'active' : '' }}" data-panel="agentes" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Equipa" aria-label="Equipa">
            <i class="ph ph-users"></i>
          </a>
        </li>
        @endif
        @if(!$crmPrivacyLocked && $navUser->canAccessRelatorios())
        <li>
          <a href="#!" class="iconbar-item {{ request()->routeIs('relatorios.*') ? 'active' : '' }}" data-panel="reports" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Relatórios" aria-label="Relatórios">
            <i class="ph ph-chart-line-up"></i>
          </a>
        </li>
        @endif
        @if(!$crmPrivacyLocked && $navUser->canAccessAi())
        <li>
          <a href="{{ route('ai.index') }}" class="iconbar-item {{ request()->routeIs('ai.*') ? 'active' : '' }}" data-panel="ai" data-navigate-on-click data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Assistente AI" aria-label="Assistente AI">
            <i class="ph ph-sparkle"></i>
          </a>
        </li>
        @endif
      </ul>
    </nav>

    <div class="sidebar-iconbar-bottom">
      @if(!$crmPrivacyLocked && $navUser->canAccessDefinicoes())
      <a href="#!" class="iconbar-item iconbar-bottom-item {{ request()->routeIs('definicoes.*') || request()->routeIs('activity.*') ? 'active' : '' }}" data-panel="definicoes" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Definições" aria-label="Definições">
        <i class="ph ph-gear"></i>
      </a>
      @endif
      <a href="#!" class="iconbar-item iconbar-bottom-item" data-panel="ajuda" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Ajuda" aria-label="Ajuda">
        <i class="ph ph-question"></i>
      </a>
    </div>
  </div>

  <!-- Nav Panel -->
  <div class="sidebar-panel">

    @if($navUser->canAccessDashboard())
    <div class="sidebar-panel-section {{ request()->routeIs('dashboard*') ? 'active' : '' }}" data-section="dashboard">
      <div class="sidebar-panel-header">
        <h6>Dashboard</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        @if($navUser->isPrestador() || $navUser->isRececao() || $crmPrivacyLocked)
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            Resumo
          </a>
        </li>
        @else
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            Resumo
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.marcacoes') ? 'active' : '' }}" href="{{ route('dashboard.marcacoes') }}">
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
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.financeiro') ? 'active' : '' }}" href="{{ route('dashboard.financeiro') }}">
            Financeiro
          </a>
        </li>
        @endif
      </ul>
    </div>
    @endif

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
        @if(!$navUser->isPrestador())
        <li>
          <a class="panel-link {{ request()->routeIs('agenda.index') && request()->query('novaMarcacao') ? 'active' : '' }}" href="{{ route('agenda.index') }}?novaMarcacao=1">
            Nova marcação
          </a>
        </li>
        @endif
      </ul>
    </div>

    @if(!$crmPrivacyLocked && $navUser->canAccessCatalog())
    <div class="sidebar-panel-section {{ request()->routeIs('services.*') || request()->routeIs('categories.*') || request()->routeIs('extras.*') || request()->routeIs('fees.*') ? 'active' : '' }}" data-section="catalogue">
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
          <a class="panel-link {{ request()->routeIs('fees.*') ? 'active' : '' }}" href="{{ route('fees.index') }}">
            Taxas
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
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessClientes())
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
        @if(!$navUser->isRececao())
        <li>
          <a class="panel-link {{ request()->routeIs('dashboard.clientes') ? 'active' : '' }}" href="{{ route('dashboard.clientes') }}">
            Estatísticas
          </a>
        </li>
        @endif
      </ul>
    </div>
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessMarketing())
    <div class="sidebar-panel-section {{ request()->routeIs('marketing.*') ? 'active' : '' }}" data-section="marketing">
      <div class="sidebar-panel-header">
        <h6>Marketing</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('marketing.campanhas-sms') ? 'active' : '' }}" href="{{ route('marketing.campanhas-sms') }}">
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
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessEquipa())
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
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessRelatorios())
    <div class="sidebar-panel-section {{ request()->routeIs('relatorios.*') ? 'active' : '' }}" data-section="reports">
      <div class="sidebar-panel-header">
        <h6>Relatórios</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.vendas') ? 'active' : '' }}" href="{{ route('relatorios.vendas') }}">
            Vendas
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.marcacoes') ? 'active' : '' }}" href="{{ route('relatorios.marcacoes') }}">
            Marcações
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.comissoes') ? 'active' : '' }}" href="{{ route('relatorios.comissoes') }}">
            Comissões
          </a>
        </li>
        @if(!empty($cashRegisterCanManage))
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.caixa') ? 'active' : '' }}" href="{{ route('relatorios.caixa') }}">
            Caixa
          </a>
        </li>
        @endif
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.sms') ? 'active' : '' }}" href="{{ route('relatorios.sms') }}">
            SMS
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('relatorios.booking-funnel') ? 'active' : '' }}" href="{{ route('relatorios.booking-funnel') }}">
            Funil Booking
          </a>
        </li>
      </ul>
    </div>
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessAi())
    <div class="sidebar-panel-section {{ request()->routeIs('ai.*') ? 'active' : '' }}" data-section="ai">
      <div class="sidebar-panel-header">
        <h6>Assistente AI</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('ai.index') ? 'active' : '' }}" href="{{ route('ai.index') }}">
            Chat
          </a>
        </li>
      </ul>
    </div>
    @endif

    @if(!$crmPrivacyLocked && $navUser->canAccessDefinicoes())
    <div class="sidebar-panel-section {{ request()->routeIs('definicoes.*') || request()->routeIs('activity.*') ? 'active' : '' }}" data-section="definicoes">
      <div class="sidebar-panel-header">
        <h6>Definições</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.negocio') ? 'active' : '' }}" href="{{ route('definicoes.negocio') }}">
            Negócio
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.marcacoes') ? 'active' : '' }}" href="{{ route('definicoes.marcacoes') }}">
            Marcações
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.equipa') ? 'active' : '' }}" href="{{ route('definicoes.equipa') }}">
            Equipa
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.emails') ? 'active' : '' }}" href="{{ route('definicoes.emails') }}">
            Emails
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.etiquetas') ? 'active' : '' }}" href="{{ route('definicoes.etiquetas') }}">
            Etiquetas
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.notificacoes') ? 'active' : '' }}" href="{{ route('definicoes.notificacoes') }}">
            Notificações
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('definicoes.pagamentos') ? 'active' : '' }}" href="{{ route('definicoes.pagamentos') }}">
            Pagamentos
          </a>
        </li>
        <li>
          <a class="panel-link {{ request()->routeIs('activity.*') ? 'active' : '' }}" href="{{ route('activity.index') }}">
            Activity Log
          </a>
        </li>
      </ul>
    </div>
    @endif

    <div class="sidebar-panel-section {{ request()->routeIs('ajuda.*') ? 'active' : '' }}" data-section="ajuda">
      <div class="sidebar-panel-header">
        <h6>Ajuda</h6>
        <button class="sidebar-panel-close btn-close" aria-label="Close"></button>
      </div>
      <ul class="panel-nav">
        <li>
          <a class="panel-link {{ request()->routeIs('ajuda.agenda') ? 'active' : '' }}" href="{{ route('ajuda.agenda') }}">
            Agenda
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay"></div>