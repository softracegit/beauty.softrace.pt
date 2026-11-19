<?php

namespace Tests\Unit\Support;

use App\Support\PhoneDisplay;
use Tests\TestCase;

class SmsBlockedCallingCodeTest extends TestCase
{
    public function test_blocks_nanp_spain_and_brazil_calling_codes(): void
    {
        $this->assertTrue(PhoneDisplay::isBlockedForAlphanumericSender('+12025550123'));
        $this->assertTrue(PhoneDisplay::isBlockedForAlphanumericSender('+15551234567'));
        $this->assertTrue(PhoneDisplay::isBlockedForAlphanumericSender('+34612345678'));
        $this->assertTrue(PhoneDisplay::isBlockedForAlphanumericSender('+5511987654321'));
        $this->assertTrue(PhoneDisplay::isBlockedForAlphanumericSender('+5535997222330'));
    }

    public function test_allows_portugal_mobile(): void
    {
        $this->assertFalse(PhoneDisplay::isBlockedForAlphanumericSender('+351912345678'));
        $this->assertFalse(PhoneDisplay::isBlockedForAlphanumericSender('912345678'));
    }

    public function test_empty_phone_is_not_blocked(): void
    {
        $this->assertFalse(PhoneDisplay::isBlockedForAlphanumericSender(null));
        $this->assertFalse(PhoneDisplay::isBlockedForAlphanumericSender(''));
    }
}
