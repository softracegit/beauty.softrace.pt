<?php

namespace Tests\Unit;

use App\Support\PhoneDisplay;
use PHPUnit\Framework\TestCase;

class PhoneDisplayTest extends TestCase
{
    public function test_to_e164_normalizes_brazil_legacy_mobile_with_eight_digits(): void
    {
        $this->assertSame('+5535997222330', PhoneDisplay::toE164('+553597222330'));
        $this->assertSame('+5535997222330', PhoneDisplay::toE164('+55 35 9722-2330'));
    }

    public function test_to_e164_keeps_valid_brazil_mobile_unchanged(): void
    {
        $this->assertSame('+5535997222330', PhoneDisplay::toE164('+5535997222330'));
    }

    public function test_to_e164_does_not_normalize_brazil_landline(): void
    {
        $this->assertSame('+553532345678', PhoneDisplay::toE164('+553532345678'));
    }

    public function test_to_e164_keeps_portugal_mobile(): void
    {
        $this->assertSame('+351912345678', PhoneDisplay::toE164('+351912345678'));
    }
}
