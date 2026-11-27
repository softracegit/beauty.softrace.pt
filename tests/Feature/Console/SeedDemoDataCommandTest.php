<?php

namespace Tests\Feature\Console;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_demo_store_and_admin_and_api_serves_it(): void
    {
        $this->artisan('demo:seed', ['--password' => 'segredo-demo-123'])->assertSuccessful();

        $this->assertTrue(Store::query()->where('slug', 'aveiro-centro')->exists());
        $this->assertTrue(User::query()->where('email', 'admin@demo.test')->exists());

        $this->getJson('/api/v1/stores/aveiro-centro/catalog')
            ->assertOk()
            ->assertJsonPath('data.categories.0.name', 'Manicure');

        // Segunda execução recusa (não duplica dados)
        $this->artisan('demo:seed')->assertFailed();
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:seed')->assertFailed();
        $this->assertFalse(Store::query()->where('slug', 'aveiro-centro')->exists());
    }
}
