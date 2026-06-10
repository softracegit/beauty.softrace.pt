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
    }

    public function test_from_phone_non_portugal_returns_en(): void
    {
        $this->assertSame('en', BookingLocale::fromPhone('+441234567890'));
        $this->assertSame('en', BookingLocale::fromPhone('+12345678901'));
        $this->assertSame('en', BookingLocale::fromPhone(null));
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
    }

    public function test_client_emails_stay_portuguese(): void
    {
        $this->assertSame('pt', BookingLocale::emailLocale());
    }
}
