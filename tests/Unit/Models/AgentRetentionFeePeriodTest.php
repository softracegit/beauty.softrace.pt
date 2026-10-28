<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\AgentRetentionFeePeriod;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentRetentionFeePeriodTest extends TestCase
{
    #[Test]
    public function resolves_rate_in_force_for_sale_date(): void
    {
        $agent = new Agent;
        $agent->setRelation('retentionFeePeriods', new Collection([
            new AgentRetentionFeePeriod([
                'rate' => 2.0,
                'unit' => Agent::COMMISSION_UNIT_EURO,
                'effective_from' => '2026-11-01',
            ]),
            new AgentRetentionFeePeriod([
                'rate' => 1.5,
                'unit' => Agent::COMMISSION_UNIT_EURO,
                'effective_from' => '2026-06-01',
            ]),
        ]));

        $oct = $agent->retentionFeePeriodForDate('2026-10-15');
        $this->assertNotNull($oct);
        $this->assertSame(1.5, (float) $oct->rate);
        $this->assertSame('1,50 €', $oct->formatDisplay());

        $nov = $agent->retentionFeePeriodForDate('2026-11-05');
        $this->assertNotNull($nov);
        $this->assertSame(2.0, (float) $nov->rate);
        $this->assertSame('2,00 €', $nov->formatDisplay());

        $this->assertNull($agent->retentionFeePeriodForDate('2026-05-01'));
    }

    #[Test]
    public function cleared_period_stops_retention_without_erasing_history(): void
    {
        $agent = new Agent;
        $agent->setRelation('retentionFeePeriods', new Collection([
            new AgentRetentionFeePeriod([
                'rate' => null,
                'unit' => null,
                'effective_from' => '2026-10-01',
            ]),
            new AgentRetentionFeePeriod([
                'rate' => 1.5,
                'unit' => Agent::COMMISSION_UNIT_EURO,
                'effective_from' => '2026-06-01',
            ]),
        ]));

        $jun = $agent->retentionFeePeriodForDate('2026-06-20');
        $this->assertNotNull($jun);
        $this->assertSame(1.5, (float) $jun->rate);

        $this->assertNull($agent->retentionFeePeriodForDate('2026-10-15'));
    }

    #[Test]
    public function falls_back_to_agent_cache_when_no_periods(): void
    {
        $agent = new Agent([
            'retention_fee_rate' => 1.5,
            'retention_fee_unit' => Agent::COMMISSION_UNIT_EURO,
            'retention_fee_effective_from' => '2026-06-01',
        ]);
        $agent->setRelation('retentionFeePeriods', new Collection);

        $period = $agent->retentionFeePeriodForDate('2026-06-20');
        $this->assertNotNull($period);
        $this->assertSame(1.5, (float) $period->rate);
        $this->assertNull($agent->retentionFeePeriodForDate('2026-05-01'));
    }
}
