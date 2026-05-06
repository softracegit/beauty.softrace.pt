<?php

namespace Tests\Unit;

use App\Services\VendusApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendusApiServiceTest extends TestCase
{
    public function test_connection_uses_bearer_auth_and_returns_success(): void
    {
        config([
            'services.vendus.api_key' => 'test-key',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'bearer',
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/account/' => Http::response(['id' => 1], 200),
        ]);

        $result = app(VendusApiService::class)->testConnection();

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.vendus.pt/ws/v1.1/account/'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_connection_returns_error_when_not_configured(): void
    {
        config([
            'services.vendus.api_key' => '',
            'services.vendus.base_url' => '',
        ]);

        $result = app(VendusApiService::class)->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['status']);
        $this->assertStringContainsString('VENDUS_API_KEY', $result['message']);
    }
}
