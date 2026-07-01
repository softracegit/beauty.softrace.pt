@extends('partials.layouts.main')
@section('title', $cliente->name . ' | Beauty CRM')

@section('css')
<link rel="stylesheet" href="{{ asset('template/css/payment-pos-modal.css') }}?v={{ file_exists(public_path('template/css/payment-pos-modal.css')) ? filemtime(public_path('template/css/payment-pos-modal.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('template/css/client-tags.css') }}?v={{ file_exists(public_path('template/css/client-tags.css')) ? filemtime(public_path('template/css/client-tags.css')) : time() }}">
@include('relatorios.partials.vendas-table-styles', ['showClienteColumn' => false])
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
        <p class="uview-email uview-email--profile mb-0">{{ $cliente->email }}</p>
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
                <button type="button" class="uview-tab nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-details">Detalhes</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-marcacoes">Marcações</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-vendas">Vendas</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-estatisticas">Estatísticas</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-log">Logs</button>
                <button type="button" class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-notas">Notas</button>
            </div>
            <div class="card-body tab-content">

                <!-- Details Tab -->
                <div class="tab-pane fade show active" id="tab-details">
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação Pessoal</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nome Completo</div>
                            <div class="uview-detail-value">{{ $cliente->name }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Email</div>
                            <div class="uview-detail-value"><a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a></div>
                        </div>
                        @if($cliente->phone)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Telefone</div>
                            <div class="uview-detail-value"><a href="{{ \App\Support\PhoneDisplay::telHref($cliente->phone) }}">{{ $cliente->formatted_phone }}</a></div>
                        </div>
                        @endif
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
                    </div>

                    {{-- Preferências --}}
                    @if($cliente->preferred_schedule || $cliente->preferences_notes)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Preferências</div>
                        @if($cliente->preferred_schedule)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Horário preferido</div>
                            <div class="uview-detail-value">{{ \App\Models\Client::preferredSchedules()[$cliente->preferred_schedule] ?? $cliente->preferred_schedule }}</div>
                        </div>
                        @endif
                        @if($cliente->preferences_notes)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Observações</div>
                            <div class="uview-detail-value">{{ $cliente->preferences_notes }}</div>
                        </div>
                        @endif
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
                            <div class="uview-detail-value">{{ $cliente->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Última Atualização</div>
                            <div class="uview-detail-value">{{ $cliente->updated_at->format('d/m/Y H:i') }}</div>
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
                    @if($marcacoes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Serviços</th>
                                        <th>Técnico</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($marcacoes as $ev)
                                        @php
                                            $isFutura = $ev->start_at->isFuture();
                                            $badgeClass = $isFutura ? 'bg-success-light text-success' : 'bg-secondary-light text-secondary';
                                        @endphp
                                        <tr>
                                            <td>{{ \App\Support\DateTimeDisplay::business($ev->start_at) }}</td>
                                            <td>
                                                @foreach($ev->eventServiceItems as $es)
                                                    <span class="badge {{ $isFutura ? 'bg-primary-light text-primary' : 'bg-secondary-light text-secondary' }} me-1">{{ trim((string) ($es->option_name ?? '')) !== '' ? $es->option_name : ($es->service?->name ?? '—') }}</span>
                                                @endforeach
                                            </td>
                                            <td>{{ $ev->user?->name ?? '—' }}</td>
                                            <td><span class="badge {{ $badgeClass }}">{{ \App\Models\CalendarEvent::statuses()[$ev->status] ?? $ev->status }}</span></td>
                                            <td>
                                                <a href="{{ route('agenda.index') }}?event={{ $ev->id }}" class="btn btn-sm btn-light" title="Ver na agenda"><i class="ph ph-calendar"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                    <p class="text-muted text-center py-3">Nenhuma marcação registada.</p>
                    @endif
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
                                        <span class="fw-semibold">Serviços mais usados</span>
                                    </div>
                                    @if($s->topServicos->isNotEmpty())
                                        <ol class="mb-0 ps-3">
                                            @foreach($s->topServicos as $sv)
                                                <li>{{ $sv->service_name }} <span class="text-muted">({{ $sv->total }})</span></li>
                                            @endforeach
                                        </ol>
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
                <div class="uview-status-list">
                <div class="uview-status-item">
                        <span class="uview-status-label">ID Cliente</span>
                        <span class="uview-status-value">{{ $cliente->client_id }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Registado em</span>
                        <span class="uview-status-value">{{ $cliente->created_at->format('d/m/Y') }}</span>
                    </div>
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
                        <span class="uview-status-value">{{ ($s?->ultimaVisita ?? null) ? \Carbon\Carbon::parse($s->ultimaVisita)->format('d/m/Y') : '—' }}</span>
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
                        <span class="uview-status-label">Notas</span>
                        <span class="uview-status-value">{{ $clientNotes->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('clientes.storeNote', $cliente), 'modelName' => 'cliente'])

@include('partials.payment-modal')

@endsection
@section('js')
@include('relatorios.partials.vendas-table-scripts')
<script>window.CLIENT_TAGS_CONFIG = { catalogUrl: @json(route('client-tags.index')), maxPerClient: {{ \App\Services\ClientTagService::MAX_TAGS_PER_CLIENT }} };</script>
<script src="{{ asset('template/js/client-tags.js') }}?v={{ file_exists(public_path('template/js/client-tags.js')) ? filemtime(public_path('template/js/client-tags.js')) : time() }}"></script>
<script>
(function() {
    const hash = window.location.hash;
    const params = new URLSearchParams(window.location.search);
    const activeTabParam = params.get('active_tab');
    const validTabs = ['tab-details', 'tab-marcacoes', 'tab-vendas', 'tab-estatisticas', 'tab-log', 'tab-notas'];
    var tabId = hash && validTabs.includes(hash.slice(1)) ? hash.slice(1) : null;
    if (!tabId && activeTabParam && validTabs.includes(activeTabParam)) {
        tabId = activeTabParam;
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
