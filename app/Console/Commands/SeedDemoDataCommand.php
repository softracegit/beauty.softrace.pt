<?php

namespace App\Console\Commands;

use App\Actions\SyncServiceOptionsAction;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Category;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\BookingTheme;
use App\Support\CurrentStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Dados de demonstração para staging/local: uma organização "Fada Studio (Demo)"
 * com as lojas aveiro-centro e porto-demo, equipa, serviços (com variantes,
 * adicionais e extras), clientes fictícios, marcações e vendas.
 *
 * Nunca corre em produção. Durante o comando, emails e notificações são
 * intercetados (não sai nada para ninguém).
 */
class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo:seed
                            {--email=admin@demo.test : Email do utilizador admin}
                            {--password= : Palavra-passe do admin (por omissão é gerada)}';

    protected $description = 'Cria dados de demonstração (Fada Studio Demo, loja aveiro-centro). Bloqueado em produção.';

    public function handle(SyncServiceOptionsAction $syncOptions): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado: APP_ENV=production. Este comando só corre em local/staging.');

            return self::FAILURE;
        }

        if (Store::query()->where('slug', 'aveiro-centro')->exists()) {
            $this->error('Já existe uma loja com o slug "aveiro-centro". Os dados de demonstração já foram criados.');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        if (User::query()->where('email', $email)->exists()) {
            $this->error("Já existe um utilizador com o email {$email}. Use --email=outro@exemplo.");

            return self::FAILURE;
        }
        $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));

        // Nada sai para fora enquanto criamos os dados.
        Mail::fake();
        Notification::fake();
        mt_srand(7);

        DB::transaction(function () use ($email, $password, $syncOptions): void {
            $this->seed($email, $password, $syncOptions);
        });

        $this->newLine();
        $this->info('Dados de demonstração criados.');
        $this->table(['', ''], [
            ['Entrar no CRM', $email],
            ['Palavra-passe', $password],
            ['Loja principal (slug)', 'aveiro-centro'],
            ['API da app', '/api/v1/stores/aveiro-centro'],
            ['Técnicas', 'laissa@demo.test, vanessa@demo.test, beatriz@demo.test (mesma palavra-passe)'],
        ]);
        $this->comment('Guarde a palavra-passe: não volta a ser mostrada.');

        return self::SUCCESS;
    }

    private function seed(string $email, string $password, SyncServiceOptionsAction $syncOptions): void
    {
        // Horário da loja e das técnicas (a disponibilidade usa o horário de cada técnica).
        $schedule = [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '19:00'],
            'tue' => ['enabled' => true, 'start' => '09:00', 'end' => '19:00'],
            'wed' => ['enabled' => true, 'start' => '09:00', 'end' => '19:00'],
            'thu' => ['enabled' => true, 'start' => '09:00', 'end' => '19:00'],
            'fri' => ['enabled' => true, 'start' => '09:00', 'end' => '19:00'],
            'sat' => ['enabled' => true, 'start' => '09:00', 'end' => '13:00'],
            'sun' => ['enabled' => false, 'start' => '09:00', 'end' => '13:00'],
        ];

        $org = Organization::query()->create([
            'name' => 'Fada Studio (Demo)',
            'slug' => 'fada-demo',
            'status' => 'active',
            'timezone' => 'Europe/Lisbon',
            'weekly_schedule' => $schedule,
            'email' => 'geral@demo.test',
        ]);

        $store = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Fada Studio Aveiro Centro',
            'slug' => 'aveiro-centro',
            'timezone' => 'Europe/Lisbon',
            'weekly_schedule' => $schedule,
            'phone' => '234 000 000',
            'email' => 'aveiro@demo.test',
            'address_line' => 'Rua de Exemplo 15',
            'city' => 'Aveiro',
            'postal_code' => '3800-000',
        ]);

        $porto = Store::query()->create([
            'organization_id' => $org->id,
            'name' => 'Fada Studio Porto',
            'slug' => 'porto-demo',
            'timezone' => 'Europe/Lisbon',
            'phone' => '220 000 000',
            'email' => 'porto@demo.test',
            'address_line' => 'Rua de Exemplo 200',
            'city' => 'Porto',
            'postal_code' => '4000-000',
            'weekly_schedule' => array_replace($schedule, [
                'sat' => ['enabled' => true, 'start' => '10:00', 'end' => '18:00'],
            ]),
        ]);

        app(CurrentStore::class)->set($store);

        $admin = User::query()->create([
            'name' => 'Admin Demo',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'organization_id' => $org->id,
        ]);
        $admin->stores()->attach([$store->id, $porto->id]);
        Auth::setUser($admin);

        foreach ([$store, $porto] as $s) {
            CrmSetting::setBookingTheme(BookingTheme::MINIMAL, $s->id);
            // Como na Fada: sem pré-pagamento ("Hoje não paga nada").
            CrmSetting::setBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, false, $s->id);
            CrmSetting::setBookingCancellationNoticeHours(12, $s->id);
            CrmSetting::setBookingCancellationPolicyText(
                'Pode cancelar ou reagendar a sua marcação sem custos até 12 horas antes da hora marcada. Se surgir um imprevisto, fale connosco por WhatsApp.',
                $s->id
            );
        }

        // Equipa
        $team = [];
        foreach ([
            ['Laissa', 'laissa', 'Nail Artist', '#E8A0BF'],
            ['Vanessa Pereira', 'vanessa', 'Manicure', '#9AD0C2'],
            ['Beatriz Nunes', 'beatriz', 'Esteticista', '#F6C28B'],
        ] as $i => [$name, $login, $spec, $color]) {
            $user = User::query()->create([
                'name' => $name,
                'email' => "{$login}@demo.test",
                'password' => Hash::make($password),
                'role' => User::ROLE_PRESTADOR,
                'organization_id' => $org->id,
            ]);
            $user->stores()->attach($store->id);
            $team[$login] = Agent::query()->create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'name' => $name,
                'specialization' => $spec,
                'status' => Agent::STATUS_ACTIVE,
                'visible_in_booking' => true,
                'visible_in_agenda' => true,
                'agenda_order' => $i,
                'color' => $color,
                'weekly_schedule' => $schedule,
            ]);
        }

        // Catálogo
        $cats = [];
        foreach ([['Manicure', '#E8A0BF'], ['Pedicure', '#9AD0C2'], ['Rosto', '#F6C28B']] as $i => [$name, $color]) {
            $cats[$name] = Category::query()->create([
                'store_id' => $store->id, 'organization_id' => $org->id,
                'name' => $name, 'sort_order' => $i + 1, 'color' => $color,
            ]);
        }

        $ec = ExtraCategory::query()->create(['organization_id' => $org->id, 'store_id' => $store->id, 'name' => 'Decoração', 'sort_order' => 1]);
        $extras = [];
        foreach ([['Francesinha', 4, 15], ['Nail Art (por unha)', 2, 10], ['Olho de Gato', 5, 15], ['Cromado', 5, 15]] as $i => [$name, $price, $dur]) {
            $extras[] = Extra::query()->create(['extra_category_id' => $ec->id, 'name' => $name, 'price' => $price, 'duration' => $dur, 'sort_order' => $i]);
        }

        $nails = ['laissa', 'vanessa'];
        $defs = [
            // [categoria, nome, duração, preço, técnicas, opções, extras?]
            ['Manicure', 'Manicure Tradicional', 45, 15, $nails, [], false],
            ['Manicure', 'Verniz de Gel / Gelinho', 60, 22, $nails, [], true],
            ['Manicure', 'Unhas de Gel', 70, 25, $nails, [
                ['name' => 'Sobre o tamanho da unha natural', 'duration' => 70, 'price' => 25, 'is_baseline' => true],
                ['name' => 'Com extensão', 'duration' => 110, 'price' => 38],
                ['name' => 'Manutenção Cliente Fada', 'duration' => 90, 'price' => 30],
                ['name' => 'Precisa de reparação?', 'duration' => 15, 'price' => 4, 'type' => 'addon'],
                ['name' => 'Remoção de outro centro?', 'duration' => 20, 'price' => 8, 'type' => 'addon'],
            ], true],
            ['Manicure', 'Unhas de Acrílico', 70, 27, ['laissa'], [
                ['name' => 'Sobre o tamanho da unha natural', 'duration' => 70, 'price' => 27, 'is_baseline' => true],
                ['name' => 'Com extensão', 'duration' => 120, 'price' => 40],
                ['name' => 'Manutenção Cliente Fada', 'duration' => 95, 'price' => 32],
                ['name' => 'Precisa de reparação?', 'duration' => 15, 'price' => 4, 'type' => 'addon'],
            ], true],
            ['Manicure', 'Remoção', 20, 8, $nails, [
                ['name' => 'Gel', 'duration' => 20, 'price' => 8, 'is_baseline' => true],
                ['name' => 'Acrílico', 'duration' => 30, 'price' => 10],
                ['name' => 'Verniz de gel', 'duration' => 15, 'price' => 6],
            ], false],
            ['Pedicure', 'Pedicure Tradicional', 50, 18, ['vanessa', 'beatriz'], [], false],
            ['Pedicure', 'Pedicure com Verniz de Gel', 70, 27, ['vanessa', 'beatriz'], [], true],
            ['Rosto', 'Limpeza de Pele', 60, 45, ['beatriz'], [], false],
            ['Rosto', 'Design de Sobrancelhas', 20, 10, ['beatriz'], [
                ['name' => 'Só design', 'duration' => 20, 'price' => 10, 'is_baseline' => true],
                ['name' => 'Design com henna', 'duration' => 35, 'price' => 16],
            ], false],
        ];

        $services = [];
        foreach ($defs as $i => [$cat, $name, $dur, $price, $who, $options, $withExtras]) {
            $service = Service::query()->create([
                'store_id' => $store->id, 'organization_id' => $org->id, 'category_id' => $cats[$cat]->id,
                'name' => $name, 'duration' => $dur, 'price' => $price, 'online_price' => $price, 'sort_order' => $i,
            ]);
            $agentIds = array_map(fn (string $k) => $team[$k]->id, $who);
            $service->agents()->attach($agentIds);

            if ($options !== []) {
                $rows = array_map(fn (array $o, int $idx) => $o + ['online_price' => $o['price'], 'sort_order' => $idx], $options, array_keys($options));
                $syncOptions->execute($service, true, $rows);
            }

            if ($withExtras) {
                $service->extras()->attach(array_map(fn ($e) => $e->id, $extras));
                foreach ($agentIds as $agentId) {
                    foreach ($extras as $e) {
                        DB::table('agent_service_extra')->insert([
                            'agent_id' => $agentId, 'service_id' => $service->id, 'extra_id' => $e->id,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
            $services[] = [$service, $who];
        }

        // Clientes fictícios (emails .invalid e números 99x, que não existem)
        $names = ['Ana Ferreira', 'Carolina Pinto', 'Mariana Costa', 'Joana Ribeiro', 'Catarina Sousa', 'Leonor Matos',
            'Teresa Gomes', 'Filipa Rocha', 'Diana Almeida', 'Helena Cruz', 'Patrícia Neves', 'Raquel Teixeira',
            'Sara Moreira', 'Vera Fonseca', 'Bárbara Lima', 'Cláudia Reis', 'Daniela Paiva', 'Eva Simões',
            'Francisca Melo', 'Gabriela Dias', 'Isabel Correia', 'Lara Barros', 'Madalena Vaz', 'Natália Pires'];
        $clients = [];
        foreach ($names as $i => $name) {
            $clients[] = Client::query()->create([
                'store_id' => $store->id, 'organization_id' => $org->id, 'name' => $name,
                'type' => Client::TYPE_POTENCIAL_CLIENTE,
                'phone' => '99'.str_pad((string) (1000000 + $i * 7411), 7, '0', STR_PAD_LEFT),
                'email' => Str::slug($name, '.').'@exemplo.invalid',
            ]);
        }

        // Marcações: últimas 6 semanas (concluídas, com venda) e próximos 10 dias
        $tz = 'Europe/Lisbon';
        $today = Carbon::now($tz)->startOfDay();
        $seq = 0;
        $agents = array_values($team);
        $keys = array_keys($team);
        for ($d = -42; $d <= 10; $d++) {
            $day = $today->copy()->addDays($d);
            if ($day->isSunday()) {
                continue;
            }
            $closeHour = $day->isSaturday() ? 13 : 19;
            foreach ($agents as $ai => $agent) {
                $mine = array_values(array_filter($services, fn ($x) => in_array($keys[$ai], $x[1], true)));
                $t = $day->copy()->setTime(9, $ai % 2 ? 30 : 0);
                while ($t->hour < $closeHour - 1) {
                    if (mt_rand(0, 99) < ($d < 0 ? 45 : 60)) {
                        $t->addMinutes(30);

                        continue;
                    }
                    [$service] = $mine[mt_rand(0, count($mine) - 1)];
                    $end = $t->copy()->addMinutes((int) $service->duration);
                    if ($end->hour > $closeHour || ($end->hour === $closeHour && $end->minute > 0)) {
                        break;
                    }
                    $past = $end->lt(Carbon::now($tz));
                    $r = mt_rand(0, 99);
                    $status = $past
                        ? ($r < 4 ? CalendarEvent::STATUS_FALTOU : ($r < 9 ? CalendarEvent::STATUS_CANCELADO : CalendarEvent::STATUS_COMPLETO))
                        : ($r < 60 ? CalendarEvent::STATUS_CONFIRMADO : CalendarEvent::STATUS_AGENDADO);
                    $client = $clients[mt_rand(0, count($clients) - 1)];

                    $event = CalendarEvent::query()->create([
                        'store_id' => $store->id, 'client_id' => $client->id, 'user_id' => $agent->user_id,
                        'event_type' => CalendarEvent::TYPE_MARCACAO, 'status' => $status,
                        'marcacao_source' => mt_rand(0, 99) < 45 ? 'online' : 'agenda',
                        'title' => $client->name,
                        'start_at' => $t->copy()->utc(), 'end_at' => $end->copy()->utc(),
                    ]);
                    $line = CalendarEventService::query()->create([
                        'calendar_event_id' => $event->id, 'service_id' => $service->id,
                        'duration' => $service->duration, 'price' => $service->price, 'sort_order' => 0,
                    ]);

                    if ($status === CalendarEvent::STATUS_COMPLETO) {
                        $sale = Sale::query()->create([
                            'store_id' => $store->id, 'calendar_event_id' => $event->id, 'client_id' => $client->id,
                            'numero_fatura' => 'DEMO/'.(++$seq),
                            'data_emissao' => $t->copy()->utc()->startOfDay(),
                            'total' => $service->price, 'valor_pago' => $service->price,
                            'payment_method' => [Sale::PAYMENT_MBWAY, Sale::PAYMENT_DINHEIRO][mt_rand(0, 1)],
                            'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO, 'status' => Sale::STATUS_PAGO,
                            'invoice_status' => Sale::INVOICE_STATUS_FATURADO,
                        ]);
                        SaleItem::query()->create([
                            'sale_id' => $sale->id, 'tipo' => SaleItem::TIPO_SERVICO,
                            'calendar_event_service_id' => $line->id, 'service_id' => $service->id,
                            'descricao' => $service->name, 'quantidade' => 1,
                            'preco_unitario' => $service->price, 'subtotal' => $service->price, 'sort_order' => 0,
                        ]);
                    }
                    $t = $end->copy()->addMinutes([0, 15, 0, 30][mt_rand(0, 3)]);
                }
            }
        }

        $this->line(sprintf(
            'Lojas: 2 · Técnicas: %d · Serviços: %d · Clientes: %d · Marcações: %d · Vendas: %d',
            count($team), count($services), count($clients),
            CalendarEvent::query()->where('store_id', $store->id)->count(),
            Sale::query()->where('store_id', $store->id)->count(),
        ));
    }
}
