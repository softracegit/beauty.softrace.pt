<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\CategoryClinicalField;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClinicalSheetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, service: Service, category: Category, event: CalendarEvent, field: CategoryClinicalField}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Clinical',
            'slug' => 'org-clinical',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Clinical',
            'slug' => 'loja-clinical',
        ]);
        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Cliente Clinical',
            'phone' => '912345679',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);
        $category = Category::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Unhas',
            'sort_order' => 1,
        ]);
        $field = CategoryClinicalField::query()->create([
            'category_id' => $category->id,
            'organization_id' => $org->id,
            'key' => 'formato',
            'label' => 'Formato',
            'input_type' => CategoryClinicalField::TYPE_SELECT,
            'options' => ['Amêndoa', 'Oval'],
            'sort_order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Manicure Gel',
            'duration' => 60,
            'price' => 40,
            'online_price' => 40,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff Clinical',
            'email' => 'staff-clinical@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Clinical',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $staff->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Cliente Clinical - Manicure Gel',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(60),
            'service_id' => $service->id,
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 60,
            'price' => 40,
            'sort_order' => 0,
        ]);

        return compact('store', 'staff', 'client', 'service', 'category', 'event', 'field');
    }

    public function test_category_clinical_field_crud(): void
    {
        $fx = $this->fixture();

        $create = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('categories.clinical-fields.store', $fx['category']), [
                'label' => 'Cor',
                'input_type' => 'text',
            ]);
        $create->assertCreated();
        $create->assertJsonPath('field.key', 'cor');

        $show = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('categories.show', $fx['category']));
        $show->assertOk();
        $this->assertGreaterThanOrEqual(2, count($show->json('clinical_fields')));
    }

    public function test_event_update_persists_clinical_sheet_snapshot(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->putJson(route('agenda.events.update', $fx['event']), [
                'clinical_sheet' => [
                    'by_category' => [
                        (string) $fx['category']->id => [
                            'formato' => 'Amêndoa',
                        ],
                    ],
                ],
            ]);

        $response->assertOk();
        $fx['event']->refresh();
        $sheet = $fx['event']->clinical_sheet;
        $this->assertIsArray($sheet);
        $this->assertSame('Amêndoa', data_get($sheet, 'by_category.'.$fx['category']->id.'.formato.value'));
        $this->assertSame('Formato', data_get($sheet, 'by_category.'.$fx['category']->id.'.formato.label'));
    }

    public function test_event_show_includes_clinical_schema_and_sheet(): void
    {
        $fx = $this->fixture();
        $fx['event']->update([
            'clinical_sheet' => [
                'by_category' => [
                    (string) $fx['category']->id => [
                        'formato' => ['label' => 'Formato', 'type' => 'select', 'value' => 'Oval'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.events.show', $fx['event']));

        $response->assertOk();
        $response->assertJsonPath('clinical_sheet.by_category.'.$fx['category']->id.'.formato.value', 'Oval');
        $this->assertNotEmpty($response->json('clinical_field_schema.categories'));
    }

    public function test_ficha_tecnica_keeps_snapshot_label_after_rename(): void
    {
        $fx = $this->fixture();

        $fx['event']->update([
            'clinical_sheet' => [
                'by_category' => [
                    (string) $fx['category']->id => [
                        'formato' => ['label' => 'Formato antigo', 'type' => 'select', 'value' => 'Oval'],
                    ],
                ],
            ],
        ]);
        $fx['field']->update(['label' => 'Formato das unhas']);

        // Timeline partial reads snapshot labels (survives schema rename); avoid clientes.show
        // which uses MySQL-only DAYOFWEEK/HOUR in buildClientStats.
        $event = $fx['event']->fresh(['eventServiceItems.service', 'user', 'sales']);
        $html = view('clientes.partials.ficha-tecnica', [
            'cliente' => $fx['client'],
            'marcacoes' => collect([$event]),
            'stats' => null,
        ])->render();

        $this->assertStringContainsString('Formato antigo', $html);
        $this->assertStringContainsString('Oval', $html);
        $this->assertStringNotContainsString('Formato das unhas', $html);
    }

    public function test_clinical_schema_endpoint(): void
    {
        $fx = $this->fixture();
        $fx['event']->update([
            'clinical_sheet' => [
                'by_category' => [
                    (string) $fx['category']->id => [
                        'formato' => ['label' => 'Formato', 'type' => 'select', 'value' => 'Amêndoa'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.clinical_schema', [
                'service_ids' => [$fx['service']->id],
                'client_id' => $fx['client']->id,
            ]));

        $response->assertOk();
        $response->assertJsonPath('client_clinical_preferences.by_category.'.$fx['category']->id.'.formato', 'Amêndoa');
        $this->assertTrue((bool) $response->json('clinical_field_schema.has_session_fields'));
    }
}
