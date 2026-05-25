<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Store;
use App\Services\AppointmentCancellationService;
use App\Services\CancellationPolicyService;
use App\Services\ClientWalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::query()->create([
            'name' => 'Org Cancel',
            'slug' => 'org-cancel',
            'status' => 'active',
        ]);
        $this->store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Cancel',
            'slug' => 'loja-cancel',
            'timezone' => 'Europe/Lisbon',
        ]);
        $this->client = Client::query()->create([
            'store_id' => $this->store->id,
            'name' => 'Cliente Cancel',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        CrmSetting::setInt(
            CrmSetting::KEY_BOOKING_CANCELLATION_NOTICE_HOURS,
            3,
            $this->store->id,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cancel_within_notice_period_credits_wallet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $this->createPaidBooking($event, 20.0);

        $result = app(AppointmentCancellationService::class)->cancel($event, [
            'cancellation_reason' => 'Teste',
        ]);

        $event->refresh();
        $this->client->refresh();

        $this->assertTrue($result->walletCredited);
        $this->assertSame(2000, $result->walletCreditAmountCents);
        $this->assertSame(CalendarEvent::STATUS_CANCELADO, $event->status);
        $this->assertTrue($event->avisou_dentro_prazo);
        $this->assertTrue($event->refund_reserva);
        $this->assertSame(2000, $this->client->wallet_balance_cents);
        $this->assertSame(1, ClientWalletTransaction::query()->where('client_id', $this->client->id)->count());
    }

    public function test_cancel_outside_notice_period_forfeits_deposit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 13:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $this->createPaidBooking($event, 25.0);

        $result = app(AppointmentCancellationService::class)->cancel($event);

        $event->refresh();
        $this->client->refresh();

        $this->assertFalse($result->walletCredited);
        $this->assertSame(0, $result->walletCreditAmountCents);
        $this->assertFalse($event->avisou_dentro_prazo);
        $this->assertFalse($event->refund_reserva);
        $this->assertSame(0, $this->client->wallet_balance_cents);
    }

    public function test_cancel_credit_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $this->createPaidBooking($event, 15.0);

        $service = app(AppointmentCancellationService::class);
        $service->cancel($event);
        $second = $service->cancel($event->fresh());

        $this->client->refresh();

        $this->assertTrue($second->alreadyCancelled);
        $this->assertSame(1500, $this->client->wallet_balance_cents);
        $this->assertSame(1, ClientWalletTransaction::query()->where('client_id', $this->client->id)->count());
    }

    public function test_policy_uses_store_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:30:00', 'Europe/Lisbon'));

        $event = $this->createMarcacaoAtLocal('2026-06-15 15:00:00');
        $policy = app(CancellationPolicyService::class)->resolveForEvent($event);

        $this->assertTrue($policy->isWithinNoticePeriod);
        $this->assertSame('Europe/Lisbon', $policy->businessTimezone);
        $this->assertSame('15/06/2026 às 12:00', $policy->deadlineFormatted());
    }

    private function createMarcacaoAtLocal(string $localDateTime): CalendarEvent
    {
        $tz = 'Europe/Lisbon';
        $startLocal = Carbon::parse($localDateTime, $tz);
        $startUtc = $startLocal->copy()->timezone(config('app.timezone'));

        return CalendarEvent::query()->create([
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação teste',
            'start_at' => $startUtc,
            'end_at' => $startUtc->copy()->addHour(),
        ]);
    }

    private function createPaidBooking(CalendarEvent $event, float $paidAmount): Booking
    {
        return Booking::query()->create([
            'store_id' => $this->store->id,
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'calendar_event_id' => $event->id,
            'client_id' => $this->client->id,
            'total_price' => 100,
            'paid_amount' => $paidAmount,
            'remaining_amount' => 100 - $paidAmount,
            'deposit_percent_used' => 20,
            'payment_status' => Booking::PAYMENT_PAID,
            'request_payload' => [],
        ]);
    }
}
