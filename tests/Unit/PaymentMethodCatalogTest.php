<?php

namespace Tests\Unit;

use App\Models\CrmSetting;
use App\Models\Sale;
use App\Support\PaymentMethodCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentMethodCatalogTest extends TestCase
{
    #[Test]
    public function catalog_defines_stripe_and_manual_mbway(): void
    {
        $defs = PaymentMethodCatalog::definitions();

        $this->assertArrayHasKey(Sale::PAYMENT_MBWAY, $defs);
        $this->assertArrayHasKey(Sale::PAYMENT_MBWAY_MANUAL, $defs);
        $this->assertSame(PaymentMethodCatalog::PROVIDER_STRIPE, $defs[Sale::PAYMENT_MBWAY]['provider']);
        $this->assertSame(PaymentMethodCatalog::PROVIDER_MANUAL, $defs[Sale::PAYMENT_MBWAY_MANUAL]['provider']);
        $this->assertSame('MBWay', $defs[Sale::PAYMENT_MBWAY]['label']);
        $this->assertSame('MBWay (manual)', $defs[Sale::PAYMENT_MBWAY_MANUAL]['label']);
    }

    #[Test]
    public function stripe_and_payment_methods_are_organization_scoped(): void
    {
        $this->assertTrue(CrmSetting::isOrganizationScopedKey(CrmSetting::KEY_PAYMENT_METHODS));
        $this->assertTrue(CrmSetting::isOrganizationScopedKey(CrmSetting::KEY_STRIPE_ENABLED));
        $this->assertTrue(CrmSetting::isOrganizationScopedKey(CrmSetting::KEY_STRIPE_SECRET_KEY));
        $this->assertFalse(CrmSetting::isOrganizationScopedKey(CrmSetting::KEY_POS_GORJETA_ENABLED));
        $this->assertFalse(CrmSetting::isOrganizationScopedKey(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED));

        $this->assertSame('o:9:payments.methods', CrmSetting::organizationSettingScope(9, CrmSetting::KEY_PAYMENT_METHODS));
        $this->assertSame('s:3:pos.gorjeta_enabled', CrmSetting::storeSettingScope(3, CrmSetting::KEY_POS_GORJETA_ENABLED));
    }
}
