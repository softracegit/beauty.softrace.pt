<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\SmsMessage;
use App\Models\Store;
use App\Services\SmsReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsReportCostEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimates_usd_to_eur_for_sms_count(): void
    {
        config([
            'services.twilio.sms_cost_usd' => 0.05010,
            'services.twilio.sms_usd_to_eur' => 0.92,
        ]);

        $estimate = app(SmsReportService::class)->estimateCost(100);

        $this->assertSame(100, $estimate['count']);
        $this->assertSame(0.05010, $estimate['unit_usd']);
        $this->assertEqualsWithDelta(0.04609, $estimate['unit_eur'], 0.00001);
        $this->assertEqualsWithDelta(5.01, $estimate['total_usd'], 0.001);
        $this->assertEqualsWithDelta(4.61, $estimate['total_eur'], 0.01);
    }

    public function test_daily_breakdown_fills_period_and_costs(): void
    {
        config([
            'services.twilio.sms_cost_usd' => 0.05,
            'services.twilio.sms_usd_to_eur' => 1.0,
        ]);

        $org = Organization::query()->create([
            'name' => 'Org SMS Cost',
            'slug' => 'org-sms-cost',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja SMS Cost',
            'slug' => 'loja-sms-cost',
            'timezone' => 'Europe/Lisbon',
        ]);

        SmsMessage::query()->create([
            'store_id' => $store->id,
            'type' => SmsMessage::TYPE_BOOKING_REMINDER,
            'to_phone' => '+351912345678',
            'from_phone' => 'SoftRace',
            'body' => 'teste 1',
            'sent_at' => Carbon::parse('2026-06-02 10:00:00', 'Europe/Lisbon')->utc(),
        ]);
        SmsMessage::query()->create([
            'store_id' => $store->id,
            'type' => SmsMessage::TYPE_BOOKING_REMINDER,
            'to_phone' => '+351912345679',
            'from_phone' => 'SoftRace',
            'body' => 'teste 2',
            'sent_at' => Carbon::parse('2026-06-02 15:00:00', 'Europe/Lisbon')->utc(),
        ]);
        SmsMessage::query()->create([
            'store_id' => $store->id,
            'type' => SmsMessage::TYPE_BOOKING_REMINDER,
            'to_phone' => '+351912345680',
            'from_phone' => 'SoftRace',
            'body' => 'teste 3',
            'sent_at' => Carbon::parse('2026-06-04 09:00:00', 'Europe/Lisbon')->utc(),
        ]);

        $breakdown = app(SmsReportService::class)->dailyBreakdown([(int) $store->id], [
            'desde' => '2026-06-01',
            'ate' => '2026-06-04',
        ]);

        $this->assertSame(['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04'], $breakdown['dates']);
        $this->assertSame([0, 2, 0, 1], $breakdown['counts']);
        $this->assertSame(3, $breakdown['total_count']);
        $this->assertSame(1, $breakdown['bucket_days']);
        $this->assertEqualsWithDelta(0.15, $breakdown['total_cost_eur'], 0.001);
        $this->assertEqualsWithDelta(0.10, $breakdown['costs_eur'][1], 0.001);
    }

    public function test_chart_bucket_days_grows_with_period_length(): void
    {
        $service = app(SmsReportService::class);
        $this->assertSame(1, $service->chartBucketDays(30));
        $this->assertSame(5, $service->chartBucketDays(60));
        $this->assertSame(10, $service->chartBucketDays(200));
    }
}
