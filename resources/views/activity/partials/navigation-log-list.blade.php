@if($logs->count() > 0)
    <div class="activity-log">
        @foreach($logs as $log)
            @php
                $paramsSummary = \App\Support\NavigationLogDisplay::routeParamsSummary($log->route_params);
            @endphp
            <div class="activity-item">
                <div class="activity-icon bg-primary-light text-primary">
                    <i class="ph ph-browser"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        {{ \App\Support\NavigationLogDisplay::routeLabel($log->route_name) }}
                    </div>
                    @if($paramsSummary)
                        <div class="activity-description small text-muted">
                            {{ $paramsSummary }}
                        </div>
                    @endif
                    <div class="activity-time">
                        <i class="ph ph-clock"></i> {{ \App\Support\ActivityLogDisplay::formatLogTimestamp($log->created_at, (int) ($log->store_id ?? 0) ?: null) }}
                        @if($log->user)
                            · {{ $log->user->name }}
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted text-center py-3">Nenhuma visita registada.</p>
@endif
