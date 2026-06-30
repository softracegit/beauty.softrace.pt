<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\User;
use App\Support\ActivityLogDisplay;
use Tests\TestCase;

class ActivityLogDisplayRoleTest extends TestCase
{
    public function test_formats_user_role_labels(): void
    {
        $formatted = ActivityLogDisplay::formatValue('role', User::ROLE_RECECAO);

        $this->assertSame(User::roleLabel(User::ROLE_RECECAO), $formatted);
    }

    public function test_formats_agent_status_labels(): void
    {
        $formatted = ActivityLogDisplay::formatChange('status', 'active', 'inactive');

        $this->assertStringContainsString(Agent::statusLabels()['active'], $formatted);
        $this->assertStringContainsString(Agent::statusLabels()['inactive'], $formatted);
    }

    public function test_causer_label_for_staff_user(): void
    {
        $user = new User(['name' => 'Rute Forte', 'role' => User::ROLE_ADMIN]);
        $activity = new Activity(['event' => 'updated']);
        $activity->setRelation('causer', $user);

        $this->assertSame('Rute Forte', ActivityLogDisplay::causerLabel($activity));
    }
}
