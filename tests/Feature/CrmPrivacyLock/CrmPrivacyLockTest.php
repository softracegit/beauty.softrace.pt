<?php

namespace Tests\Feature\CrmPrivacyLock;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmPrivacyLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Lock',
            'slug' => 'org-lock',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Lock',
            'slug' => 'loja-lock',
        ]);
        $staff = User::query()->create([
            'name' => 'Admin Lock',
            'email' => 'admin-lock@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Admin Lock',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Lock',
            'email' => 'maria@gmail.com',
            'phone' => '+351912345678',
            'nif' => '123456789',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço',
            'duration' => 30,
            'price' => 20,
            'online_price' => 20,
            'sort_order' => 1,
        ]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_CHEGOU,
            'title' => 'Marcação lock',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(30),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 20,
            'sort_order' => 0,
        ]);

        CrmSetting::setPrivacyLockPinHash(Hash::make('1234'), (int) $store->id);
        CrmSetting::setPrivacyLockIdleMinutes(5, (int) $store->id);

        return compact('store', 'staff', 'client', 'event');
    }

    public function test_lock_and_unlock_require_valid_pin(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('crm-privacy-lock.lock'))
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('crm-privacy-lock.unlock'), ['pin' => '9999'])
            ->assertStatus(422);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('crm-privacy-lock.unlock'), ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('locked', false);
    }

    public function test_client_pages_are_blocked_while_crm_is_locked(): void
    {
        $fx = $this->fixture();

        $session = [
            SetCurrentStore::SESSION_KEY => $fx['store']->id,
            'crm_privacy_locked' => true,
        ];

        $this->actingAs($fx['staff'])
            ->withSession($session)
            ->get(route('clientes.index'))
            ->assertRedirect(route('agenda.index'));
    }

    public function test_agenda_event_payload_masks_pii_while_locked(): void
    {
        $fx = $this->fixture();

        $session = [
            SetCurrentStore::SESSION_KEY => $fx['store']->id,
            'crm_privacy_locked' => true,
        ];

        $response = $this->actingAs($fx['staff'])
            ->withSession($session)
            ->getJson(route('agenda.events.show', $fx['event']));

        $response->assertOk();
        $response->assertJsonPath('client_email', 'm***@g***.com');
        $response->assertJsonPath('client_phone', '***678');
        $response->assertJsonPath('client_nif', '***789');
    }

    public function test_checkout_rejects_rascunho_when_locked(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->post(route('caixa.open'), ['opening_float' => '100.00']);

        $response = $this->actingAs($fx['staff'])
            ->withSession([
                SetCurrentStore::SESSION_KEY => $fx['store']->id,
                'crm_privacy_locked' => true,
            ])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'items' => [[
                    'tipo' => 'servico',
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 20,
                ]],
                'payment_method' => 'dinheiro',
                'invoice_fiscal_mode' => 'consumer',
                'checkout_mode' => 'rascunho',
            ]);

        $response->assertStatus(403);
    }
}
