<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_legal_terms_page_is_accessible(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Termos e Condições', false);
    }

    public function test_legal_privacy_page_is_accessible(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Política de Privacidade', false);
    }

    public function test_legal_cookies_page_is_accessible(): void
    {
        $this->get(route('legal.cookies'))
            ->assertOk()
            ->assertSee('Política de Cookies', false);
    }
}
