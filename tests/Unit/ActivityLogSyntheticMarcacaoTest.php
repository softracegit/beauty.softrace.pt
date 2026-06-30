<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Support\ActivityLogDisplay;
use App\Support\ActivityLogSyntheticMarcacao;
use Tests\TestCase;

class ActivityLogSyntheticMarcacaoTest extends TestCase
{
    public function test_builds_synthetic_creation_for_zappy_import_without_created_log(): void
    {
        config(['app.name' => 'Race']);

        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'description' => "[Importado Zappy]\nNotas do cliente",
            'store_id' => 1,
            'created_at' => now()->subDays(10),
        ]);
        $event->id = 42;
        $event->setRelation('client', null);
        $event->setRelation('eventServices', collect());
        $event->setRelation('service', null);

        $activities = collect([
            new Activity([
                'event' => 'updated',
                'description' => 'Marcação atualizada',
                'created_at' => now()->subDay(),
            ]),
        ]);

        $merged = ActivityLogSyntheticMarcacao::injectMissingZappyCreation($activities, $event);

        $this->assertCount(2, $merged);
        $synthetic = $merged->last();
        $this->assertSame('created', $synthetic->event);
        $this->assertSame('Marcação criada (importação)', $synthetic->description);
        $this->assertSame(
            'Importação do Zappy para Race',
            ActivityLogDisplay::causerLabel($synthetic),
        );
    }

    public function test_skips_when_creation_log_already_exists(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'description' => '[Importado Zappy]',
            'store_id' => 1,
        ]);
        $event->id = 1;

        $activities = collect([
            new Activity([
                'event' => 'created',
                'description' => 'Marcação criada (agenda)',
            ]),
        ]);

        $this->assertNull(ActivityLogSyntheticMarcacao::missingZappyCreationEntry($event, $activities));
    }

    public function test_skips_non_zappy_marcacao(): void
    {
        $event = new CalendarEvent([
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'description' => 'Marcação normal',
            'store_id' => 1,
        ]);
        $event->id = 0;

        $this->assertNull(
            ActivityLogSyntheticMarcacao::missingZappyCreationEntry($event, collect()),
        );
    }

    public function test_has_creation_log_matches_marcacao_criada_variants(): void
    {
        $this->assertTrue(ActivityLogSyntheticMarcacao::hasCreationLog(collect([
            new Activity(['event' => 'created', 'description' => 'Marcação criada']),
            new Activity(['event' => 'updated', 'description' => 'Outro']),
        ])));

        $this->assertTrue(ActivityLogSyntheticMarcacao::hasCreationLog(collect([
            new Activity(['event' => 'created', 'description' => 'Marcação criada (importação)']),
        ])));

        $this->assertFalse(ActivityLogSyntheticMarcacao::hasCreationLog(collect([
            new Activity(['event' => 'updated', 'description' => 'Marcação atualizada']),
        ])));
    }
}
