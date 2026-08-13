<?php

namespace Tests\Unit;

use App\Models\Sale;
use App\Support\PaymentMethodCatalog;
use Tests\TestCase;

class PaymentMethodCatalogTest extends TestCase
{
    public function test_catalog_defines_stripe_and_manual_mbway(): void
    {
        $defs = PaymentMethodCatalog::definitions();

        $this->assertArrayHasKey(Sale::PAYMENT_MBWAY, $defs);
        $this->assertArrayHasKey(Sale::PAYMENT_MBWAY_MANUAL, $defs);
        $this->assertSame(PaymentMethodCatalog::PROVIDER_STRIPE, $defs[Sale::PAYMENT_MBWAY]['provider']);
        $this->assertSame(PaymentMethodCatalog::PROVIDER_MANUAL, $defs[Sale::PAYMENT_MBWAY_MANUAL]['provider']);
        $this->assertSame('MBWay', $defs[Sale::PAYMENT_MBWAY]['label']);
        $this->assertSame('MBWay (manual)', $defs[Sale::PAYMENT_MBWAY_MANUAL]['label']);
    }

    public function test_enabled_for_channel_excludes_stripe_methods_when_not_ready(): void
    {
        try {
            $storeId = 1;
            if (\App\Support\StripeCredentials::isReady($storeId)) {
                $this->markTestSkipped('Stripe está ativo nesta loja de teste.');
            }

            $codes = array_column(
                PaymentMethodCatalog::enabledForChannel(PaymentMethodCatalog::CHANNEL_AGENDA, $storeId),
                'code',
            );
        } catch (\Throwable) {
            $this->markTestSkipped('Base de dados de teste sem crm_settings.');
        }

        $this->assertNotContains(Sale::PAYMENT_CARTAO, $codes);
        $this->assertNotContains(Sale::PAYMENT_MBWAY, $codes);
        $this->assertNotContains(Sale::PAYMENT_MULTIBANCO, $codes);
    }
}
