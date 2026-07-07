<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Support\ActivityLogMarcacaoOrigin;
use Tests\TestCase;

class CalendarEventMarcacaoSourceTest extends TestCase
{
    public function test_resolved_source_uses_stored_value(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'marcacao_source' => ActivityLogMarcacaoOrigin::ONLINE,
        ]);

        $this->assertSame(ActivityLogMarcacaoOrigin::ONLINE, $event->resolvedMarcacaoSource());
        $this->assertSame('Booking', $event->marcacaoSourceLabel());
        $this->assertTrue($event->isOnlineMarcacao());
        $this->assertFalse($event->isAgendaMarcacao());
    }

    public function test_resolved_source_falls_back_to_booking_relation(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'marcacao_source' => null,
        ]);
        $event->setRelation('onlineBooking', new Booking());

        $this->assertSame(ActivityLogMarcacaoOrigin::ONLINE, $event->resolvedMarcacaoSource());
    }

    public function test_resolved_source_defaults_to_agenda_for_legacy_marcacao(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'marcacao_source' => null,
            'description' => 'Notas da receção',
        ]);

        $this->assertSame(ActivityLogMarcacaoOrigin::AGENDA, $event->resolvedMarcacaoSource());
        $this->assertSame('Agenda', $event->marcacaoSourceLabel());
        $this->assertTrue($event->isAgendaMarcacao());
    }

    public function test_resolved_source_is_null_for_non_marcacao_events(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_TEMPO_PESSOAL,
            'marcacao_source' => ActivityLogMarcacaoOrigin::AGENDA,
        ]);

        $this->assertNull($event->resolvedMarcacaoSource());
        $this->assertNull($event->marcacaoSourceLabel());
    }
}
