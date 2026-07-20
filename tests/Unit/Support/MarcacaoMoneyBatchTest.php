<?php

namespace Tests\Unit\Support;

use App\Support\MarcacaoMoneyBatch;
use Tests\TestCase;

class MarcacaoMoneyBatchTest extends TestCase
{
    public function test_empty_pipeline_totals(): void
    {
        $this->assertSame(
            ['previsto' => 0.0, 'por_fazer' => 0.0],
            MarcacaoMoneyBatch::sumPipelineTotals(collect())
        );
    }

    public function test_empty_event_ids_preload(): void
    {
        $batch = new MarcacaoMoneyBatch([], 1);
        $this->assertSame(40.0, $batch->amountDue(1, 40.0));
        $this->assertSame(0.0, $batch->moneyToward(1));
        $this->assertSame([], $batch->chargedFees(1));
        $this->assertTrue($batch->activeSalesForEvent(1)->isEmpty());
    }
}
