<?php

namespace Tests\Unit;

use App\Models\Sale;
use App\Services\VendusPaymentMethodResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendusPaymentMethodResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_resolve_uses_env_override_without_matching_type(): void
    {
        config([
            'services.vendus.api_key' => 'k',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.payment_method_id' => '99',
        ]);

        Http::fake();

        $sale = new Sale();
        $sale->payment_method = Sale::PAYMENT_MBWAY;

        $id = app(VendusPaymentMethodResolver::class)->resolvePaymentMethodIdForSale($sale);

        $this->assertSame('99', $id);
        Http::assertNothingSent();
    }

    public function test_resolve_matches_vendus_type_from_api(): void
    {
        config([
            'services.vendus.api_key' => 'k',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.payment_method_id' => null,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 7, 'title' => 'Numerário', 'type' => 'NU', 'status' => 'on'],
            ], 200),
        ]);

        $sale = new Sale();
        $sale->id = 1;
        $sale->payment_method = Sale::PAYMENT_DINHEIRO;

        $id = app(VendusPaymentMethodResolver::class)->resolvePaymentMethodIdForSale($sale);

        $this->assertSame('7', $id);
    }

    public function test_cartao_prefers_cc_over_cd(): void
    {
        config([
            'services.vendus.api_key' => 'k',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.payment_method_id' => null,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 2, 'title' => 'Débito', 'type' => 'CD', 'status' => 'on'],
                ['id' => 1, 'title' => 'Crédito', 'type' => 'CC', 'status' => 'on'],
            ], 200),
        ]);

        $sale = new Sale();
        $sale->id = 1;
        $sale->payment_method = Sale::PAYMENT_CARTAO;

        $id = app(VendusPaymentMethodResolver::class)->resolvePaymentMethodIdForSale($sale);

        $this->assertSame('1', $id);
    }

    public function test_mbway_falls_back_to_cc_when_mbway_type_absent(): void
    {
        config([
            'services.vendus.api_key' => 'k',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.payment_method_id' => null,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 20, 'title' => 'Dinheiro', 'type' => 'NU', 'status' => 'on'],
                ['id' => 11, 'title' => 'Cartão de Crédito', 'type' => 'CC', 'status' => 'on'],
            ], 200),
        ]);

        $sale = new Sale();
        $sale->id = 2;
        $sale->payment_method = Sale::PAYMENT_MBWAY;

        $id = app(VendusPaymentMethodResolver::class)->resolvePaymentMethodIdForSale($sale);

        $this->assertSame('11', $id);
    }
}
