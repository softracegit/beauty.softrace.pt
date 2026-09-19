<?php

namespace Tests\Unit;

use App\Services\StoreBusinessSettingsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreBusinessSettingsServiceTest extends TestCase
{
    #[Test]
    public function resolve_active_tab_defaults_and_accepts_known_tabs(): void
    {
        $svc = $this->app->make(StoreBusinessSettingsService::class);

        $this->assertSame('dados', $svc->resolveActiveTab(null));
        $this->assertSame('dados', $svc->resolveActiveTab('xyz'));
        $this->assertSame('logotipos', $svc->resolveActiveTab('logotipos'));
        $this->assertSame('horario', $svc->resolveActiveTab('horario'));
        $this->assertSame('privacidade', $svc->resolveActiveTab('privacidade'));
        $this->assertSame('emails', $svc->resolveActiveTab('emails'));
    }

    #[Test]
    public function tab_for_field_name_maps_required_fields(): void
    {
        $svc = $this->app->make(StoreBusinessSettingsService::class);

        $this->assertSame('dados', $svc->tabForFieldName('maps_url'));
        $this->assertSame('logotipos', $svc->tabForFieldName('logo'));
        $this->assertSame('horario', $svc->tabForFieldName('weekly_schedule.mon.start'));
        $this->assertSame('privacidade', $svc->tabForFieldName('privacy_lock_pin'));
        $this->assertSame('emails', $svc->tabForFieldName('email_use_business_branding'));
    }

    #[Test]
    public function resolve_active_tab_prefers_validation_error_tab(): void
    {
        $svc = $this->app->make(StoreBusinessSettingsService::class);

        $messages = new \Illuminate\Support\MessageBag([
            'privacy_lock_pin' => ['Defina o PIN de desbloqueio do posto (4 dígitos).'],
        ]);
        $bag = (new \Illuminate\Support\ViewErrorBag)->put('default', $messages);
        session()->put([
            'errors' => $bag,
            '_old_input' => ['_active_tab' => 'dados'],
        ]);

        $this->assertSame('privacidade', $svc->resolveActiveTab('dados'));
    }

    #[Test]
    public function store_override_or_null_inherits_when_equal_or_empty(): void
    {
        $svc = $this->app->make(StoreBusinessSettingsService::class);
        $method = new \ReflectionMethod(StoreBusinessSettingsService::class, 'storeOverrideOrNull');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($svc, '+351 234 000 000', '+351 234 000 000'));
        $this->assertNull($method->invoke($svc, '  +351 234 000 000  ', '+351 234 000 000'));
        $this->assertNull($method->invoke($svc, '', '+351 234 000 000'));
        $this->assertNull($method->invoke($svc, null, '+351 234 000 000'));
        $this->assertSame('+351 911 000 000', $method->invoke($svc, '+351 911 000 000', '+351 234 000 000'));
        $this->assertSame('loja@test.pt', $method->invoke($svc, 'loja@test.pt', null));
    }
}
