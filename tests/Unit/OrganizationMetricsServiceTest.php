<?php

namespace Tests\Unit;

use App\Services\OrganizationMetricsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationMetricsServiceTest extends TestCase
{
    #[Test]
    public function previous_period_matches_same_length_ending_day_before(): void
    {
        $svc = $this->app->make(OrganizationMetricsService::class);

        [$prevDesde, $prevAte] = $svc->previousPeriodBounds('2026-09-01', '2026-09-30');

        $this->assertSame('2026-08-02', $prevDesde);
        $this->assertSame('2026-08-31', $prevAte);
    }

    #[Test]
    public function previous_period_for_single_day(): void
    {
        $svc = $this->app->make(OrganizationMetricsService::class);

        [$prevDesde, $prevAte] = $svc->previousPeriodBounds('2026-09-09', '2026-09-09');

        $this->assertSame('2026-09-08', $prevDesde);
        $this->assertSame('2026-09-08', $prevAte);
    }

    #[Test]
    public function evolution_mode_defaults_to_daily_under_45_days(): void
    {
        $svc = $this->app->make(OrganizationMetricsService::class);

        $this->assertSame('diaria', $svc->resolveEvolutionMode('2026-09-01', '2026-09-30'));
        $this->assertSame('semanal', $svc->resolveEvolutionMode('2026-01-01', '2026-03-31'));
        $this->assertSame('semanal', $svc->resolveEvolutionMode('2026-09-01', '2026-09-10', 'semanal'));
        $this->assertSame('diaria', $svc->resolveEvolutionMode('2026-01-01', '2026-12-31', 'diaria'));
    }
}
