<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgendaCheckoutFeesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent, service: Service, category: Category}
     */
    private function feesFixture(float $servicePrice = 15.0): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Fees',
            'slug' => 'org-fees',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Fees',
            'slug' => 'loja-fees',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Taxas',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'phone' => '+351912345679',
        ]);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Categoria',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço base',
            'duration' => 30,
            'price' => $servicePrice,
            'online_price' => $servicePrice,
            'sort_order' => 1,
        ]);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_AGENDADO,
            'title' => 'Marcação taxas',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);

        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => $servicePrice,
            'sort_order' => 0,
        ]);

        $staff = User::query()->create([
            'name' => 'Staff Fees',
            'email' => 'staff-fees@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Fees',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'event', 'service', 'category');
    }

    private function attachFeeToService(Service $service, string $name, float $price): Fee
    {
        $fee = Fee::query()->create([
            'store_id' => $service->store_id,
            'name' => $name,
            'price' => $price,
            'sort_order' => 1,
        ]);
        $service->fees()->attach($fee->id);

        return $fee;
    }

    public function test_checkout_deduplicates_same_fee_across_two_services(): void
    {
        $fixture = $this->feesFixture(10.0);
        $fee = $this->attachFeeToService($fixture['service'], 'Taxa única', 2.0);

        $serviceB = Service::query()->create([
            'store_id' => $fixture['store']->id,
            'category_id' => $fixture['category']->id,
            'name' => 'Serviço B',
            'duration' => 30,
            'price' => 12.0,
            'online_price' => 12.0,
            'sort_order' => 2,
        ]);
        $serviceB->fees()->attach($fee->id);

        CalendarEventService::query()->create([
            'calendar_event_id' => $fixture['event']->id,
            'service_id' => $serviceB->id,
            'duration' => 30,
            'price' => 12.0,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.checkout', $fixture['event']));

        $response->assertOk()
            ->assertJsonPath('apply_catalog_fees', true);

        $taxaLines = collect($response->json('items'))->where('tipo', SaleItem::TIPO_TAXA)->values();
        $this->assertCount(1, $taxaLines);
        $this->assertSame((int) $fee->id, (int) $taxaLines->first()['fee_id']);
        $this->assertEquals(2.0, (float) $taxaLines->first()['preco_unitario']);
    }

    public function test_checkout_includes_multiple_fees_on_same_service(): void
    {
        $fixture = $this->feesFixture(20.0);
        $feeA = $this->attachFeeToService($fixture['service'], 'Taxa A', 1.5);
        $feeB = $this->attachFeeToService($fixture['service'], 'Taxa B', 3.0);

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.checkout', $fixture['event']));

        $response->assertOk();
        $taxaLines = collect($response->json('items'))->where('tipo', SaleItem::TIPO_TAXA)->values();
        $this->assertCount(2, $taxaLines);
        $feeIds = $taxaLines->pluck('fee_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertEquals([(int) $feeA->id, (int) $feeB->id], $feeIds);
    }

    public function test_settled_marcacao_ignores_new_catalog_fees(): void
    {
        $fixture = $this->feesFixture(15.0);

        Sale::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'numero_fatura' => 'FR TEST/1',
            'data_emissao' => now()->toDateString(),
            'total' => 15.0,
            'valor_pago' => 15.0,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
        ]);

        $this->attachFeeToService($fixture['service'], 'Taxa nova', 2.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.events.show', $fixture['event']))
            ->assertOk()
            ->assertJsonPath('invoice_settled', true)
            ->assertJsonPath('apply_catalog_fees', false)
            ->assertJsonPath('amount_due', 0);
    }

    public function test_open_marcacao_includes_fees_in_checkout_and_amount_due(): void
    {
        $fixture = $this->feesFixture(15.0);
        $this->attachFeeToService($fixture['service'], 'Taxa checkout', 2.0);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.events.show', $fixture['event']))
            ->assertOk()
            ->assertJsonPath('invoice_settled', false)
            ->assertJsonPath('apply_catalog_fees', true)
            ->assertJsonPath('amount_due', 17);

        $checkout = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->getJson(route('agenda.checkout', $fixture['event']));

        $checkout->assertOk()
            ->assertJsonPath('apply_catalog_fees', true);

        $taxaLines = collect($checkout->json('items'))->where('tipo', SaleItem::TIPO_TAXA);
        $this->assertCount(1, $taxaLines);
    }
}
