<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\AgentCommissionPeriod;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentCommissionPeriodTest extends TestCase
{
    #[Test]
    public function resolves_commission_rate_in_force_for_sale_date(): void
    {
        $agent = new Agent;
        $agent->setRelation('commissionPeriods', new Collection([
            new AgentCommissionPeriod([
                'rate' => 55,
                'unit' => Agent::COMMISSION_UNIT_PERCENT,
                'effective_from' => '2026-11-01',
            ]),
            new AgentCommissionPeriod([
                'rate' => 50,
                'unit' => Agent::COMMISSION_UNIT_PERCENT,
                'effective_from' => '2026-06-01',
            ]),
        ]));

        $oct = $agent->commissionPeriodForDate('2026-10-15');
        $this->assertNotNull($oct);
        $this->assertSame(50.0, (float) $oct->rate);
        $this->assertSame('50,00 %', $oct->formatDisplay());

        $nov = $agent->commissionPeriodForDate('2026-11-05');
        $this->assertNotNull($nov);
        $this->assertSame(55.0, (float) $nov->rate);
        $this->assertSame('55,00 %', $nov->formatDisplay());

        $this->assertNull($agent->commissionPeriodForDate('2026-05-01'));
    }

    #[Test]
    public function cleared_commission_stops_without_erasing_history(): void
    {
        $agent = new Agent;
        $agent->setRelation('commissionPeriods', new Collection([
            new AgentCommissionPeriod([
                'rate' => null,
                'unit' => null,
                'effective_from' => '2026-10-01',
            ]),
            new AgentCommissionPeriod([
                'rate' => 50,
                'unit' => Agent::COMMISSION_UNIT_PERCENT,
                'effective_from' => '2026-06-01',
            ]),
        ]));

        $jun = $agent->commissionPeriodForDate('2026-06-20');
        $this->assertNotNull($jun);
        $this->assertSame(50.0, (float) $jun->rate);

        $this->assertNull($agent->commissionPeriodForDate('2026-10-15'));
    }

    #[Test]
    public function falls_back_to_agent_cache_without_effective_from(): void
    {
        $agent = new Agent([
            'commission_rate' => 60,
            'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
        ]);
        $agent->setRelation('commissionPeriods', new Collection);

        $period = $agent->commissionPeriodForDate('2026-06-20');
        $this->assertNotNull($period);
        $this->assertSame(60.0, (float) $period->rate);
    }
}
