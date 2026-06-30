<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\ActivityLogDisplay;
use App\Support\ActivityLogMarcacaoOrigin;
use Tests\TestCase;

class ActivityLogMarcacaoOriginTest extends TestCase
{
    public function test_infers_online_origin_from_booking_client_causer(): void
    {
        $clientUser = new User(['name' => 'Daniel Simões', 'role' => User::ROLE_CLIENTE, 'client_id' => 1]);
        $event = new CalendarEvent(['event_type' => CalendarEvent::TYPE_MARCACAO]);

        $activity = new Activity([
            'event' => 'created',
            'description' => 'Marcação criada',
        ]);
        $activity->setRelation('causer', $clientUser);
        $activity->setRelation('subject', $event);

        $this->assertSame(ActivityLogMarcacaoOrigin::ONLINE, ActivityLogMarcacaoOrigin::inferFromActivity($activity));
        $this->assertSame('Marcação criada (marcação online)', ActivityLogDisplay::activityTitle($activity));
        $this->assertSame('Daniel Simões (cliente)', ActivityLogDisplay::causerLabel($activity));
    }

    public function test_activity_title_appends_agent_name_for_member_updates(): void
    {
        $agent = new Agent(['name' => 'Daniel Simões']);
        $activity = new Activity([
            'event' => 'updated',
            'description' => 'Membro atualizado',
        ]);
        $activity->setRelation('subject', $agent);

        $this->assertSame('Membro atualizado: Daniel Simões', ActivityLogDisplay::activityTitle($activity));
    }

    public function test_formats_commission_unit_labels(): void
    {
        $this->assertSame('Percentagem', ActivityLogDisplay::formatValue('commission_unit', Agent::COMMISSION_UNIT_PERCENT));
        $this->assertSame('Euro', ActivityLogDisplay::formatValue('commission_unit', Agent::COMMISSION_UNIT_EURO));
    }
}
