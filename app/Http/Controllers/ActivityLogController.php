<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\UserPageViewLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $event = $request->get('event'); // created|updated|deleted|...
        $from = $request->get('from');
        $to = $request->get('to');
        $causerId = $request->get('causer_id');
        $subjectType = $request->get('subject_type');

        $userMorphClass = (new User())->getMorphClass();

        $baseQuery = $this->scopedActivityQuery();

        // Most activity pages are on event updates, so default excludes null events (legacy rows).
        if ($event) {
            $baseQuery->where('event', $event);
        } else {
            $baseQuery->whereNotNull('event');
        }

        if ($from) {
            $baseQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $baseQuery->whereDate('created_at', '<=', $to);
        }

        if ($q !== '') {
            $baseQuery->where(function ($qq) use ($q) {
                $qq->where('description', 'like', "%{$q}%")
                    ->orWhere('log_name', 'like', "%{$q}%");
            });
        }

        if ($causerId) {
            $baseQuery->where('causer_type', $userMorphClass)
                ->where('causer_id', (int) $causerId);
        }

        if ($subjectType) {
            $baseQuery->where('subject_type', $subjectType);
        }

        $activities = (clone $baseQuery)
            ->with([
                'causer',
                'store:id,name,slug',
                'subject' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    CalendarEvent::class => ['client', 'eventServices', 'service'],
                ]),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // Subject type dropdown options (within the date/event/causer filters, but without subject_type filter).
        $subjectTypesQuery = $this->scopedActivityQuery();
        if ($event) {
            $subjectTypesQuery->where('event', $event);
        } else {
            $subjectTypesQuery->whereNotNull('event');
        }
        if ($from) {
            $subjectTypesQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $subjectTypesQuery->whereDate('created_at', '<=', $to);
        }
        if ($q !== '') {
            $subjectTypesQuery->where(function ($qq) use ($q) {
                $qq->where('description', 'like', "%{$q}%")
                    ->orWhere('log_name', 'like', "%{$q}%");
            });
        }
        if ($causerId) {
            $subjectTypesQuery->where('causer_type', $userMorphClass)
                ->where('causer_id', (int) $causerId);
        }

        $subjectTypeOptions = (clone $subjectTypesQuery)
            ->select('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->filter()
            ->values();

        $filterUsers = $this->filterUserOptions();

        return view('activity.index', compact(
            'activities',
            'q',
            'event',
            'from',
            'to',
            'causerId',
            'subjectType',
            'subjectTypeOptions',
            'filterUsers',
        ));
    }

    public function navigation(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $userId = $request->get('user_id');
        $routeName = $request->get('route_name');
        $q = trim((string) $request->get('q'));

        $baseQuery = $this->scopedNavigationQuery();

        if ($from) {
            $baseQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $baseQuery->whereDate('created_at', '<=', $to);
        }
        if ($userId) {
            $baseQuery->where('user_id', (int) $userId);
        }
        if ($routeName) {
            $baseQuery->where('route_name', $routeName);
        }
        if ($q !== '') {
            $baseQuery->where(function ($qq) use ($q) {
                $qq->where('path', 'like', "%{$q}%")
                    ->orWhere('route_name', 'like', "%{$q}%");
            });
        }

        $logs = (clone $baseQuery)
            ->with(['user', 'store:id,name,slug'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $routeOptions = (clone $this->scopedNavigationQuery())
            ->select('route_name')
            ->distinct()
            ->whereNotNull('route_name')
            ->orderBy('route_name')
            ->pluck('route_name');

        $filterUsers = $this->filterUserOptions();

        return view('activity.navigation', compact(
            'logs',
            'from',
            'to',
            'userId',
            'routeName',
            'q',
            'routeOptions',
            'filterUsers',
        ));
    }

    /**
     * @return Builder<\App\Models\UserPageViewLog>
     */
    protected function scopedNavigationQuery(): Builder
    {
        $query = UserPageViewLog::query();
        $user = auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return $query;
        }

        $storeIds = $this->accessibleStoreIds($user);

        return $storeIds === []
            ? $query->whereRaw('0 = 1')
            : $query->whereIn('store_id', $storeIds);
    }

    /**
     * @return Builder<\App\Models\Activity>
     */
    protected function scopedActivityQuery(): Builder
    {
        $query = Activity::query();
        $user = auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return $query;
        }

        $storeIds = $this->accessibleStoreIds($user);

        return $storeIds === []
            ? $query->whereRaw('0 = 1')
            : $query->whereIn('store_id', $storeIds);
    }

    /**
     * Lojas visíveis no activity log (todas as acessíveis, não só a loja da sessão).
     *
     * @return list<int>
     */
    protected function accessibleStoreIds(?User $user): array
    {
        if (! $user instanceof User) {
            $fallback = current_store_id();

            return $fallback > 0 ? [$fallback] : [];
        }

        return $user->accessibleStores()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    protected function filterUserOptions(): Collection
    {
        $user = auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return User::activeStaff(null)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $storeIds = $this->accessibleStoreIds($user instanceof User ? $user : null);

        return User::activeStaff($storeIds !== [] ? $storeIds : null)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
