@extends('partials.layouts.main')
@section('title', 'Activity Log | Beauty CRM')

@section('content')
<div class="uview-grid">
    <!-- Main Content -->
    <div>
        <div class="card">
            <div class="users-toolbar">
                <div class="users-toolbar-left">
                    <form action="{{ route('activity.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2">
                        <div class="users-search">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" name="q" placeholder="Pesquisar..." value="{{ $q }}">
                        </div>

                        <div>
                            <label class="form-label small text-muted mb-0">Ação</label>
                            <select name="event" class="form-select form-select-sm">
                                <option value="" {{ $event ? '' : 'selected' }}>Todas</option>
                                <option value="created" {{ $event === 'created' ? 'selected' : '' }}>Criado</option>
                                <option value="updated" {{ $event === 'updated' ? 'selected' : '' }}>Atualizado</option>
                                <option value="deleted" {{ $event === 'deleted' ? 'selected' : '' }}>Eliminado</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label small text-muted mb-0">De</label>
                            <input type="text" name="from" class="form-control form-control-sm" value="{{ $from }}" data-crm-datepicker autocomplete="off">
                        </div>

                        <div>
                            <label class="form-label small text-muted mb-0">Até</label>
                            <input type="text" name="to" class="form-control form-control-sm" value="{{ $to }}" data-crm-datepicker autocomplete="off">
                        </div>

                        <div>
                            <label class="form-label small text-muted mb-0">Membro (causer)</label>
                            <select name="causer_id" class="form-select form-select-sm">
                                <option value="" {{ $causerId ? '' : 'selected' }}>Todos</option>
                                @foreach(\App\Models\User::query()->orderBy('name')->get() as $u)
                                    <option value="{{ $u->id }}" {{ (string)$causerId === (string)$u->id ? 'selected' : '' }}>
                                        {{ $u->name ?? $u->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label small text-muted mb-0">Tipo de registo</label>
                            <select name="subject_type" class="form-select form-select-sm">
                                <option value="" {{ $subjectType ? '' : 'selected' }}>Todos</option>
                                @foreach($subjectTypeOptions as $st)
                                    <option value="{{ $st }}" {{ $subjectType === $st ? 'selected' : '' }}>
                                        {{ class_basename(str_replace('App\\Models\\', '', $st)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ph ph-magnifying-glass me-1"></i> Filtrar
                        </button>
                        <a href="{{ route('activity.index') }}" class="btn btn-outline-secondary btn-sm">
                            Limpar
                        </a>
                    </form>
                </div>
            </div>

            <div class="card-body pt-2">
                @include('activity.partials.activity-log-list', ['activities' => $activities])
                @if($activities->hasPages())
                    <div class="users-pagination">
                        <div class="users-pagination-info">
                            <span>Mostrando <strong>{{ $activities->firstItem() ?? 0 }}-{{ $activities->lastItem() ?? 0 }}</strong> de <strong>{{ $activities->total() }}</strong> atividades</span>
                        </div>
                        <div class="users-pagination-nav">
                            @if ($activities->onFirstPage())
                                <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
                            @else
                                <a href="{{ $activities->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
                            @endif

                            @php
                                $start = max(1, $activities->currentPage() - 2);
                                $end = min($activities->lastPage(), $activities->currentPage() + 2);
                            @endphp

                            @foreach ($activities->getUrlRange($start, $end) as $page => $url)
                                @if ($page == $activities->currentPage())
                                    <span class="users-page-btn active">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                                @endif
                            @endforeach

                            @if ($activities->hasMorePages())
                                <a href="{{ $activities->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
                            @else
                                <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Resumo</h5>
            </div>
            <div class="card-body">
                @php
                    $createdCount = (int) ($eventCounts['created'] ?? 0);
                    $updatedCount = (int) ($eventCounts['updated'] ?? 0);
                    $deletedCount = (int) ($eventCounts['deleted'] ?? 0);
                @endphp
                <div class="uview-stats">
                    <div class="uview-stat">
                        <div class="uview-stat-value" style="color: var(--success-color)">{{ $createdCount }}</div>
                        <div class="uview-stat-label">Criados</div>
                    </div>
                    <div class="uview-stat">
                        <div class="uview-stat-value" style="color: var(--info-color)">{{ $updatedCount }}</div>
                        <div class="uview-stat-label">Atualizações</div>
                    </div>
                    <div class="uview-stat">
                        <div class="uview-stat-value" style="color: var(--accent-color)">{{ $updatedCount + $createdCount }}</div>
                        <div class="uview-stat-label">Total (C+U)</div>
                    </div>
                    <div class="uview-stat">
                        <div class="uview-stat-value" style="color: var(--danger-color)">{{ $deletedCount }}</div>
                        <div class="uview-stat-label">Eliminados</div>
                    </div>
                    <div class="uview-stat">
                        <div class="uview-stat-value">{{ $uniqueCausers }}</div>
                        <div class="uview-stat-label">Utilizadores únicos</div>
                    </div>
                </div>
                <div class="text-muted small mt-3">
                    Última atividade: {{ $lastActivityAt ? $lastActivityAt->format('d/m/Y H:i') : '—' }}
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Membros mais ativos</h5>
            </div>
            <div class="card-body p-0">
                <div class="uview-status-list" style="padding: 0 var(--spacing-lg);">
                    @if($mostActiveUsersFormatted->count() > 0)
                        @foreach($mostActiveUsersFormatted as $row)
                            <div class="uview-status-item">
                                <div class="d-flex align-items-center gap-2">
                                    @php
                                        $avatar = $row->user?->agent?->avatar ? asset('storage/' . $row->user->agent->avatar) : asset('template/img/profile-img.webp');
                                    @endphp
                                    <img src="{{ $avatar }}" alt="{{ $row->user?->name ?? 'User' }}" class="rounded-circle" width="28" height="28" style="width: 28px; height: 28px;">
                                    <span style="font-size: 0.8125rem; font-weight: 500;">
                                        {{ $row->user?->name ?? ($row->user?->email ?? '—') }}
                                    </span>
                                </div>
                                <span class="badge bg-primary">{{ $row->total }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="text-muted small p-3">Nenhum utilizador com atividade no filtro atual.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Objetos mais ativos</h5>
            </div>
            <div class="card-body p-0">
                <div class="uview-status-list" style="padding: 0 var(--spacing-lg);">
                    @if($mostActiveSubjectsRows->count() > 0)
                        @foreach($mostActiveSubjectsRows as $row)
                            <div class="uview-status-item">
                                <span class="uview-status-label">
                                    {{ class_basename($row->subject_type) }} #{{ $row->subject_id }}
                                </span>
                                <span class="badge bg-secondary">{{ (int) $row->total }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="text-muted small p-3">Sem dados no filtro atual.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

