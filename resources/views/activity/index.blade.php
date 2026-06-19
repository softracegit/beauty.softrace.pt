@extends('partials.layouts.main')
@section('title', 'Activity Log | Beauty CRM')

@section('content')
<div class="card">
    <div class="card-body pb-0 pt-3">
        @include('activity.partials.activity-tabs')
    </div>

    <div class="users-toolbar activity-log-toolbar">
            <div class="users-toolbar-left">
                <form action="{{ route('activity.index') }}" method="GET" class="activity-filter-form">
                    <div class="users-search activity-filter-search">
                        <i class="ph ph-magnifying-glass"></i>
                        <input type="text" name="q" placeholder="Pesquisar..." value="{{ $q }}">
                    </div>

                    <select name="event" class="form-select form-select-sm activity-filter-select activity-filter-select--action" aria-label="Ação">
                        <option value="" {{ $event ? '' : 'selected' }}>Ação</option>
                        <option value="created" {{ $event === 'created' ? 'selected' : '' }}>Criado</option>
                        <option value="updated" {{ $event === 'updated' ? 'selected' : '' }}>Atualizado</option>
                        <option value="deleted" {{ $event === 'deleted' ? 'selected' : '' }}>Eliminado</option>
                    </select>

                    <input type="text" name="from" class="form-control form-control-sm activity-filter-date" value="{{ $from }}" placeholder="De" data-crm-datepicker autocomplete="off" aria-label="De">

                    <input type="text" name="to" class="form-control form-control-sm activity-filter-date" value="{{ $to }}" placeholder="Até" data-crm-datepicker autocomplete="off" aria-label="Até">

                    <select name="causer_id" class="form-select form-select-sm activity-filter-select" aria-label="Membro">
                        <option value="" {{ $causerId ? '' : 'selected' }}>Membro</option>
                        @foreach($filterUsers as $u)
                            <option value="{{ $u->id }}" {{ (string)$causerId === (string)$u->id ? 'selected' : '' }}>
                                {{ $u->name ?? $u->email }}
                            </option>
                        @endforeach
                    </select>

                    <select name="subject_type" class="form-select form-select-sm activity-filter-select activity-filter-select--type" aria-label="Tipo de registo">
                        <option value="" {{ $subjectType ? '' : 'selected' }}>Tipo</option>
                        @foreach($subjectTypeOptions as $st)
                            <option value="{{ $st }}" {{ $subjectType === $st ? 'selected' : '' }}>
                                {{ class_basename(str_replace('App\\Models\\', '', $st)) }}
                            </option>
                        @endforeach
                    </select>

                    <div class="activity-filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ph ph-magnifying-glass me-1"></i> Filtrar
                        </button>
                        <a href="{{ route('activity.index') }}" class="btn btn-outline-secondary btn-sm">
                            Limpar
                        </a>
                    </div>
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
@endsection
