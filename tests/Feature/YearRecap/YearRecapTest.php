<?php

namespace Tests\Feature\YearRecap;

use App\Http\Middleware\SetCurrentStore;
use App\Models\Agent;
use App\Models\AgentCommissionPeriod;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\Store;
use App\Models\User;
use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\YearRecapBuilder;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class YearRecapTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $org = Organization::query()->create(['name' => 'Org Recap', 'slug' => 'org-recap', 'status' => 'active']);
        $store = Store::query()->create(['organization_id' => $org->id, 'name' => 'Studio Recap', 'slug' => 'studio-recap']);

        $catUnhas = Category::query()->create(['store_id' => $store->id, 'name' => 'Unhas', 'sort_order' => 1]);
        $catRosto = Category::query()->create(['store_id' => $store->id, 'name' => 'Rosto', 'sort_order' => 2]);
        $gel = Service::query()->create([
            'store_id' => $store->id, 'category_id' => $catUnhas->id, 'name' => 'Verniz Gel',
            'duration' => 60, 'price' => 25, 'online_price' => 25, 'sort_order' => 1,
        ]);
        $limpeza = Service::query()->create([
            'store_id' => $store->id, 'category_id' => $catRosto->id, 'name' => 'Limpeza de pele',
            'duration' => 90, 'price' => 45, 'online_price' => 45, 'sort_order' => 2,
        ]);

        $admin = $this->user('Admin Recap', User::ROLE_ADMIN, $org, $store);
        $rececao = $this->user('Rita Receção', User::ROLE_RECECAO, $org, $store);
        $ana = $this->user('Ana Ribeiro', User::ROLE_PRESTADOR, $org, $store, withAgent: true);
        $sara = $this->user('Sara Mendes', User::ROLE_PRESTADOR, $org, $store, withAgent: true);

        $clients = collect(range(1, 14))->map(fn ($i) => Client::query()->create([
            'store_id' => $store->id,
            'name' => 'Cliente '.$i.' Silva',
            'type' => Client::TYPE_POTENCIAL_CLIENTE,
        ]));

        // Ana: 30 marcações de gel (jun–out), clientes 1–10 voltam várias vezes.
        for ($i = 0; $i < 30; $i++) {
            $start = Carbon::parse('2026-06-06 10:00', 'Europe/Lisbon')->addDays($i * 4)->utc();
            $this->marcacao($store, $ana, $clients[$i % 10], $gel, $start, 60, 25.0, $i % 3 === 0 ? 'online' : 'agenda', $rececao);
        }
        // Sara: 15 limpezas, clientes 10–13.
        for ($i = 0; $i < 15; $i++) {
            $start = Carbon::parse('2026-06-07 15:00', 'Europe/Lisbon')->addDays($i * 7)->utc();
            $this->marcacao($store, $sara, $clients[10 + ($i % 4)], $limpeza, $start, 90, 45.0, 'agenda', $admin);
        }
        // Canceladas e faltas não contam.
        $this->marcacao($store, $ana, $clients[0], $gel, Carbon::parse('2026-07-01 09:00', 'UTC'), 60, null, 'agenda', null, CalendarEvent::STATUS_CANCELADO);
        $this->marcacao($store, $ana, $clients[0], $gel, Carbon::parse('2026-07-02 09:00', 'UTC'), 60, null, 'agenda', null, CalendarEvent::STATUS_FALTOU);

        foreach (range(1, 12) as $i) {
            SmsMessage::query()->create([
                'store_id' => $store->id, 'type' => SmsMessage::TYPE_BOOKING_REMINDER,
                'to_phone' => '+351910000000', 'from_phone' => 'Studio', 'body' => 'Lembrete',
                'sent_at' => Carbon::parse('2026-08-01 10:00', 'UTC')->addDays($i),
            ]);
        }

        return compact('org', 'store', 'admin', 'rececao', 'ana', 'sara');
    }

    private function user(string $name, string $role, Organization $org, Store $store, bool $withAgent = false): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => str()->slug($name).'@recap.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'organization_id' => $org->id,
        ]);
        $user->stores()->sync([$store->id]);
        if ($withAgent) {
            $agent = Agent::query()->create([
                'user_id' => $user->id, 'store_id' => $store->id, 'name' => $name,
                'status' => Agent::STATUS_ACTIVE,
                'commission_rate' => 40, 'commission_unit' => Agent::COMMISSION_UNIT_PERCENT,
                'commission_effective_from' => '2026-01-01',
            ]);
            AgentCommissionPeriod::query()->create([
                'agent_id' => $agent->id, 'rate' => 40, 'unit' => Agent::COMMISSION_UNIT_PERCENT, 'effective_from' => '2026-01-01',
            ]);
        }

        return $user;
    }

    private function marcacao(
        Store $store, User $tech, Client $client, Service $service, Carbon $startUtc, int $minutes,
        ?float $paid, string $source, ?User $createdBy, string $status = CalendarEvent::STATUS_COMPLETO,
    ): void {
        if ($createdBy !== null) {
            $this->actingAs($createdBy);
        }
        app(CurrentStore::class)->set($store);

        $event = CalendarEvent::query()->create([
            'store_id' => $store->id, 'client_id' => $client->id, 'user_id' => $tech->id,
            'event_type' => CalendarEvent::TYPE_MARCACAO, 'status' => $status, 'marcacao_source' => $source,
            'title' => 'Marcação', 'start_at' => $startUtc, 'end_at' => $startUtc->copy()->addMinutes($minutes),
        ]);
        $esi = CalendarEventService::query()->create([
            'calendar_event_id' => $event->id, 'service_id' => $service->id,
            'duration' => $minutes, 'price' => $paid ?? 0, 'sort_order' => 0,
        ]);

        if ($paid !== null) {
            $sale = Sale::query()->create([
                'store_id' => $store->id, 'calendar_event_id' => $event->id, 'client_id' => $client->id,
                'numero_fatura' => 'FR T/'.(++$this->seq), 'data_emissao' => $startUtc->copy()->startOfDay(),
                'total' => $paid, 'valor_pago' => $paid,
                'payment_method' => $this->seq % 2 ? Sale::PAYMENT_MBWAY : Sale::PAYMENT_DINHEIRO,
                'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO, 'status' => Sale::STATUS_PAGO,
                'invoice_status' => Sale::INVOICE_STATUS_FATURADO,
            ]);
            SaleItem::query()->create([
                'sale_id' => $sale->id, 'tipo' => SaleItem::TIPO_SERVICO, 'calendar_event_service_id' => $esi->id,
                'service_id' => $service->id, 'descricao' => $service->name, 'quantidade' => 1,
                'preco_unitario' => $paid, 'subtotal' => $paid, 'sort_order' => 0,
            ]);
        }
    }

    private function recapFor(User $user, Store $store): array
    {
        $this->actingAs($user);
        app(CurrentStore::class)->set($store);

        return app(YearRecapBuilder::class)->compute(new RecapContext(
            viewer: $user,
            audience: RecapContext::audienceFor($user),
            storeId: $store->id,
            storeName: $store->name,
            year: 2026,
            timezone: 'Europe/Lisbon',
        ));
    }

    /** @return array<string, array<string, mixed>> */
    private function slidesById(array $recap): array
    {
        return collect($recap['slides'])->keyBy('id')->all();
    }

    public function test_prestadora_sees_only_her_year_and_her_own_money(): void
    {
        $fx = $this->fixture();
        $recap = $this->recapFor($fx['ana'], $fx['store']);
        $slides = $this->slidesById($recap);

        $this->assertSame('prestador', $recap['audience']);
        $this->assertSame('30', $slides['marcacoes']['value']);
        $this->assertSame('10', $slides['clientes']['value']);
        $this->assertSame('100%', $slides['retorno']['value']);
        $this->assertSame('Cliente S.', $slides['fieis']['value']);
        $this->assertSame('Verniz Gel', $slides['servicos']['value']);
        $this->assertSame('≈ 300', $slides['unhas']['value']);
        $this->assertSame('30 h', $slides['horas']['value']);
        $this->assertSame('O teu ano, Ana', $slides['cover']['subtitle']);

        // Só o dinheiro dela: 30 × 25 € e 40% de comissão.
        $this->assertSame('750 €', $slides['faturacao_pessoal']['value']);
        $this->assertSame('300 €', $slides['faturacao_pessoal']['stat_secondary']['value']);

        foreach (['faturacao', 'pagamentos', 'sms', 'canais', 'equipa', 'marcadas_por_mim'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $slides, "Prestadora não pode ver o slide {$forbidden}");
        }
        $this->assertStringNotContainsString('1 425', json_encode($recap, JSON_UNESCAPED_UNICODE)); // total da loja
    }

    public function test_rececao_sees_store_without_financials(): void
    {
        $fx = $this->fixture();
        $recap = $this->recapFor($fx['rececao'], $fx['store']);
        $slides = $this->slidesById($recap);

        $this->assertSame('rececao', $recap['audience']);
        $this->assertSame('45', $slides['marcacoes']['value']);
        $this->assertSame('20', $slides['marcadas_por_mim']['value']); // as 10 marcações com origem online não contam
        $this->assertSame('Ana Ribeiro', $slides['equipa']['value']);
        $this->assertArrayHasKey('servicos', $slides);

        foreach (['faturacao', 'faturacao_pessoal', 'pagamentos', 'sms', 'canais'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $slides, "Receção não pode ver o slide {$forbidden}");
        }
        $this->assertStringNotContainsString('€', json_encode($recap, JSON_UNESCAPED_UNICODE));
    }

    public function test_admin_sees_everything(): void
    {
        $fx = $this->fixture();
        $recap = $this->recapFor($fx['admin'], $fx['store']);
        $slides = $this->slidesById($recap);

        $this->assertSame('admin', $recap['audience']);
        $this->assertSame('45', $slides['marcacoes']['value']);
        $this->assertSame('1 425 €', $slides['faturacao']['value']);
        $this->assertSame('45', $slides['pagamentos']['value']);
        $this->assertSame('12', $slides['sms']['value']);
        $this->assertSame('split', $slides['canais']['type']);
        $this->assertSame(22, $slides['canais']['split'][1]['pct']); // 10 de 45 online
        $this->assertCount(4, $slides['finale']['highlights']);
    }

    public function test_empty_year_shows_gentle_finale(): void
    {
        $fx = $this->fixture();
        $this->actingAs($fx['admin']);
        $recap = app(YearRecapBuilder::class)->compute(new RecapContext(
            viewer: $fx['admin'], audience: 'admin', storeId: $fx['store']->id,
            storeName: 'Studio Recap', year: 2024, timezone: 'Europe/Lisbon',
        ));

        $this->assertCount(2, $recap['slides']);
        $this->assertSame('Ainda é cedo.', $recap['slides'][1]['title']);
    }

    public function test_page_renders_per_role_and_hides_financials_from_prestadora(): void
    {
        $fx = $this->fixture();

        $this->actingAs($fx['ana'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('year-recap.show'))
            ->assertOk()
            ->assertSee('O teu ano, Ana')
            ->assertSee('O que faturaste')
            ->assertDontSee('Faturação do ano')
            ->assertDontSee('Pagamentos recebidos');

        $this->actingAs($fx['admin'])
            ->withSession([SetCurrentStore::SESSION_KEY => $fx['store']->id])
            ->get(route('year-recap.show', ['template' => 'nocturne']))
            ->assertOk()
            ->assertSee('Faturação do ano')
            ->assertSee('SMS enviadas');
    }
}
