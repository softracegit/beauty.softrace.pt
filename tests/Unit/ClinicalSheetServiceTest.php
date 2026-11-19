<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\CategoryClinicalField;
use App\Models\Client;
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
}
