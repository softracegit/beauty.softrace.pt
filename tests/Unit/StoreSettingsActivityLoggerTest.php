<?php

namespace Tests\Unit;

use App\Services\StoreSettingsActivityLogger;
use Tests\TestCase;

class StoreSettingsActivityLoggerTest extends TestCase
{
    public function test_log_scalar_change_formats_line(): void
    {
        $logger = new StoreSettingsActivityLogger;

        $this->assertSame(
            'Pagamento online obrigatório: Sim → Não',
            $logger->logScalarChange('Pagamento online obrigatório', true, false, fn ($v) => $v ? 'Sim' : 'Não'),
        );
        $this->assertNull($logger->logScalarChange('Igual', 'a', 'a'));
    }

    public function test_log_bool_change_formats_line(): void
    {
        $logger = new StoreSettingsActivityLogger;

        $this->assertSame('Gorjeta no POS: Não → Sim', $logger->logBoolChange('Gorjeta no POS', false, true));
        $this->assertNull($logger->logBoolChange('Igual', true, true));
    }
}
