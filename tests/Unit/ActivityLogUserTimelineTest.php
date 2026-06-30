<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\User;
use App\Support\ActivityLogUserTimeline;
use Tests\TestCase;

class ActivityLogUserTimelineTest extends TestCase
{
    public function test_merges_causer_and_subject_activities_without_duplicates(): void
    {
        $user = new User(['name' => 'Daniel Simões']);
        $user->id = 5;

        $agent = new Agent(['name' => 'Daniel Simões', 'user_id' => 5]);
        $agent->id = 2;
        $agent->setRelation('user', $user);

        $shared = new Activity([
            'event' => 'updated',
            'description' => 'Membro atualizado',
            'created_at' => now()->subMinutes(5),
        ]);
        $shared->id = 10;

        $onlyCauser = new Activity([
            'event' => 'updated',
            'description' => 'Cliente atualizado',
            'created_at' => now()->subMinute(),
        ]);
        $onlyCauser->id = 11;

        $merged = collect([$shared, $onlyCauser, $shared])
            ->unique(fn (Activity $activity) => (int) $activity->id)
            ->sortByDesc(fn (Activity $activity) => sprintf(
                '%s-%010d',
                $activity->created_at?->format('Y-m-d H:i:s.u') ?? '',
                (int) $activity->id,
            ))
            ->values();

        $this->assertCount(2, $merged);
        $this->assertSame(11, $merged->first()->id);
    }

    public function test_for_agent_without_user_does_not_query_causer_logs(): void
    {
        $agent = new Agent(['name' => 'Sem conta']);
        $agent->id = 1;

        $this->assertNull($agent->user_id);
        $this->assertNull($agent->user);
    }
}
