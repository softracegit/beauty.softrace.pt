<ul class="nav nav-tabs nav-tabs-bordered crm-segment-tabs mb-0" id="activityLogTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a
            class="nav-link {{ request()->routeIs('activity.index') ? 'active' : '' }}"
            href="{{ route('activity.index') }}"
            role="tab"
            aria-selected="{{ request()->routeIs('activity.index') ? 'true' : 'false' }}"
        >
            Alterações
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a
            class="nav-link {{ request()->routeIs('activity.navigation') ? 'active' : '' }}"
            href="{{ route('activity.navigation') }}"
            role="tab"
            aria-selected="{{ request()->routeIs('activity.navigation') ? 'true' : 'false' }}"
        >
            Navegação
        </a>
    </li>
</ul>
