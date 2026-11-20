<?php

namespace Tests\Unit\Support;

use App\Models\Organization;
use App\Models\Store;
use App\Support\MarcacaoMailCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandAndStoreNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_name_uses_company_only(): void
    {
        $org = Organization::query()->create([
            'name' => 'FadaStudio',
            'slug' => 'fada-studio',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Aveiro Centro',
            'slug' => 'aveiro-centro',
            'timezone' => 'Europe/Lisbon',
        ]);

        $this->assertSame('FadaStudio', MarcacaoMailCopy::organizationName($store));
        $this->assertSame('FadaStudio', MarcacaoMailCopy::storeName($store));
        $this->assertSame('Lembrete de marcação - FadaStudio', MarcacaoMailCopy::subject('Lembrete de marcação', $store));
        $this->assertSame('FadaStudio Aveiro Centro', MarcacaoMailCopy::brandAndStoreName($store));
    }

    public function test_does_not_duplicate_when_names_match(): void
    {
        $org = Organization::query()->create([
            'name' => 'SoftRace',
            'slug' => 'softrace-dup',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'SoftRace',
            'slug' => 'softrace-dup-store',
            'timezone' => 'Europe/Lisbon',
        ]);

        $this->assertSame('SoftRace', MarcacaoMailCopy::brandAndStoreName($store));
    }
}
