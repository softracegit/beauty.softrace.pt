<?php

namespace Tests\Unit;

use App\Support\BookingTheme;
use PHPUnit\Framework\TestCase;

class BookingThemeTest extends TestCase
{
    public function test_registry_contains_all_themes(): void
    {
        $registry = BookingTheme::registry();

        $this->assertArrayHasKey(BookingTheme::DEFAULT, $registry);
        $this->assertArrayHasKey(BookingTheme::ELEGANT, $registry);
        $this->assertArrayHasKey(BookingTheme::NOIR, $registry);
        $this->assertNull($registry[BookingTheme::DEFAULT]['css']);
        $this->assertIsArray($registry[BookingTheme::ELEGANT]['css']);
        $this->assertIsArray($registry[BookingTheme::NOIR]['css']);
    }

    public function test_resolve_falls_back_to_default_for_unknown_theme(): void
    {
        $this->assertSame(BookingTheme::DEFAULT, BookingTheme::resolve('tema-inexistente'));
        $this->assertSame(BookingTheme::DEFAULT, BookingTheme::resolve(''));
    }

    public function test_resolve_accepts_known_themes(): void
    {
        $this->assertSame(BookingTheme::ELEGANT, BookingTheme::resolve(BookingTheme::ELEGANT));
        $this->assertSame(BookingTheme::NOIR, BookingTheme::resolve(BookingTheme::NOIR));
    }

    public function test_uses_refined_layout_for_elegant_and_noir(): void
    {
        $this->assertTrue(BookingTheme::usesRefinedLayout(BookingTheme::ELEGANT));
        $this->assertTrue(BookingTheme::usesRefinedLayout(BookingTheme::NOIR));
        $this->assertFalse(BookingTheme::usesRefinedLayout(BookingTheme::DEFAULT));
    }

    public function test_css_files_in_registry(): void
    {
        $registry = BookingTheme::registry();

        $this->assertNull($registry[BookingTheme::DEFAULT]['css']);
        $this->assertCount(2, $registry[BookingTheme::ELEGANT]['css']);
        $this->assertCount(2, $registry[BookingTheme::NOIR]['css']);
        $this->assertContains('booking-assets/css/themes/noir.css', $registry[BookingTheme::NOIR]['css']);
    }
}
