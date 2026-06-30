<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class ActivityLogQuery
{
    public static function forSubject(Model $subject, int $limit = 200): Collection
    {
        if (! method_exists($subject, 'activities')) {
            return collect();
        }

        $activities = $subject->activities()
            ->with([
                'causer',
                'subject' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    CalendarEvent::class => ['client', 'eventServices', 'service', 'onlineBooking'],
                    Agent::class => [],
                    User::class => [],
                ]),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return self::attachSubject($activities, $subject);
    }

    public static function forAgent(Agent $agent, int $limit = 200): Collection
    {
        $agent->loadMissing('user');

        $merged = self::forSubject($agent, $limit);

        if ($agent->user) {
            $merged = $merged->concat(self::forSubject($agent->user, $limit));
        }

        return $merged
            ->sortByDesc(fn ($activity) => sprintf(
                '%s-%010d',
                $activity->created_at?->format('Y-m-d H:i:s.u') ?? '',
                (int) $activity->id,
            ))
            ->take($limit)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Activity>  $activities
     * @return \Illuminate\Support\Collection<int, \App\Models\Activity>
     */
    private static function attachSubject(Collection $activities, Model $subject): Collection
    {
        foreach ($activities as $activity) {
            if ((int) $activity->subject_id === (int) $subject->getKey()
                && $activity->subject_type === $subject->getMorphClass()) {
                $activity->setRelation('subject', $subject);
            }
        }

        return $activities;
    }
}
