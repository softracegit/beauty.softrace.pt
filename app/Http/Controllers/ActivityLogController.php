<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;

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

        $baseQuery = Activity::query();

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
            ->with('causer')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Sidebar stats (use the same filters as the list).
        $totalLogs = (clone $baseQuery)->count();

        $eventCounts = (clone $baseQuery)
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        $uniqueCausers = (clone $baseQuery)
            ->where('causer_type', $userMorphClass)
            ->distinct('causer_id')
            ->count('causer_id');

        $mostActiveUsersRows = (clone $baseQuery)
            ->where('causer_type', $userMorphClass)
            ->whereNotNull('causer_id')
            ->select('causer_id', DB::raw('count(*) as total'))
            ->groupBy('causer_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $mostActiveUserIds = $mostActiveUsersRows->pluck('causer_id')->filter()->values()->all();
        $mostActiveUsers = $mostActiveUserIds
            ? User::with('agent')
                ->whereIn('id', $mostActiveUserIds)
                ->get()
                ->keyBy('id')
            : collect();

        $mostActiveUsersFormatted = $mostActiveUsersRows->map(function ($row) use ($mostActiveUsers) {
            return (object)[
                'user_id' => (int) $row->causer_id,
                'total' => (int) $row->total,
                'user' => $mostActiveUsers->get((int) $row->causer_id),
            ];
        });

        $lastActivityAtRaw = (clone $baseQuery)->max('created_at');
        $lastActivityAt = $lastActivityAtRaw ? Carbon::parse((string) $lastActivityAtRaw) : null;

        // Subject type dropdown options (within the date/event/causer filters, but without subject_type filter).
        $subjectTypesQuery = Activity::query();
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

        // Most active subjects (lightweight: show type + id).
        $mostActiveSubjectsRows = (clone $baseQuery)
            ->select('subject_type', 'subject_id', DB::raw('count(*) as total'))
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('activity.index', compact(
            'activities',
            'q',
            'event',
            'from',
            'to',
            'causerId',
            'subjectType',
            'eventCounts',
            'uniqueCausers',
            'totalLogs',
            'mostActiveUsersFormatted',
            'lastActivityAt',
            'subjectTypeOptions',
            'mostActiveSubjectsRows'
        ));
    }
}

