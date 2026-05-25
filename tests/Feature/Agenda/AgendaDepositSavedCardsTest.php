<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\BookingSavedCard;
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

class AgendaDepositSavedCardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent, service: Service}
     */
    private function agendaDepositFixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Agenda',
            'slug' => 'org-agenda-cards',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Agenda',
            'slug' => 'loja-agenda-cards',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Cartão',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'stripe_customer_id' => 'cus_test_123',
        ]);
        $otherClient = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Outro Cliente',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]);

        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $cat->id,
            'name' => 'Corte',
            'duration' => 30,
            'price' => 50,
            'online_price' => 50,
            'sort_order' => 1,
        ]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação cartão',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);

        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 50,
            'sort_order' => 0,
        ]);

        $staff = User::query()->create([
            'name' => 'Staff Agenda',
            'email' => 'staff-agenda-cards@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Agenda',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        BookingSavedCard::query()->create([
            'client_id' => $client->id,
            'stripe_customer_id' => 'cus_test_123',
            'stripe_payment_method_id' => 'pm_test_visa',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
        ]);

        BookingSavedCard::query()->create([
            'client_id' => $otherClient->id,
            'stripe_customer_id' => 'cus_other',
            'stripe_payment_method_id' => 'pm_test_other',
            'brand' => 'mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2029,
            'is_default' => true,
        ]);

        return compact('store', 'staff', 'client', 'event', 'service');
    }

    public function test_client_saved_cards_endpoint_returns_active_cards(): void
    {
        $fixture = $this->agendaDepositFixture();

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.clients.saved_cards', $fixture['client']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'cards')
            ->assertJsonPath('cards.0.brand', 'visa')
            ->assertJsonPath('cards.0.last4', '4242')
            ->assertJsonPath('cards.0.is_default', true);
    }

    public function test_client_saved_cards_endpoint_404_for_other_store(): void
    {
        $fixture = $this->agendaDepositFixture();
        $otherStore = Store::query()->create([
            'organization_id' => $fixture['store']->organization_id,
            'name' => 'Outra Loja',
            'slug' => 'outra-loja-cards',
        ]);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $otherStore->id])
            ->getJson(route('agenda.clients.saved_cards', $fixture['client']))
            ->assertNotFound();
    }

    public function test_deposit_card_rejects_saved_card_from_other_client(): void
    {
        $fixture = $this->agendaDepositFixture();
        $foreignCardId = BookingSavedCard::query()
            ->where('stripe_payment_method_id', 'pm_test_other')
            ->value('id');

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.card', $fixture['event']), [
                'saved_card_id' => $foreignCardId,
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['saved_card_id']);
    }

    public function test_deposit_card_requires_stripe_customer(): void
    {
        $fixture = $this->agendaDepositFixture();
        $fixture['client']->forceFill(['stripe_customer_id' => null])->save();

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('agenda.deposit.card', $fixture['event']), [
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Este cliente não tem cartão guardado no sistema de pagamentos.');
    }
}
