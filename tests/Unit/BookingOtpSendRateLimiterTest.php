<?php

namespace Tests\Unit;

use App\Services\BookingOtpSendRateLimiter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingOtpSendRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_cooldown_blocks_second_attempt(): void
    {
        config([
            'booking.otp_send_cooldown_seconds' => 60,
            'booking.otp_send_max_per_window' => 0,
        ]);

        $lim = app(BookingOtpSendRateLimiter::class);
        $lim->assertCanSend('unit-bucket-a', 'login');
        $lim->recordSuccessfulSend('unit-bucket-a');

        try {
            $lim->assertCanSend('unit-bucket-a', 'login');
            $this->fail('Expected HttpResponseException');
        } catch (HttpResponseException $e) {
            $this->assertSame(422, $e->getResponse()->getStatusCode());
            $json = json_decode((string) $e->getResponse()->getContent(), true);
            $this->assertArrayHasKey('retry_after_seconds', $json);
            $this->assertGreaterThan(0, (int) $json['retry_after_seconds']);
        }
    }

    public function test_max_sends_then_lockout(): void
    {
        config([
            'booking.otp_send_cooldown_seconds' => 0,
            'booking.otp_send_max_per_window' => 2,
            'booking.otp_send_count_window_hours' => 1,
            'booking.otp_send_lockout_hours' => 1,
        ]);

        $lim = app(BookingOtpSendRateLimiter::class);
        $lim->assertCanSend('unit-bucket-b', 'verify');
        $lim->recordSuccessfulSend('unit-bucket-b');
        $lim->assertCanSend('unit-bucket-b', 'verify');
        $lim->recordSuccessfulSend('unit-bucket-b');

        try {
            $lim->assertCanSend('unit-bucket-b', 'verify');
            $this->fail('Expected HttpResponseException');
        } catch (HttpResponseException $e) {
            $this->assertSame(422, $e->getResponse()->getStatusCode());
            $json = json_decode((string) $e->getResponse()->getContent(), true);
            $this->assertArrayHasKey('errors', $json);
            $this->assertArrayHasKey('verify', $json['errors']);
            $this->assertStringContainsString('demasiados pedidos', (string) $json['errors']['verify'][0]);
        }
    }
}
