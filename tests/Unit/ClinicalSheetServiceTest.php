<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\CategoryClinicalField;
use App\Models\Client;
use App\Models\Material;
use App\Models\Organization;
use App\Services\ClinicalSheetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Testes focados no serviço (schema mínimo) — evita RefreshDatabase completo
 * enquanto migrações legadas falham no SQLite in-memory.
 */
class ClinicalSheetServiceTest extends TestCase
{
    private ClinicalSheetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClinicalSheetService;
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('category_clinical_fields');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('organizations');

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('material_category_id')->nullable();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('brand', 128)->nullable();
            $table->string('finish', 64)->nullable();
            $table->string('hex', 7)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('category_clinical_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('key', 64);
            $table->string('label');
            $table->string('input_type', 32);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('event_type', 32);
            $table->string('status', 32)->nullable();
            $table->json('clinical_sheet')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_build_snapshot_stores_label_and_value_by_category(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $cat = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        CategoryClinicalField::query()->create([
            'category_id' => $cat->id,
            'organization_id' => $org->id,
            'key' => 'formato',
            'label' => 'Formato',
            'input_type' => 'select',
            'options' => ['Amêndoa', 'Oval'],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);

        $snap = $this->service->buildSnapshot([$cat->id], [
            'by_category' => [
                (string) $cat->id => ['formato' => 'Amêndoa'],
            ],
        ]);

        $this->assertSame('Amêndoa', data_get($snap, 'by_category.'.$cat->id.'.formato.value'));
        $this->assertSame('Formato', data_get($snap, 'by_category.'.$cat->id.'.formato.label'));
        $this->assertSame('select', data_get($snap, 'by_category.'.$cat->id.'.formato.type'));
    }

    public function test_snapshot_display_lines_keep_old_label_after_rename(): void
    {
        $lines = $this->service->snapshotDisplayLines([
            'by_category' => [
                '9' => [
                    'formato' => ['label' => 'Formato antigo', 'type' => 'select', 'value' => 'Oval'],
                ],
            ],
        ]);
        $this->assertCount(1, $lines);
        $this->assertSame('Formato antigo', $lines[0]['label']);
        $this->assertSame('Oval', $lines[0]['value']);
    }

    public function test_preferences_use_most_frequent_value_from_marcacoes(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org2', 'status' => 'active']);
        $cat = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        CategoryClinicalField::query()->create([
            'category_id' => $cat->id,
            'organization_id' => $org->id,
            'key' => 'formato',
            'label' => 'Formato',
            'input_type' => 'select',
            'options' => ['Amêndoa', 'Oval'],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);
        $client = Client::withoutEvents(fn () => Client::query()->create([
            'organization_id' => $org->id,
            'name' => 'Ana',
            'phone' => '912000000',
            'type' => 'potencial_cliente',
        ]));

        $sheet = fn (string $formato) => json_encode([
            'by_category' => [
                (string) $cat->id => ['formato' => ['label' => 'Formato', 'type' => 'select', 'value' => $formato]],
            ],
        ]);
        $insert = fn (string $formato, string $startAt, string $status = 'agendado') => DB::table('calendar_events')->insert([
            'client_id' => $client->id,
            'event_type' => 'marcacao',
            'status' => $status,
            'clinical_sheet' => $sheet($formato),
            'start_at' => $startAt,
        ]);

        $insert('Amêndoa', '2026-10-01 10:00:00');
        $insert('Amêndoa', '2026-10-08 10:00:00');
        $insert('Oval', '2026-10-15 10:00:00');
        $insert('Oval', '2026-10-20 10:00:00', 'cancelado');
        $insert('Oval', '2026-10-21 10:00:00', 'cancelado');

        $prefs = $this->service->preferencesPayload($client, [$cat->id]);
        $this->assertSame('Amêndoa', data_get($prefs, 'by_category.'.$cat->id.'.formato'));

        $insert('Oval', '2026-10-22 10:00:00');
        $prefs = $this->service->preferencesPayload($client, [$cat->id]);
        $this->assertSame('Oval', data_get($prefs, 'by_category.'.$cat->id.'.formato'), 'Empate → valor mais recente');
    }

    public function test_material_field_snapshot_stores_rich_value(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org-mat', 'status' => 'active']);
        $cat = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Cor',
            'sort_order' => 1,
        ]);
        $matCatId = DB::table('material_categories')->insertGetId([
            'organization_id' => $org->id,
            'name' => 'Pigmentos',
            'color' => '#bfdbfe',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $material = Material::withoutEvents(fn () => Material::query()->create([
            'organization_id' => $org->id,
            'material_category_id' => $matCatId,
            'name' => 'Ruby Red',
            'code' => 'RR-01',
            'brand' => 'Gelish',
            'finish' => 'creme',
            'hex' => '#9b1b30',
            'stock' => 12,
            'photo_path' => 'materials/ruby-red.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]));
        CategoryClinicalField::query()->create([
            'category_id' => $cat->id,
            'organization_id' => $org->id,
            'key' => 'pigmento',
            'label' => 'Pigmento',
            'input_type' => CategoryClinicalField::TYPE_MATERIAL,
            'options' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);

        $snap = $this->service->buildSnapshot([$cat->id], [
            'by_category' => [
                (string) $cat->id => ['pigmento' => $material->id],
            ],
        ]);

        $value = data_get($snap, 'by_category.'.$cat->id.'.pigmento.value');
        $this->assertSame(CategoryClinicalField::TYPE_MATERIAL, data_get($snap, 'by_category.'.$cat->id.'.pigmento.type'));
        $this->assertSame($material->id, data_get($value, 'id'));
        $this->assertSame('Ruby Red', data_get($value, 'name'));
        $this->assertSame('RR-01', data_get($value, 'code'));
        $this->assertSame('Gelish', data_get($value, 'brand'));
        $this->assertSame('creme', data_get($value, 'finish'));
        $this->assertSame('Creme', data_get($value, 'finish_label'));
        $this->assertSame('#9b1b30', data_get($value, 'hex'));
        $this->assertSame('materials/ruby-red.jpg', data_get($value, 'photo_path'));
        $this->assertArrayNotHasKey('stock', $value);
        $this->assertArrayNotHasKey('photo_url', $value);
        $this->assertSame(
            'Ruby Red (RR-01) · Gelish · Creme',
            $this->service->formatDisplayValue($value)
        );
    }

    public function test_preferences_merge_same_material_despite_volatile_stock(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org-mat-pref', 'status' => 'active']);
        $cat = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Cor',
            'sort_order' => 1,
        ]);
        $matCatId = DB::table('material_categories')->insertGetId([
            'organization_id' => $org->id,
            'name' => 'Pigmentos',
            'color' => '#bfdbfe',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ruby = Material::withoutEvents(fn () => Material::query()->create([
            'organization_id' => $org->id,
            'material_category_id' => $matCatId,
            'name' => 'Ruby Red',
            'code' => 'RR-01',
            'brand' => 'Gelish',
            'finish' => 'creme',
            'hex' => '#9b1b30',
            'stock' => 5,
            'sort_order' => 1,
            'is_active' => true,
        ]));
        $nude = Material::withoutEvents(fn () => Material::query()->create([
            'organization_id' => $org->id,
            'material_category_id' => $matCatId,
            'name' => 'Soft Nude',
            'code' => 'SN-02',
            'brand' => 'OPI',
            'finish' => 'sheer',
            'hex' => '#e8c4b8',
            'stock' => 8,
            'sort_order' => 2,
            'is_active' => true,
        ]));
        CategoryClinicalField::query()->create([
            'category_id' => $cat->id,
            'organization_id' => $org->id,
            'key' => 'pigmento',
            'label' => 'Pigmento',
            'input_type' => CategoryClinicalField::TYPE_MATERIAL,
            'options' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);
        $client = Client::withoutEvents(fn () => Client::query()->create([
            'organization_id' => $org->id,
            'name' => 'Bea',
            'phone' => '913000000',
            'type' => 'potencial_cliente',
        ]));

        $sheet = fn (array $materialValue) => json_encode([
            'by_category' => [
                (string) $cat->id => [
                    'pigmento' => [
                        'label' => 'Pigmento',
                        'type' => CategoryClinicalField::TYPE_MATERIAL,
                        'value' => $materialValue,
                    ],
                ],
            ],
        ]);
        $insert = fn (array $materialValue, string $startAt) => DB::table('calendar_events')->insert([
            'client_id' => $client->id,
            'event_type' => 'marcacao',
            'status' => 'agendado',
            'clinical_sheet' => $sheet($materialValue),
            'start_at' => $startAt,
        ]);

        // Mesmo material com stock diferente em snapshots legados — deve contar como 1 preferência
        $insert($ruby->toChoiceArray(), '2026-10-01 10:00:00');
        $ruby->stock = 2;
        $insert($ruby->toChoiceArray(), '2026-10-08 10:00:00');
        $insert($nude->toChoiceArray(), '2026-10-15 10:00:00');

        $prefs = $this->service->preferencesPayload($client, [$cat->id]);
        $prefValue = data_get($prefs, 'by_category.'.$cat->id.'.pigmento');

        $this->assertSame($ruby->id, data_get($prefValue, 'id'));
        $this->assertSame('Ruby Red', data_get($prefValue, 'name'));
        $this->assertArrayNotHasKey('stock', $prefValue);
        $this->assertArrayNotHasKey('photo_url', $prefValue);
    }

    public function test_archived_material_still_snapshots_and_validates(): void
    {
        $org = Organization::query()->create(['name' => 'Org', 'slug' => 'org-arch', 'status' => 'active']);
        $cat = Category::query()->create([
            'organization_id' => $org->id,
            'name' => 'Cor',
            'sort_order' => 1,
        ]);
        $matCatId = DB::table('material_categories')->insertGetId([
            'organization_id' => $org->id,
            'name' => 'Pigmentos',
            'color' => '#bfdbfe',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $material = Material::withoutEvents(fn () => Material::query()->create([
            'organization_id' => $org->id,
            'material_category_id' => $matCatId,
            'name' => 'Ruby Red',
            'code' => 'RR-01',
            'hex' => '#9b1b30',
            'sort_order' => 1,
            'is_active' => true,
        ]));
        CategoryClinicalField::query()->create([
            'category_id' => $cat->id,
            'organization_id' => $org->id,
            'key' => 'pigmento',
            'label' => 'Pigmento',
            'input_type' => CategoryClinicalField::TYPE_MATERIAL,
            'options' => [$material->id],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);

        Material::withoutEvents(fn () => $material->delete());

        $snap = $this->service->buildSnapshot([$cat->id], [
            'by_category' => [
                (string) $cat->id => ['pigmento' => $material->id],
            ],
        ]);
        $this->assertSame($material->id, data_get($snap, 'by_category.'.$cat->id.'.pigmento.value.id'));
        $this->assertTrue((bool) data_get($snap, 'by_category.'.$cat->id.'.pigmento.value.archived'));

        $this->service->validateSubmitted([$cat->id], [
            'by_category' => [
                (string) $cat->id => ['pigmento' => $material->id],
            ],
        ]);

        $usages = Material::withTrashed()->find($material->id)?->clinicalFieldUsages() ?? [];
        $this->assertCount(1, $usages);
        $this->assertSame('Pigmento', $usages[0]['field_label']);
        $this->assertSame('Cor', $usages[0]['category_name']);
    }
}
