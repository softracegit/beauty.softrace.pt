<?php

namespace Tests\Unit;

use App\Support\PortugueseFiscalId;
use PHPUnit\Framework\TestCase;

class PortugueseFiscalIdTest extends TestCase
{
    public function test_detects_company_nif_by_first_digit(): void
    {
        $this->assertTrue(PortugueseFiscalId::isCompany('500000000'));
        $this->assertTrue(PortugueseFiscalId::isCompany('600000000'));
        $this->assertFalse(PortugueseFiscalId::isCompany('123456789'));
        $this->assertFalse(PortugueseFiscalId::isCompany('999999990'));
        $this->assertFalse(PortugueseFiscalId::isCompany(''));
        $this->assertFalse(PortugueseFiscalId::isCompany('50'));
    }
}
