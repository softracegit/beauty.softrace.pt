<?php

namespace Tests\Unit;

use App\Support\BookingLocale;
use Tests\TestCase;

class BookingLocaleTest extends TestCase
{
    public function test_from_phone_portugal_returns_pt(): void
    {
        $this->assertSame('pt', BookingLocale::fromPhone('+351912345678'));
        $this->assertSame('pt', BookingLocale::fromPhone('912345678'));
        $this->assertSame('pt', BookingLocale::fromPhone('+5511987654321'));
    }

    public function test_from_phone_non_portugal_returns_en(): void
    {
        $this->assertSame('en', BookingLocale::fromPhone('+441234567890'));
        $this->assertSame('en', BookingLocale::fromPhone('+12345678901'));
        $this->assertSame('en', BookingLocale::fromPhone('+4915123456789'));
        $this->assertSame('en', BookingLocale::fromPhone(null));
    }

    public function test_from_phone_spanish_and_latam_prefixes_return_es(): void
    {
        $this->assertSame('es', BookingLocale::fromPhone('+34612345678'));
        $this->assertSame('es', BookingLocale::fromPhone('+584121234567'));
        $this->assertSame('es', BookingLocale::fromPhone('+573001234567'));
        $this->assertSame('es', BookingLocale::fromPhone('+525512345678'));
        $this->assertSame('es', BookingLocale::fromPhone('+5491112345678'));
        $this->assertSame('es', BookingLocale::fromPhone('+59171234567'));
        $this->assertSame('es', BookingLocale::fromPhone('+56912345678'));
        $this->assertSame('es', BookingLocale::fromPhone('+593991234567'));
        $this->assertSame('es', BookingLocale::fromPhone('+51987654321'));
        $this->assertSame('es', BookingLocale::fromPhone('+59899123456'));
    }

    public function test_resolve_falls_back_to_default_for_unknown_locale(): void
    {
        $this->assertSame('pt', BookingLocale::resolve('fr'));
        $this->assertSame('en', BookingLocale::resolve('en'));
    }

    public function test_intl_locale_maps_booking_locales(): void
    {
        $this->assertSame('pt-PT', BookingLocale::intlLocale('pt'));
        $this->assertSame('en-GB', BookingLocale::intlLocale('en'));
        $this->assertSame('es-ES', BookingLocale::intlLocale('es'));
    }

    public function test_client_emails_stay_portuguese(): void
    {
        $this->assertSame('pt', BookingLocale::emailLocale());
    }
}
