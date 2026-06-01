<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgendaFinalizeDraftInvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent}
     */
    private function fixture(): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Finalize',
            'slug' => 'org-finalize',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja',
            'slug' => 'loja-finalize',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
            'phone' => '+351912345678',
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Serviço',
            'duration' => 30,
            'price' => 20.0,
            'online_price' => 20.0,
            'sort_order' => 1,
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_COMPLETO,
            'title' => 'Marcação',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ]);
        CalendarEventService::query()->create([
            'calendar_event_id' => $event->id,
            'service_id' => $service->id,
            'duration' => 30,
            'price' => 20.0,
            'sort_order' => 0,
        ]);
        $staff = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff-finalize@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);

        return compact('store', 'staff', 'client', 'event');
    }

    public function test_finalize_draft_caixa_sale_sets_faturado(): void
    {
        $fixture = $this->fixture();
        $sale = Sale::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'numero_fatura' => 'FR DRAFT/1',
            'data_emissao' => now()->toDateString(),
            'total' => 20.0,
            'valor_pago' => 20.0,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_RASCUNHO,
            'issue_without_fiscal_id' => true,
        ]);

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('sales.finalize-invoice', $sale), [
                'invoice_fiscal_mode' => 'consumer',
                'invoice_delivery' => 'print',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice_status', Sale::INVOICE_STATUS_FATURADO)
            ->assertJsonPath('scope', Sale::SCOPE_CAIXA_LIQUIDACAO);

        $sale->refresh();
        $this->assertSame(Sale::INVOICE_STATUS_FATURADO, $sale->invoice_status);
    }

    public function test_finalize_draft_booking_reserva_sale(): void
    {
        $fixture = $this->fixture();
        $sale = Sale::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'numero_fatura' => 'FR PRE/1',
            'data_emissao' => now()->toDateString(),
            'total' => 5.0,
            'valor_pago' => 5.0,
            'payment_method' => Sale::PAYMENT_MBWAY,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_RASCUNHO,
            'issue_without_fiscal_id' => true,
        ]);

        $response = $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('sales.finalize-invoice', $sale), [
                'invoice_fiscal_mode' => 'consumer',
            ]);

        $response->assertOk()
            ->assertJsonPath('scope', Sale::SCOPE_BOOKING_RESERVA);

        $this->assertSame(Sale::INVOICE_STATUS_FATURADO, $sale->fresh()->invoice_status);
    }

    public function test_finalize_rejects_already_faturado(): void
    {
        $fixture = $this->fixture();
        $sale = Sale::query()->create([
            'store_id' => $fixture['store']->id,
            'calendar_event_id' => $fixture['event']->id,
            'client_id' => $fixture['client']->id,
            'numero_fatura' => 'FR OK/1',
            'data_emissao' => now()->toDateString(),
            'total' => 10.0,
            'valor_pago' => 10.0,
            'payment_method' => Sale::PAYMENT_DINHEIRO,
            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => Sale::INVOICE_STATUS_FATURADO,
        ]);

        $this->actingAs($fixture['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fixture['store']->id])
            ->postJson(route('sales.finalize-invoice', $sale), [
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Esta fatura já foi emitida.');
    }
}
