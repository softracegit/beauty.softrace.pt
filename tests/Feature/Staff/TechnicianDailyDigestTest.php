<?php

namespace Tests\Feature\Staff;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\TechnicianDailyDigestNotification;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TechnicianDailyDigestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, tech: User, client: Client}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Digest',
            'slug' => 'org-digest',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Digest',
            'slug' => 'loja-digest',
        ]);
        $tech = User::query()->create([
            'name' => 'Técnica Digest',
            'email' => 'tecnica-digest@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $tech->id,
            'store_id' => $store->id,
            'name' => 'Técnica Digest',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $tech->stores()->sync([$store->id]);

        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Cliente Digest',
            'phone' => '912000111',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'allergies' => 'Alergia a PPD',
        ]);

        return compact('store', 'tech', 'client');
    }

    private function createMarcacao(Store $store, User $tech, Client $client, Carbon $startLocal): CalendarEvent
    {
        $startUtc = $startLocal->copy()->utc();

        return CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $tech->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Digest marcação',
            'start_at' => $startUtc,
            'end_at' => $startUtc->copy()->addHour(),
        ]);
    }

    #[Test]
    public function does_not_send_when_technician_has_no_appointments_today(): void
    {
        $fx = $this->fixture();
        $tz = StoreBusinessTime::timezoneForStore((int) $fx['store']->id);
        Carbon::setTestNow(Carbon::parse('2026-10-23 08:30:00', $tz));

        Notification::fake();

        $this->artisan('staff:send-daily-appointment-digest', ['--store' => $fx['store']->id])
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function sends_digest_when_technician_has_at_least_one_appointment(): void
    {
        $fx = $this->fixture();
        $tz = StoreBusinessTime::timezoneForStore((int) $fx['store']->id);
        Carbon::setTestNow(Carbon::parse('2026-10-23 08:30:00', $tz));

        $this->createMarcacao(
            $fx['store'],
            $fx['tech'],
            $fx['client'],
            Carbon::parse('2026-10-23 10:00:00', $tz)
        );

        Notification::fake();

        $this->artisan('staff:send-daily-appointment-digest', ['--store' => $fx['store']->id])
            ->assertSuccessful();

        Notification::assertSentTo($fx['tech'], TechnicianDailyDigestNotification::class, function (TechnicianDailyDigestNotification $n) use ($fx) {
            return $n->store->is($fx['store'])
                && $n->events->count() === 1
                && $n->events->first()->client?->allergies === 'Alergia a PPD';
        });
    }

    #[Test]
    public function sends_digest_on_sunday_when_there_are_appointments(): void
    {
        $fx = $this->fixture();
        $tz = StoreBusinessTime::timezoneForStore((int) $fx['store']->id);
        // Domingo
        Carbon::setTestNow(Carbon::parse('2026-10-25 08:30:00', $tz));
        $this->assertSame(Carbon::SUNDAY, Carbon::now($tz)->dayOfWeek);

        $this->createMarcacao(
            $fx['store'],
            $fx['tech'],
            $fx['client'],
            Carbon::parse('2026-10-25 11:00:00', $tz)
        );

        Notification::fake();

        $this->artisan('staff:send-daily-appointment-digest', ['--store' => $fx['store']->id])
            ->assertSuccessful();

        Notification::assertSentTo($fx['tech'], TechnicianDailyDigestNotification::class);
    }

    #[Test]
    public function respects_disabled_daily_digest_preference(): void
    {
        $fx = $this->fixture();
        $tz = StoreBusinessTime::timezoneForStore((int) $fx['store']->id);
        Carbon::setTestNow(Carbon::parse('2026-10-23 08:30:00', $tz));

        $this->createMarcacao(
            $fx['store'],
            $fx['tech'],
            $fx['client'],
            Carbon::parse('2026-10-23 10:00:00', $tz)
        );

        UserNotificationPreference::query()->create([
            'user_id' => $fx['tech']->id,
            'category' => UserNotificationPreference::TYPE_DAILY_DIGEST,
            'bell_enabled' => false,
            'email_enabled' => false,
        ]);

        Notification::fake();

        $this->artisan('staff:send-daily-appointment-digest', ['--store' => $fx['store']->id])
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
