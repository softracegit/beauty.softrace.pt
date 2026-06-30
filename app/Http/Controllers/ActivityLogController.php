<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\UserPageViewLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

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
            ->with('user')
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

        return $query->where('store_id', current_store_id());
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

        return $query->where('store_id', current_store_id());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function filterUserOptions()
    {
        $user = auth()->user();
        $storeId = $user instanceof User && $user->isSuperAdmin()
            ? null
            : current_store_id();

        return User::activeStaff($storeId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
