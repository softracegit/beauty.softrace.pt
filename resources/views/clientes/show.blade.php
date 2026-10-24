@extends('partials.layouts.main')
@section('title', $cliente->name . ' | Beauty CRM')

@section('css')
<link rel="stylesheet" href="{{ asset('template/css/payment-pos-modal.css') }}?v={{ file_exists(public_path('template/css/payment-pos-modal.css')) ? filemtime(public_path('template/css/payment-pos-modal.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('template/css/client-tags.css') }}?v={{ file_exists(public_path('template/css/client-tags.css')) ? filemtime(public_path('template/css/client-tags.css')) : time() }}">
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
@include('relatorios.partials.vendas-table-styles', ['showClienteColumn' => false])
<style>
.uview-header .uview-phone { margin: 0; font-size: 0.9rem; }
.uview-header .uview-phone a { color: inherit; text-decoration: none; }
.uview-header .uview-phone a:hover { text-decoration: underline; }
/* Resumo — card de alertas */
.client-ficha-alert-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-bottom: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: 0.65rem;
    border: 1px solid #fecaca;
    background: linear-gradient(135deg, #fff7ed 0%, #fef2f2 55%, #fff 100%);
    color: #9a3412;
    box-shadow: 0 1px 0 rgba(154, 52, 18, 0.04);
}
.client-ficha-alert-card__head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.client-ficha-alert-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 999px;
    background: #fff;
    color: #ea580c;
    font-size: 1.15rem;
    box-shadow: 0 0 0 1px rgba(234, 88, 12, 0.12);
    flex-shrink: 0;
}
.client-ficha-alert-card__title {
    font-weight: 700;
    font-size: 0.92rem;
    letter-spacing: 0.01em;
}
.client-ficha-alert-card__edit {
    margin-left: auto;
    font-size: 0.8rem;
    font-weight: 600;
    color: #c2410c;
    text-decoration: none;
}
.client-ficha-alert-card__edit:hover { color: #9a3412; text-decoration: underline; }
.client-ficha-alert-card__body { padding-left: 2.2rem; }
.client-ficha-alert-card__line {
    font-size: 0.88rem;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}
.client-ficha-alert-card__line + .client-ficha-alert-card__line { margin-top: 0.25rem; }

/* Resumo — KPI cards */
.client-resumo-kpis .card-body {
    padding: 0.85rem 1rem 0.7rem;
}

/* Resumo — timeline */
.client-ficha-timeline-wrap--spaced {
    margin-top: 2.25rem;
}
.client-ficha-timeline {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-top: 0.5rem;
}
.client-ficha-timeline__item {
    --timeline-icon-size: 2.25rem;
    --timeline-icon-gap: 0.55rem;
    --timeline-item-gap: 1.75rem;
    display: grid;
    grid-template-columns: var(--timeline-icon-size) minmax(0, 1fr);
    gap: 0.85rem 1.1rem;
    align-items: start;
    position: relative;
    padding: 0 0 var(--timeline-item-gap);
}
.client-ficha-timeline__item:last-child {
    padding-bottom: 0.25rem;
}
.client-ficha-timeline__item:not(:last-child) .client-ficha-timeline__rail::after {
    content: "";
    position: absolute;
    left: 50%;
    top: calc(0.15rem + var(--timeline-icon-size) + var(--timeline-icon-gap));
    bottom: calc(-1 * var(--timeline-item-gap) + var(--timeline-icon-gap));
    width: 2px;
    transform: translateX(-50%);
    background: color-mix(in srgb, var(--accent-color, #0d6efd) 28%, transparent);
}
.client-ficha-timeline__rail {
    position: relative;
    display: flex;
    justify-content: center;
    align-self: stretch;
    padding-top: 0.15rem;
}
.client-ficha-timeline__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: var(--timeline-icon-size);
    height: var(--timeline-icon-size);
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.1), 0 1px 2px rgba(15, 23, 42, 0.05);
    z-index: 1;
}
.client-ficha-timeline__status-icon {
    font-size: 1.25rem;
    line-height: 1;
    color: #6c757d;
}
.client-ficha-timeline__status-icon.agenda-status-icon-agendado { color: #6c757d; }
.client-ficha-timeline__status-icon.agenda-status-icon-notificado { color: #eab308; }
.client-ficha-timeline__status-icon.agenda-status-icon-confirmado { color: #16a34a; }
.client-ficha-timeline__status-icon.agenda-status-icon-chegou,
.client-ficha-timeline__status-icon.agenda-status-icon-iniciado { color: var(--accent-color, #0d6efd); }
.client-ficha-timeline__status-icon.ri-forbid-fill,
.client-ficha-timeline__status-icon.ri-close-circle-fill { color: #dc2626; }
.client-ficha-timeline__status-icon.agenda-status-icon-iniciado.ri { font-size: 1.35rem; }
.client-ficha-timeline__content {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.07);
}
.client-ficha-timeline__item:last-child .client-ficha-timeline__content {
    padding-bottom: 0;
    border-bottom: 0;
}
.client-ficha-timeline__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem 1.5rem;
}
.client-ficha-timeline__main {
    min-width: 0;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.client-ficha-timeline__headline {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.25rem 0.55rem;
    min-width: 0;
}
.client-ficha-timeline__date {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}
.client-ficha-timeline__hour {
    font-size: 0.82rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: #475569;
}
.client-ficha-timeline__services {
    font-size: 0.98rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
    min-width: 0;
}
.client-ficha-timeline__status-line {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.2rem 0.35rem;
    font-size: 0.84rem;
    color: #64748b;
}
.client-ficha-timeline__status {
    font-weight: 600;
    color: #1e293b;
}
.client-ficha-timeline__meta {
    color: #64748b;
}
.client-ficha-timeline__meta-sep {
    color: #cbd5e1;
}
.client-ficha-timeline__agenda-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 0.45rem;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    flex-shrink: 0;
}
.client-ficha-timeline__agenda-btn:hover {
    background: #eff6ff;
    border-color: rgba(37, 99, 235, 0.25);
    color: var(--accent-color, #0d6efd);
}
.client-ficha-timeline__agenda-btn .ph { font-size: 1.15rem; }
.client-ficha-timeline__facts {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.25rem 1.15rem;
    margin-top: 0.1rem;
}
.client-ficha-timeline__fact {
    font-size: 0.78rem;
    color: #334155;
    line-height: 1.35;
}
.client-ficha-timeline__fact-label {
    display: inline-block;
    margin-right: 0.3rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}
.client-ficha-timeline__tech {
    margin-top: 0.35rem;
    padding: 0.65rem 0.8rem;
    background: var(--bs-tertiary-bg, #f8fafc);
    border: 1px solid var(--bs-border-color, #eef2f7);
    border-radius: 0.45rem;
    font-size: 0.84rem;
    line-height: 1.45;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.client-ficha-timeline__tech-label {
    display: inline-block;
    min-width: 4.5rem;
    margin-right: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}
@media (max-width: 767.98px) {
    .client-ficha-timeline__agenda-btn {
        align-self: flex-start;
    }
}
</style>
@endsection

@section('content')

@php
    $avatarNum = ($cliente->id % 9) + 1;
    $hasAvatar = (bool) $cliente->avatar;
    $avatarSrc = $hasAvatar ? asset('storage/' . $cliente->avatar) : null;
    $nameParts = preg_split('/\s+/u', trim((string) ($cliente->name ?? '')));
    $nameParts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));
    if (count($nameParts) >= 2) {
        $firstInitial = mb_substr($nameParts[0], 0, 1, 'UTF-8');
        $lastInitial = mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8');
        $avatarInitial = mb_strtoupper($firstInitial . $lastInitial, 'UTF-8');
    } elseif (count($nameParts) === 1) {
        $avatarInitial = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $avatarInitial = '—';
    }
    $clientNotes = $cliente->getRelationValue('notes') ?: $cliente->notes()->with('user')->get();
@endphp

<!-- User Profile Header -->
<div class="uview-header">
    @if($hasAvatar)
        <img src="{{ $avatarSrc }}" alt="{{ $cliente->name }}" class="uview-avatar">
    @else
        <div class="uview-avatar uview-avatar-initials d-flex align-items-center justify-content-center fw-semibold">
            {{ $avatarInitial }}
        </div>
    @endif
    <div class="uview-info">
        <h2 class="uview-name">{{ $cliente->name }}</h2>
        @if($cliente->email)
            <p class="uview-email uview-email--profile mb-0"><a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a></p>
        @endif
        @if($cliente->phone)
            <p class="uview-phone uview-email--profile mb-0">
                <a href="{{ \App\Support\PhoneDisplay::telHref($cliente->phone) }}">{{ $cliente->formatted_phone }}</a>
            </p>
        @endif
        <div class="uview-header-tags">
            @include('clientes.partials.tags-inline', [
                'client' => $cliente,
                'tags' => $cliente->tags,
                'readonly' => auth()->user()?->isPrestador() ?? false,
                'variant' => 'profile',
            ])
        </div>
    </div>
    <div class="uview-header-actions">
        <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-primary btn-sm">
            <i class="ph ph-pencil-simple me-1"></i> Editar
        </a>
        @if($agents->isNotEmpty())
        <div class="dropdown">
            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ph ph-calendar-plus me-1"></i> Nova marcação
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach($agents as $agent)
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('agenda.index') }}?novaMarcacao=1&client_id={{ $cliente->id }}&user_id={{ $agent->user_id }}">
                        @php
                            $agentAvatar = $agent->avatar ? asset('storage/' . $agent->avatar) : asset('template/img/avatars/avatar-' . (($agent->id % 9) + 1) . '.webp');
                        @endphp
                        <img src="{{ $agentAvatar }}" alt="" class="rounded-circle" width="28" height="28" style="object-fit: cover;">
                        <span>{{ $agent->name }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>

<!-- Content Grid -->
<div class="uview-grid">
    <!-- Main Content -->
    <div>
        <div class="card">
            <div class="uview-tabs" role="tablist">
                <button type="button" class="uview-tab nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-resumo">Resumo</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-details">Detalhes</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-marcacoes">Marcações</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-vendas">Vendas</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-estatisticas">Estatísticas</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-log">Logs</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-notas">Notas</button>
            </div>
            <div class="card-body tab-content">

                <!-- Resumo Tab -->
                <div class="tab-pane fade show active" id="tab-resumo">
                    @include('clientes.partials.ficha-tecnica', [
                        'cliente' => $cliente,
                        'marcacoes' => $marcacoes ?? collect(),
                        'stats' => $stats ?? null,
                    ])
                </div>

                <!-- Details Tab — perfil completo (mesmo que também apareça no header / resumo / ficha técnica) -->
                <div class="tab-pane fade" id="tab-details">
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação Pessoal</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nome Completo</div>
                            <div class="uview-detail-value">{{ $cliente->name }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Email</div>
                            <div class="uview-detail-value">
                                @if($cliente->email)
                                    <a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Telefone</div>
                            <div class="uview-detail-value">
                                @if($cliente->phone)
                                    <a href="{{ \App\Support\PhoneDisplay::telHref($cliente->phone) }}">{{ $cliente->formatted_phone }}</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        @if($cliente->nif)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">NIF</div>
                            <div class="uview-detail-value">{{ $cliente->nif }}</div>
                        </div>
                        @endif
                        @if($cliente->birth_date)
                        @php
                            $clienteBirthdayHighlight = $cliente->birthdayHighlight(sameMonthOnly: true);
                        @endphp
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Nascimento</div>
                            <div class="uview-detail-value">
                                {{ $cliente->birth_date->format('d/m/Y') }}
                                @if($clienteBirthdayHighlight)
                                <div class="client-birthday-highlight client-birthday-highlight--{{ $clienteBirthdayHighlight['scope'] }} client-birthday-highlight--{{ $clienteBirthdayHighlight['tense'] }}" role="status">
                                    <i class="ri-gift-2-fill" aria-hidden="true"></i>
                                    <span>{!! $clienteBirthdayHighlight['message_html'] !!}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Idade</div>
                            <div class="uview-detail-value">{{ $cliente->age }} anos</div>
                        </div>
                        @endif
                        @if($cliente->gender)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Género</div>
                            <div class="uview-detail-value">{{ \App\Models\Client::genders()[$cliente->gender] ?? $cliente->gender }}</div>
                        </div>
                        @endif
                        @if($cliente->nationality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nacionalidade</div>
                            <div class="uview-detail-value">{{ $cliente->nationality }}</div>
                        </div>
                        @endif
                        @if($cliente->marital_status)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Estado Civil</div>
                            <div class="uview-detail-value">{{ \App\Models\Client::maritalStatuses()[$cliente->marital_status] ?? $cliente->marital_status }}</div>
                        </div>
                        @endif
                        @if($cliente->origem)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Origem</div>
                            <div class="uview-detail-value">{{ $cliente->origem }}</div>
                        </div>
                        @endif
                        @if($cliente->profissao)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Profissão</div>
                            <div class="uview-detail-value">{{ $cliente->profissao }}</div>
                        </div>
                        @endif
                    </div>

                    @php
                        $detalheAlertas = collect([
                            $cliente->allergies,
                            $cliente->contraindications,
                        ])->map(fn ($v) => trim((string) $v))->filter()->implode(' · ');
                    @endphp
                    @if($detalheAlertas !== '')
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Alertas</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Alertas</div>
                            <div class="uview-detail-value text-danger">{{ $detalheAlertas }}</div>
                        </div>
                    </div>
                    @endif

                    @if($cliente->address || $cliente->postal_code || $cliente->locality)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Morada</div>
                        @if($cliente->address)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Morada</div>
                            <div class="uview-detail-value">
                                {{ $cliente->address }}
                                @if($cliente->door), Porta {{ $cliente->door }}@endif
                                @if($cliente->floor), {{ $cliente->floor }}º@endif
                                @if($cliente->side), {{ $cliente->side }}@endif
                            </div>
                        </div>
                        @endif
                        @if($cliente->postal_code)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Código Postal</div>
                            <div class="uview-detail-value">{{ $cliente->postal_code }}</div>
                        </div>
                        @endif
                        @if($cliente->locality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Localidade</div>
                            <div class="uview-detail-value">{{ $cliente->locality }}</div>
                        </div>
                        @endif
                    </div>
                    @endif

                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação da Conta</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">ID Cliente</div>
                            <div class="uview-detail-value">{{ $cliente->client_id }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Registo</div>
                            <div class="uview-detail-value">{{ \App\Support\DateTimeDisplay::formatInstant($cliente->created_at, $cliente->store_id ? (int) $cliente->store_id : null, 'd/m/Y H:i') }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Última Atualização</div>
                            <div class="uview-detail-value">{{ \App\Support\DateTimeDisplay::formatInstant($cliente->updated_at, $cliente->store_id ? (int) $cliente->store_id : null, 'd/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Marcações Tab -->
                <div class="tab-pane fade" id="tab-marcacoes">
                    <form method="GET" action="{{ route('clientes.show', $cliente) }}" class="uview-cliente-tab-filters mb-3">
                        <input type="hidden" name="active_tab" value="tab-marcacoes">
                        <input type="hidden" name="vendas_desde" value="{{ $vendasDesde ?? '' }}">
                        <input type="hidden" name="vendas_ate" value="{{ $vendasAte ?? '' }}">
                        <input type="hidden" name="vendas_servico" value="{{ $vendasServico ?? '' }}">
                        <input type="hidden" name="vendas_tecnico" value="{{ $vendasTecnico ?? '' }}">
                        <input type="hidden" name="vendas_estado" value="{{ $vendasEstado ?? '' }}">
                        <div class="uview-filter-field uview-filter-date">
                            <label class="form-label small text-muted mb-0">Desde</label>
                            <input type="text" name="marcacoes_desde" class="form-control form-control-sm" value="{{ $marcacoesDesde ?? '' }}">
                        </div>
                        <div class="uview-filter-field uview-filter-date">
                            <label class="form-label small text-muted mb-0">Até</label>
                            <input type="text" name="marcacoes_ate" class="form-control form-control-sm" value="{{ $marcacoesAte ?? '' }}">
                        </div>
                        <div class="uview-filter-field uview-filter-select">
                            <label class="form-label small text-muted mb-0">Serviço</label>
                            <select name="marcacoes_servico" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach($servicosCliente ?? [] as $svc)
                                <option value="{{ $svc->id }}" {{ ($marcacoesServico ?? '') == $svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uview-filter-field uview-filter-select">
                            <label class="form-label small text-muted mb-0">Técnico</label>
                            <select name="marcacoes_tecnico" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach($tecnicosCliente ?? [] as $tec)
                                <option value="{{ $tec->id }}" {{ ($marcacoesTecnico ?? '') == $tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uview-filter-field uview-filter-estado">
                            <label class="form-label small text-muted mb-0">Estado</label>
                            <select name="marcacoes_estado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach(\App\Models\CalendarEvent::statuses() as $key => $label)
                                    <option value="{{ $key }}" {{ ($marcacoesEstado ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uview-filter-submit">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-magnifying-glass"></i>
                            </button>
                        </div>
                    </form>
                    @include('relatorios.partials.marcacoes-table', [
                        'marcacoes' => $marcacoes,
                        'marcacoesTotais' => $marcacoesTotais ?? [],
                        'showClienteColumn' => false,
                        'actionMode' => 'modal',
                        'emptyMessage' => 'Nenhuma marcação registada.',
                    ])
                </div>

                <!-- Vendas Tab -->
                <div class="tab-pane fade" id="tab-vendas">
                    <form method="GET" action="{{ route('clientes.show', $cliente) }}" class="uview-cliente-tab-filters mb-3">
                        <input type="hidden" name="active_tab" value="tab-vendas">
                        <input type="hidden" name="marcacoes_desde" value="{{ $marcacoesDesde ?? '' }}">
                        <input type="hidden" name="marcacoes_ate" value="{{ $marcacoesAte ?? '' }}">
                        <input type="hidden" name="marcacoes_servico" value="{{ $marcacoesServico ?? '' }}">
                        <input type="hidden" name="marcacoes_tecnico" value="{{ $marcacoesTecnico ?? '' }}">
                        <input type="hidden" name="marcacoes_estado" value="{{ $marcacoesEstado ?? '' }}">
                        <div class="uview-filter-field uview-filter-date">
                            <label class="form-label small text-muted mb-0">Desde</label>
                            <input type="text" name="vendas_desde" class="form-control form-control-sm" value="{{ $vendasDesde ?? '' }}">
                        </div>
                        <div class="uview-filter-field uview-filter-date">
                            <label class="form-label small text-muted mb-0">Até</label>
                            <input type="text" name="vendas_ate" class="form-control form-control-sm" value="{{ $vendasAte ?? '' }}">
                        </div>
                        <div class="uview-filter-field uview-filter-select">
                            <label class="form-label small text-muted mb-0">Serviço</label>
                            <select name="vendas_servico" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach($servicosCliente ?? [] as $svc)
                                <option value="{{ $svc->id }}" {{ ($vendasServico ?? '') == $svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uview-filter-field uview-filter-select">
                            <label class="form-label small text-muted mb-0">Técnico</label>
                            <select name="vendas_tecnico" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach($tecnicosCliente ?? [] as $tec)
                                <option value="{{ $tec->id }}" {{ ($vendasTecnico ?? '') == $tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uview-filter-field uview-filter-estado">
                            <label class="form-label small text-muted mb-0">Estado</label>
                            <select name="vendas_estado" class="form-select form-select-sm">
                                <option value="">Faturado e Rascunho</option>
                                <option value="{{ \App\Models\Sale::INVOICE_STATUS_FATURADO }}" {{ ($vendasEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_FATURADO ? 'selected' : '' }}>Faturado</option>
                                <option value="{{ \App\Models\Sale::INVOICE_STATUS_RASCUNHO }}" {{ ($vendasEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_RASCUNHO ? 'selected' : '' }}>Rascunho</option>
                            </select>
                        </div>
                        <div class="uview-filter-submit">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-magnifying-glass"></i>
                            </button>
                        </div>
                    </form>
                    @include('relatorios.partials.vendas-table', [
                        'vendas' => $vendas,
                        'vendasTotais' => $vendasTotais,
                        'showClienteColumn' => false,
                        'rowIdPrefix' => 'cliente-vendas-acoes',
                        'emptyMessage' => 'Nenhuma venda registada.',
                    ])
                </div>

                <!-- Estatísticas Tab -->
                <div class="tab-pane fade" id="tab-estatisticas">
                    @php $s = $stats ?? null; @endphp
                    @if($s)
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="ph ph-repeat fs-4 text-primary"></i>
                                        <span class="fw-semibold">Taxa de retenção</span>
                                    </div>
                                    @if($s->clienteRecorrente)
                                        <span class="badge bg-success-light text-success fs-6">Cliente recorrente</span>
                                        <p class="text-muted small mt-1 mb-0">{{ $s->totalMarcacoes }} marcações realizadas</p>
                                    @else
                                        <span class="badge bg-secondary-light text-secondary">Cliente novo</span>
                                        <p class="text-muted small mt-1 mb-0">{{ $s->totalMarcacoes }} marcação(ões) — precisa de 2+ para ser recorrente</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="ph ph-user fs-4 text-primary"></i>
                                        <span class="fw-semibold">Técnico preferido</span>
                                    </div>
                                    <p class="mb-0">{{ $s->tecnicoPreferido ?? '—' }}</p>
                                    <p class="text-muted small mt-1 mb-0">Técnico com mais marcações neste cliente</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="ph ph-scissors fs-4 text-primary"></i>
                                        <span class="fw-semibold">Serviço mais usado</span>
                                    </div>
                                    @if($s->topServicos->isNotEmpty())
                                        @php $sv = $s->topServicos->first(); @endphp
                                        <p class="mb-0">{{ $sv->service_name }} <span class="text-muted">({{ $sv->total }})</span></p>
                                    @else
                                        <p class="text-muted mb-0">Nenhum serviço registado.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Receita por mês (últimos 12 meses)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="min-height: 280px;" id="statsReceitaChart"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Padrão de agendamento — Dias da semana</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="min-height: 280px;" id="statsDiasChart"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Padrão de agendamento — Horários</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="min-height: 240px;" id="statsHorasChart"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <p class="text-muted text-center py-4">Sem dados de estatísticas disponíveis.</p>
                    @endif
                </div>

                <!-- Logs Tab -->
                <div class="tab-pane fade" id="tab-log">
                    @include('activity.partials.activity-log-list', [
                        'activities' => $activities,
                        'hideSubjectLinks' => true,
                    ])
                </div>

                <!-- Notas Tab -->
                <div class="tab-pane fade" id="tab-notas">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Notas do cliente</h6>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="ph ph-plus me-1"></i> Adicionar Nota
                        </button>
                    </div>
                    @if($clientNotes->count() > 0)
                        <div class="activity-log">
                            @foreach($clientNotes as $note)
                                @php
                                    $color = \App\Models\Note::getColorForType($note->type);
                                    $typeLabel = \App\Models\Note::types()[$note->type] ?? $note->type;
                                    $iconClass = match($note->type) {
                                        \App\Models\Note::TYPE_EMAIL => 'ph-duotone ph-envelope',
                                        \App\Models\Note::TYPE_CHAMADA => 'ph-duotone ph-phone',
                                        \App\Models\Note::TYPE_REUNIAO => 'ph-duotone ph-calendar',
                                        default => 'ph-duotone ph-note',
                                    };
                                    $bgClass = match($color) {
                                        'text-success' => 'bg-success-light text-success',
                                        'text-info' => 'bg-info-light text-info',
                                        'text-warning' => 'bg-warning-light text-warning',
                                        'text-danger' => 'bg-danger-light text-danger',
                                        'text-primary' => 'bg-primary-light text-primary',
                                        default => 'bg-primary-light text-primary',
                                    };
                                @endphp
                                <div class="activity-item">
                                    <div class="activity-icon {{ $bgClass }}">
                                        <i class="{{ $iconClass }}"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">{{ $typeLabel }}</div>
                                        <div class="activity-description">{{ $note->note }}</div>
                                        <div class="activity-time">
                                            <i class="ph ph-clock"></i> {{ $note->created_at->format('d/m/Y H:i') }}
                                            @if($note->user)
                                                por {{ $note->user->name }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center py-3">Nenhuma nota adicionada ainda.</p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        @php
            $s = $stats ?? null;
        @endphp
        {{-- Resumo / KPIs --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Resumo</h5>
            </div>
            <div class="card-body">
                @php
                    $sidebarStoreId = $cliente->store_id ? (int) $cliente->store_id : null;
                @endphp
                <div class="uview-status-list">
                    <div class="uview-status-item">
                        <span class="uview-status-label">Cliente desde</span>
                        <span class="uview-status-value">{{ $cliente->created_at->locale('pt')->diffForHumans() }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Total gasto</span>
                        <span class="uview-status-value">{{ number_format($totalGasto ?? 0, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Ticket médio</span>
                        <span class="uview-status-value">{{ $ticketMedio !== null ? number_format($ticketMedio, 2, ',', ' ') . ' €' : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Intervalo médio</span>
                        <span class="uview-status-value">{{ ($s?->intervaloMedioDias ?? null) !== null ? number_format($s->intervaloMedioDias, 0) . ' dias' : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Última visita</span>
                        <span class="uview-status-value">{{ ($s?->ultimaVisita ?? null) ? \App\Support\DateTimeDisplay::marcacao(\Carbon\Carbon::parse($s->ultimaVisita), $sidebarStoreId, 'd/m/Y') : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Marcações</span>
                        <span class="uview-status-value">{{ $s?->totalMarcacoes ?? $marcacoes->count() }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Marcações futuras</span>
                        <span class="uview-status-value">{{ $s?->marcacoesFuturas ?? 0 }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Horário preferido</span>
                        <span class="uview-status-value">{{ $cliente->preferred_schedule ? (\App\Models\Client::preferredSchedules()[$cliente->preferred_schedule] ?? $cliente->preferred_schedule) : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Data de nascimento</span>
                        <span class="uview-status-value">{{ $cliente->birth_date ? $cliente->birth_date->format('d/m/Y') : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Idade</span>
                        <span class="uview-status-value">{{ $cliente->birth_date && $cliente->age !== null ? $cliente->age.' anos' : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Género</span>
                        <span class="uview-status-value">{{ $cliente->gender ? (\App\Models\Client::genders()[$cliente->gender] ?? $cliente->gender) : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">NIF</span>
                        <span class="uview-status-value">{{ $cliente->nif ?: '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Nacionalidade</span>
                        <span class="uview-status-value">{{ $cliente->nationality ?: '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Estado Civil</span>
                        <span class="uview-status-value">{{ $cliente->marital_status ? (\App\Models\Client::maritalStatuses()[$cliente->marital_status] ?? $cliente->marital_status) : '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Origem</span>
                        <span class="uview-status-value">{{ $cliente->origem ?: '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Profissão</span>
                        <span class="uview-status-value">{{ $cliente->profissao ?: '—' }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">ID Cliente</span>
                        <span class="uview-status-value">{{ $cliente->client_id }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Data de registo</span>
                        <span class="uview-status-value">{{ \App\Support\DateTimeDisplay::formatInstant($cliente->created_at, $sidebarStoreId, 'd/m/Y') }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Última actualização</span>
                        <span class="uview-status-value">{{ \App\Support\DateTimeDisplay::formatInstant($cliente->updated_at, $sidebarStoreId, 'd/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('clientes.storeNote', $cliente), 'modelName' => 'cliente'])

@include('partials.payment-modal')

@include('relatorios.partials.marcacao-detalhe-modals', ['marcacoes' => $marcacoes ?? collect()])

@endsection
@section('js')
@include('relatorios.partials.vendas-table-scripts')
@include('relatorios.partials.marcacao-detalhe-modals-script')
<script>window.CLIENT_TAGS_CONFIG = { catalogUrl: @json(route('client-tags.index')), maxPerClient: {{ \App\Services\ClientTagService::MAX_TAGS_PER_CLIENT }} };</script>
<script src="{{ asset('template/js/client-tags.js') }}?v={{ file_exists(public_path('template/js/client-tags.js')) ? filemtime(public_path('template/js/client-tags.js')) : time() }}"></script>
<script>
(function() {
    const hash = window.location.hash;
    const params = new URLSearchParams(window.location.search);
    const activeTabParam = params.get('active_tab');
    const validTabs = ['tab-resumo', 'tab-ficha-tecnica', 'tab-details', 'tab-marcacoes', 'tab-vendas', 'tab-estatisticas', 'tab-log', 'tab-notas'];
    var tabId = hash && validTabs.includes(hash.slice(1)) ? hash.slice(1) : null;
    if (!tabId && activeTabParam && validTabs.includes(activeTabParam)) {
        tabId = activeTabParam;
    }
    if (tabId === 'tab-ficha-tecnica') {
        tabId = 'tab-resumo';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tabList = document.querySelector('.uview-tabs');
        if (tabList) {
            if (tabId) {
                const trigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
                if (trigger && typeof bootstrap !== 'undefined') {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                }
            }

            tabList.addEventListener('shown.bs.tab', function(e) {
                const target = e.target.getAttribute('data-bs-target');
                if (target && target.startsWith('#')) {
                    history.replaceState(null, '', window.location.pathname + target);
                }
                if (target === '#tab-estatisticas' && typeof initStatsCharts === 'function') {
                    initStatsCharts();
                }
            });
        }
    });
})();
@if(isset($stats) && $stats)
function initStatsCharts() {
    if (window._statsChartsRendered) return;
    window._statsChartsRendered = true;
    var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    var successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    var mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    var borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';
    var receitaPorMes = @json($stats->receitaPorMes ?? []);
    var porDiaSemana = @json($stats->porDiaSemana ?? []);
    var porHora = @json($stats->porHora ?? array_fill(0, 24, 0));
    var catsReceita = receitaPorMes.map(function(r) { return r.month; });
    var dataReceita = receitaPorMes.map(function(r) { return parseFloat(r.revenue); });
    var catsDias = porDiaSemana.map(function(d) { return d.nome; });
    var dataDias = porDiaSemana.map(function(d) { return d.total; });
    var catsHoras = [];
    for (var h = 0; h < 24; h++) catsHoras.push(String(h).padStart(2, '0') + 'h');
    if (document.querySelector('#statsReceitaChart') && typeof ApexCharts !== 'undefined') {
        new ApexCharts(document.querySelector('#statsReceitaChart'), {
            series: [{ name: 'Receita (€)', data: dataReceita }],
            chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '75%' } },
            colors: [accentColor],
            dataLabels: { enabled: false },
            xaxis: { categories: catsReceita, labels: { style: { colors: mutedColor }, maxHeight: 80 } },
            yaxis: { labels: { style: { colors: mutedColor } } },
            grid: { borderColor: borderColor, strokeDashArray: 4 }
        }).render();
    }
    if (document.querySelector('#statsDiasChart') && typeof ApexCharts !== 'undefined') {
        new ApexCharts(document.querySelector('#statsDiasChart'), {
            series: [{ name: 'Marcações', data: dataDias }],
            chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '65%' } },
            colors: [successColor],
            dataLabels: { enabled: false },
            xaxis: { categories: catsDias, labels: { style: { colors: mutedColor } } },
            yaxis: { labels: { style: { colors: mutedColor } } },
            grid: { borderColor: borderColor, strokeDashArray: 4 }
        }).render();
    }
    if (document.querySelector('#statsHorasChart') && typeof ApexCharts !== 'undefined') {
        new ApexCharts(document.querySelector('#statsHorasChart'), {
            series: [{ name: 'Marcações', data: Object.values(porHora) }],
            chart: { type: 'bar', height: 240, fontFamily: 'inherit', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '90%' } },
            colors: [accentColor],
            dataLabels: { enabled: false },
            xaxis: { categories: catsHoras, labels: { style: { colors: mutedColor } } },
            yaxis: { labels: { style: { colors: mutedColor } } },
            grid: { borderColor: borderColor, strokeDashArray: 4 }
        }).render();
    }
}
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#tab-estatisticas' && typeof initStatsCharts === 'function') {
        setTimeout(initStatsCharts, 100);
    }
});
@endif
</script>
@endsection
