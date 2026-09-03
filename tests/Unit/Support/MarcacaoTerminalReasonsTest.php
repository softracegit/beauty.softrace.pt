<?php

namespace Tests\Unit\Support;

use App\Models\CalendarEvent;
use App\Support\MarcacaoTerminalReasons;
use PHPUnit\Framework\TestCase;

class MarcacaoTerminalReasonsTest extends TestCase
{
    public function test_resolve_stores_preset_reason(): void
    {
        $resolved = MarcacaoTerminalReasons::resolveStoredReason(
            CalendarEvent::STATUS_FALTOU,
            MarcacaoTerminalReasons::FALTOU[3],
            null,
            'Nota interna',
        );

        $this->assertSame([
            'reason' => 'Cliente não disponível',
            'notes' => 'Nota interna',
        ], $resolved);
    }

    public function test_resolve_outra_requires_text(): void
    {
        $this->assertNull(MarcacaoTerminalReasons::resolveStoredReason(
            CalendarEvent::STATUS_CANCELADO,
            'Outra razão',
            '',
            null,
        ));

        $resolved = MarcacaoTerminalReasons::resolveStoredReason(
            CalendarEvent::STATUS_CANCELADO,
            'Outra razão',
            'Texto livre',
            null,
        );

        $this->assertSame('Texto livre', $resolved['reason']);
    }

    public function test_invalid_preset_returns_null(): void
    {
        $this->assertNull(MarcacaoTerminalReasons::resolveStoredReason(
            CalendarEvent::STATUS_FALTOU,
            'Marcação duplicada',
            null,
            null,
        ));
    }
}
