<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientTechnicalSheetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, service: Service, event: CalendarEvent}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Ficha Técnica',
            'slug' => 'org-ficha-tecnica',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Ficha Técnica',
            'slug' => 'loja-ficha-tecnica',
        ]);
        $client = Client::query()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Cliente Ficha Técnica',
            'phone' => '912345678',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'allergies' => 'Alergia a PPD',
            'contraindications' => 'Evitar oxidante alto',
            'preferences_notes' => 'Sem amónia',
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Coloração',
            'duration' => 60,
            'price' => 40,
            'online_price' => 40,
            'sort_order' => 1,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff Ficha Técnica',
            'email' => 'staff-ficha-tecnica@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Ficha Técnica',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'user_id' => $staff->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Cliente Ficha Técnica - Coloração',
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

        return compact('store', 'staff', 'client', 'service', 'event');
    }

    public function test_client_show_includes_technical_sheet_tab_data(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('clientes.show', $fx['client']));

        $response->assertOk();
        $response->assertSee('Resumo', false);
        $response->assertSee('Alergia a PPD', false);
        $response->assertSee('Evitar oxidante alto', false);
        $response->assertSee('tab-resumo', false);
    }

    public function test_event_show_returns_client_alerts_and_technical_fields(): void
    {
        $fx = $this->fixture();
        $fx['event']->update([
            'technical_formula' => '6N + 20vol 30min',
            'technical_products' => 'Wella Koleston',
            'technical_notes' => 'Boa cobertura',
        ]);

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.events.show', $fx['event']));

        $response->assertOk();
        $response->assertJsonPath('client_allergies', 'Alergia a PPD');
        $response->assertJsonPath('client_contraindications', 'Evitar oxidante alto');
        $response->assertJsonPath('client_has_alerts', true);
        $response->assertJsonPath('client_service_preferences', 'Sem amónia');
        $response->assertJsonPath('technical_formula', '6N + 20vol 30min');
        $response->assertJsonPath('technical_products', 'Wella Koleston');
        $response->assertJsonPath('technical_notes', 'Boa cobertura');
    }

    public function test_calendar_events_include_client_has_alerts_flag(): void
    {
        $fx = $this->fixture();
        $start = $fx['event']->start_at->copy()->subHour();
        $end = $fx['event']->end_at->copy()->addHour();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->getJson(route('agenda.events', [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
            ]));

        $response->assertOk();
        $event = collect($response->json())->firstWhere('id', (string) $fx['event']->id);
        $this->assertNotNull($event);
        $this->assertTrue((bool) data_get($event, 'extendedProps.client_has_alerts'));
    }

    public function test_event_update_persists_technical_fields(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->putJson(route('agenda.events.update', $fx['event']), [
                'technical_formula' => '7.1 + 10vol',
                'technical_products' => 'Loreal Majirel',
                'technical_notes' => 'Raiz só',
            ]);

        $response->assertOk();
        $fx['event']->refresh();
        $this->assertSame('7.1 + 10vol', $fx['event']->technical_formula);
        $this->assertSame('Loreal Majirel', $fx['event']->technical_products);
        $this->assertSame('Raiz só', $fx['event']->technical_notes);
    }

    public function test_client_update_persists_allergies_and_contraindications(): void
    {
        $fx = $this->fixture();

        $response = $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->put(route('clientes.update', $fx['client']), [
                'name' => $fx['client']->name,
                'phone' => $fx['client']->phone,
                'allergies' => 'Látex',
                'contraindications' => 'Não usar henna',
                'preferences_notes' => 'Prefere manhã',
            ]);

        $response->assertRedirect();
        $fx['client']->refresh();
        $this->assertSame('Látex', $fx['client']->allergies);
        $this->assertSame('Não usar henna', $fx['client']->contraindications);
        $this->assertSame('Prefere manhã', $fx['client']->preferences_notes);
    }
}
