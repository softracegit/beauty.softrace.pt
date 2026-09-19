<?php

namespace Tests\Unit;

use App\Support\StoreContextPreference;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreContextPreferenceTest extends TestCase
{
    #[Test]
    public function parse_store_id_param_accepts_positive_integers(): void
    {
        $this->assertSame(3, StoreContextPreference::parseStoreIdParam('3'));
        $this->assertSame(12, StoreContextPreference::parseStoreIdParam(12));
        $this->assertNull(StoreContextPreference::parseStoreIdParam('todas'));
        $this->assertNull(StoreContextPreference::parseStoreIdParam(''));
        $this->assertNull(StoreContextPreference::parseStoreIdParam(null));
        $this->assertNull(StoreContextPreference::parseStoreIdParam(0));
    }

    #[Test]
    public function is_all_scope_param_detects_todas(): void
    {
        $this->assertTrue(StoreContextPreference::isAllScopeParam('todas'));
        $this->assertTrue(StoreContextPreference::isAllScopeParam(' Todas '));
        $this->assertFalse(StoreContextPreference::isAllScopeParam('3'));
        $this->assertFalse(StoreContextPreference::isAllScopeParam(null));
        $this->assertFalse(StoreContextPreference::isAllScopeParam(''));
    }
}
