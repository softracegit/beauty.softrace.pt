<?php

namespace Tests\Unit\Support;

use App\Support\ClientContactMask;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientContactMaskTest extends TestCase
{
    #[Test]
    public function phone_keeps_only_last_three_digits(): void
    {
        $this->assertSame('******789', ClientContactMask::phone('+351 912 345 789'));
        $this->assertSame('', ClientContactMask::phone(null));
        $this->assertSame('', ClientContactMask::phone(''));
    }

    #[Test]
    public function email_keeps_first_and_last_local_letters(): void
    {
        $this->assertSame('j***a@e***.pt', ClientContactMask::email('joana@empresa.pt'));
        $this->assertSame('a***@e***.com', ClientContactMask::email('a@exemplo.com'));
        $this->assertSame('', ClientContactMask::email('invalid'));
        $this->assertSame('', ClientContactMask::email(null));
    }

    #[Test]
    public function nif_keeps_last_three_digits(): void
    {
        $this->assertSame('***456', ClientContactMask::nif('123456'));
        $this->assertSame('***', ClientContactMask::nif('12'));
    }
}
