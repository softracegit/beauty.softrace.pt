<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Support\ActivityLogContext;
use Carbon\Carbon;
use Tests\TestCase;

class ActivityLogContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'UTC',
            'booking.business_timezone' => 'Europe/Lisbon',
        ]);
    }

    public function test_marcacao_line_includes_client_datetime_and_services(): void
    {
        $client = new Client(['name' => 'Rita Lopes']);
        $service = new \App\Models\Service(['name' => 'Manutenção de Gel']);

        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'start_at' => Carbon::parse('2026-06-30 17:35:00', 'UTC'),
        ]);
        $event->setRelation('client', $client);
        $event->setRelation('eventServices', collect([$service]));

        $line = ActivityLogContext::marcacaoLine($event);

        $this->assertSame('Rita Lopes · 30/06/2026 18:35 · Manutenção de Gel', $line);
    }

    public function test_context_from_properties_reads_contexto_key(): void
    {
        $line = ActivityLogContext::contextFromProperties([
            'contexto' => 'Beatriz · 30/06/2026 18:00',
            'sale_id' => 1,
        ]);

        $this->assertSame('Beatriz · 30/06/2026 18:00', $line);
    }

    public function test_resolve_for_activity_rebuilds_context_from_subject_when_missing(): void
    {
        $client = new Client(['name' => 'Beatriz Carbalho']);
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'start_at' => Carbon::parse('2026-06-30 17:35:00', 'UTC'),
        ]);
        $event->setRelation('client', $client);
        $event->setRelation('eventServices', collect());

        $activity = new Activity([
            'properties' => ['sale_id' => 1],
            'subject_type' => $event->getMorphClass(),
            'subject_id' => 99,
        ]);
        $activity->setRelation('subject', $event);

        $line = ActivityLogContext::resolveForActivity($activity);

        $this->assertSame('Beatriz Carbalho · 30/06/2026 18:35', $line);
    }

    public function test_resolve_for_activity_prefers_stored_contexto(): void
    {
        $activity = new Activity([
            'properties' => ['contexto' => 'Contexto gravado'],
        ]);

        $this->assertSame('Contexto gravado', ActivityLogContext::resolveForActivity($activity));
    }
}
