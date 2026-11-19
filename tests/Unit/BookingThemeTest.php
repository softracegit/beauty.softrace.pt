<?php

namespace Tests\Unit;

use App\Support\BookingTheme;
use PHPUnit\Framework\TestCase;

class BookingThemeTest extends TestCase
{
    public function test_registry_only_offers_minimal_and_classic(): void
    {
        $registry = BookingTheme::registry();

        $this->assertSame([BookingTheme::MINIMAL, BookingTheme::DEFAULT], array_keys($registry));
        $this->assertNull($registry[BookingTheme::DEFAULT]['css']);
        $this->assertIsArray($registry[BookingTheme::MINIMAL]['css']);
    }

    public function test_resolve_falls_back_to_minimal_for_unknown_or_removed_theme(): void
    {
        $this->assertSame(BookingTheme::MINIMAL, BookingTheme::resolve('tema-inexistente'));
        $this->assertSame(BookingTheme::MINIMAL, BookingTheme::resolve(''));
        $this->assertSame(BookingTheme::MINIMAL, BookingTheme::resolve(BookingTheme::ELEGANT));
        $this->assertSame(BookingTheme::MINIMAL, BookingTheme::resolve(BookingTheme::NOIR));
    }

    public function test_resolve_accepts_known_themes(): void
    {
        $this->assertSame(BookingTheme::DEFAULT, BookingTheme::resolve(BookingTheme::DEFAULT));
        $this->assertSame(BookingTheme::MINIMAL, BookingTheme::resolve(BookingTheme::MINIMAL));
    }

    public function test_uses_refined_layout_for_minimal_only(): void
    {
        $this->assertTrue(BookingTheme::usesRefinedLayout(BookingTheme::MINIMAL));
        $this->assertFalse(BookingTheme::usesRefinedLayout(BookingTheme::DEFAULT));
    }

    public function test_css_files_in_registry(): void
    {
        $registry = BookingTheme::registry();

        $this->assertNull($registry[BookingTheme::DEFAULT]['css']);
        $this->assertContains('booking-assets/css/themes/minimal.css', $registry[BookingTheme::MINIMAL]['css']);
    }
}
