<?php

namespace Tests\Feature\Agenda;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Activity;
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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\Support\OpensCashRegister;
use Tests\TestCase;

class MarcacaoPaymentActivityLogTest extends TestCase
{
    use OpensCashRegister;
    use RefreshDatabase;

    /**
     * @return array{store: Store, staff: User, client: Client, event: CalendarEvent}
     */
    private function fixture(float $servicePrice = 30.0): array
    {
        $org = Organization::query()->create([
            'name' => 'Org Payment Log',
            'slug' => 'org-payment-log',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Loja Payment Log',
            'slug' => 'loja-payment-log',
        ]);
        $client = Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente Payment Log',
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
            'price' => $servicePrice,
            'online_price' => $servicePrice,
            'sort_order' => 1,
        ]);
        $event = CalendarEvent::query()->create([
            'store_id' => $store->id,
            'client_id' => $client->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO,
            'status' => CalendarEvent::STATUS_CONFIRMADO,
            'title' => 'Marcação pagamento',
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
            'name' => 'Staff Payment Log',
            'email' => 'staff-payment-log@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        Agent::query()->create([
            'user_id' => $staff->id,
            'store_id' => $store->id,
            'name' => 'Staff Payment Log',
            'status' => Agent::STATUS_ACTIVE,
        ]);
        $staff->stores()->sync([$store->id]);
        $this->openCashRegisterForStore($staff, $store);

        return compact('store', 'staff', 'client', 'event');
    }

    private function activityDescriptions(CalendarEvent $event): array
    {
        return Activity::query()
            ->where('subject_type', $event->getMorphClass())
            ->where('subject_id', $event->id)
            ->orderBy('id')
            ->pluck('description')
            ->all();
    }

    public function test_checkout_logs_marcacao_paga_and_fatura_gerada_without_status_change_log(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'checkout_mode' => 'faturar',
                'items' => [[
                    'tipo' => 'servico',
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 30,
                    'subtotal' => 30,
                ]],
            ])
            ->assertOk();

        $descriptions = $this->activityDescriptions($fx['event']->fresh());

        $this->assertContains('Marcação paga', $descriptions);
        $this->assertContains('Fatura gerada', $descriptions);
        $this->assertNotContains('Estado da marcação alterado', $descriptions);
    }

    public function test_checkout_rascunho_logs_marcacao_paga_without_fatura_gerada(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.checkout.store'), [
                'event_id' => $fx['event']->id,
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
                'checkout_mode' => 'rascunho',
                'items' => [[
                    'tipo' => 'servico',
                    'descricao' => 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => 30,
                    'subtotal' => 30,
                ]],
            ])
            ->assertOk();

        $descriptions = $this->activityDescriptions($fx['event']->fresh());

        $this->assertContains('Marcação paga', $descriptions);
        $this->assertNotContains('Fatura gerada', $descriptions);
        $this->assertNotContains('Estado da marcação alterado', $descriptions);
    }

    public function test_deposit_logs_pre_pagamento_and_fatura_gerada(): void
    {
        Config::set('booking.deposit_percent', 20);
        $fx = $this->fixture(50.0);

        $this->actingAs($fx['staff'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->postJson(route('agenda.deposit.store', $fx['event']), [
                'payment_method' => Sale::PAYMENT_DINHEIRO,
                'invoice_fiscal_mode' => 'consumer',
            ])
            ->assertOk();

        $descriptions = $this->activityDescriptions($fx['event']->fresh());

        $this->assertContains('Pré-pagamento recebido', $descriptions);
        $this->assertContains('Fatura gerada', $descriptions);
    }
}
