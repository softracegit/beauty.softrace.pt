<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class ActivityLogUserTimeline
{
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Logs do membro: alterações feitas por ele (causer) + alterações ao registo dele (subject).
     *
     * @return Collection<int, Activity>
     */
    public static function forAgent(Agent $agent, ?int $storeId = null, int $limit = 200): Collection
    {
        $agent->loadMissing('user');
        $storeId = $storeId ?? (function_exists('current_store_id') ? current_store_id() : null);

        $activities = self::queryForAgent($agent, $storeId)
            ->with(self::eagerLoads())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return self::attachKnownSubjects($activities, $agent);
    }

    public static function paginateForAgent(
        Agent $agent,
        ?int $storeId = null,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): LengthAwarePaginator {
        $agent->loadMissing('user');
        $storeId = $storeId ?? (function_exists('current_store_id') ? current_store_id() : null);

        $paginator = self::queryForAgent($agent, $storeId)
            ->with(self::eagerLoads())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->fragment('tab-log');

        $paginator->setCollection(
            self::attachKnownSubjects($paginator->getCollection(), $agent),
        );

        return $paginator;
    }

    /**
     * @return Builder<Activity>
     */
    private static function queryForAgent(Agent $agent, ?int $storeId): Builder
    {
        $agentMorphClass = $agent->getMorphClass();
        $agentId = (int) $agent->getKey();

        $query = Activity::query();

        if ($storeId !== null) {
            $query->where('store_id', (int) $storeId);
        }

        $query->where(function (Builder $outer) use ($agent, $agentMorphClass, $agentId): void {
            $outer->where(function (Builder $q) use ($agentMorphClass, $agentId): void {
                $q->where('subject_type', $agentMorphClass)
                    ->where('subject_id', $agentId);
            });

            $user = $agent->user;
            if ($user instanceof User) {
                $userMorphClass = $user->getMorphClass();
                $userId = (int) $user->getKey();

                $outer->orWhere(function (Builder $q) use ($userMorphClass, $userId): void {
                    $q->where('subject_type', $userMorphClass)
                        ->where('subject_id', $userId);
                });

                $outer->orWhere(function (Builder $q) use ($userMorphClass, $userId): void {
                    $q->where('causer_type', $userMorphClass)
                        ->where('causer_id', $userId);
                });
            }
        });

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private static function eagerLoads(): array
    {
        return [
            'causer',
            'subject' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                CalendarEvent::class => ['client', 'eventServices', 'service', 'onlineBooking'],
                Agent::class => [],
                User::class => [],
            ]),
        ];
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Activity>
     */
    private static function attachKnownSubjects(Collection $activities, Agent $agent): Collection
    {
        $agentMorphClass = $agent->getMorphClass();
        $userMorphClass = $agent->user?->getMorphClass();

        foreach ($activities as $activity) {
            if ($activity->subject_type === $agentMorphClass
                && (int) $activity->subject_id === (int) $agent->getKey()) {
                $activity->setRelation('subject', $agent);
            } elseif ($agent->user instanceof User
                && $activity->subject_type === $userMorphClass
                && (int) $activity->subject_id === (int) $agent->user->getKey()) {
                $activity->setRelation('subject', $agent->user);
            }
        }

        return $activities;
    }
}
