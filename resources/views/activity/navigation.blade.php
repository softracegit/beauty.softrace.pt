@extends('partials.layouts.main')
@section('title', 'Activity Log | Beauty CRM')

@section('content')
<div class="card">
    <div class="card-body pb-0 pt-3">
        @include('activity.partials.activity-tabs')
    </div>

    <div class="users-toolbar activity-log-toolbar">
            <div class="users-toolbar-left">
                <form action="{{ route('activity.navigation') }}" method="GET" class="activity-filter-form">
                    <div class="users-search activity-filter-search activity-filter-search--wide">
                        <i class="ph ph-magnifying-glass"></i>
                        <input type="text" name="q" placeholder="Pesquisar rota ou URL..." value="{{ $q }}">
                    </div>

                    <input type="text" name="from" class="form-control form-control-sm activity-filter-date" value="{{ $from }}" placeholder="De" data-crm-datepicker autocomplete="off" aria-label="De">

                    <input type="text" name="to" class="form-control form-control-sm activity-filter-date" value="{{ $to }}" placeholder="Até" data-crm-datepicker autocomplete="off" aria-label="Até">

                    <select name="user_id" class="form-select form-select-sm activity-filter-select" aria-label="Utilizador">
                        <option value="" {{ $userId ? '' : 'selected' }}>Utilizador</option>
                        @foreach($filterUsers as $u)
                            <option value="{{ $u->id }}" {{ (string) $userId === (string) $u->id ? 'selected' : '' }}>
                                {{ $u->name ?? $u->email }}
                            </option>
                        @endforeach
                    </select>

                    <select name="route_name" class="form-select form-select-sm activity-filter-select activity-filter-select--route" aria-label="Rota">
                        <option value="" {{ $routeName ? '' : 'selected' }}>Rota</option>
                            @foreach($routeOptions as $routeOption)
                                <option value="{{ $routeOption }}" {{ $routeName === $routeOption ? 'selected' : '' }}>
                                    {{ \App\Support\NavigationLogDisplay::routeLabel($routeOption) }}
                                </option>
                            @endforeach
                    </select>

                    <div class="activity-filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ph ph-funnel me-1"></i> Filtrar
                        </button>
                        <a href="{{ route('activity.navigation') }}" class="btn btn-outline-secondary btn-sm">
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-body pt-2">
            @include('activity.partials.navigation-log-list', ['logs' => $logs])

            @if($logs->hasPages())
                <div class="users-pagination">
                    <div class="users-pagination-info">
                        <span>Mostrando <strong>{{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }}</strong> de <strong>{{ $logs->total() }}</strong> visitas</span>
                    </div>
                    <div class="users-pagination-nav">
                        @if ($logs->onFirstPage())
                            <span class="users-page-btn" disabled><i class="ph ph-caret-left"></i></span>
                        @else
                            <a href="{{ $logs->previousPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-left"></i></a>
                        @endif

                        @php
                            $start = max(1, $logs->currentPage() - 2);
                            $end = min($logs->lastPage(), $logs->currentPage() + 2);
                        @endphp

                        @foreach ($logs->getUrlRange($start, $end) as $page => $url)
                            @if ($page == $logs->currentPage())
                                <span class="users-page-btn active">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="users-page-btn">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($logs->hasMorePages())
                            <a href="{{ $logs->nextPageUrl() }}" class="users-page-btn"><i class="ph ph-caret-right"></i></a>
                        @else
                            <span class="users-page-btn" disabled><i class="ph ph-caret-right"></i></span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
</div>
@endsection
