<?php

use App\Jobs\SendBookingReminderSmsJob;
use App\Models\CalendarEvent;
use App\Services\ClientWalletService;
use App\Services\VendusApiService;
use App\Services\VendusPaymentMethodResolver;
use App\Services\ZappyImport\ZappyImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vendus:test-connection', function (VendusApiService $vendus) {
    $this->comment('A testar ligacao a API Vendus...');

    $result = $vendus->testConnection();

    if ($result['ok']) {
        $this->info($result['message'].' (HTTP '.$result['status'].')');

        return self::SUCCESS;
    }

    $this->error($result['message'].' (HTTP '.$result['status'].')');

    return self::FAILURE;
})->purpose('Testa autenticacao e acesso a API Vendus');

Artisan::command('vendus:list-payment-methods', function (VendusPaymentMethodResolver $resolver) {
    if ((string) config('services.vendus.api_key') === '' || (string) config('services.vendus.base_url') === '') {
        $this->error('Configure VENDUS_API_KEY e VENDUS_BASE_URL no .env.');

        return self::FAILURE;
    }

    if ((bool) config('services.vendus.simulate', false)) {
        $this->warn('VENDUS_SIMULATE=true: a listagem ainda chama a API; em alternativa desative para testes reais.');
    }

    $rows = $resolver->listPaymentMethods();
    if ($rows === []) {
        $this->warn('Nenhum meio de pagamento devolvido (ou falha na API). Verifique os logs.');

        return self::FAILURE;
    }

    $this->table(
        ['id', 'title', 'type', 'status'],
        array_map(static function (array $r): array {
            return [
                (string) ($r['id'] ?? ''),
                (string) ($r['title'] ?? ''),
                (string) ($r['type'] ?? ''),
                (string) ($r['status'] ?? ''),
            ];
        }, $rows)
    );

    $this->line('');
    $this->comment('Mapeamento interno: dinheiro→NU, mbway→MBWAY, multibanco→MB, transferencia→TB, cartao→CC/CD, outro→OU.');
    $this->comment('Override fixo no .env: VENDUS_PAYMENT_METHOD_ID=<id>');

    return self::SUCCESS;
})->purpose('Lista meios de pagamento Vendus (ids para FR / debug)');

Artisan::command('booking:dispatch-sms-reminders', function () {
    $tz = (string) config('booking.business_timezone', config('app.timezone', 'UTC'));
    $nowLocal = CarbonImmutable::now($tz);
    $startHour = (int) config('booking.sms_reminder_day_before_send_start_hour', 8);
    $endHour = (int) config('booking.sms_reminder_day_before_send_end_hour', 22);
    $maxPerRun = max(1, (int) config('booking.sms_reminder_max_per_run', 15));

    if ($endHour > $startHour) {
        $hour = (int) $nowLocal->format('G');
        if ($hour < $startHour || $hour >= $endHour) {
            $this->comment(sprintf(
                'SMS lembrete: fora da janela de envio (hora local %d; permitido %d–%d).',
                $hour,
                $startHour,
                $endHour
            ));

            return self::SUCCESS;
        }
    }

    // Início do «amanhã» civil na timezone de negócio → intervalo em UTC na BD.
    $tomorrowStartLocal = $nowLocal->addDay()->startOfDay();
    $tomorrowEndLocal = $tomorrowStartLocal->addDay();
    $windowStartUtc = $tomorrowStartLocal->utc();
    $windowEndUtc = $tomorrowEndLocal->utc();

    $sameDayCreationSkip = static function (CalendarEvent $event) use ($tz): bool {
        if ($event->start_at === null || $event->created_at === null) {
            return false;
        }

        return $event->start_at->copy()->timezone($tz)->toDateString()
            === $event->created_at->copy()->timezone($tz)->toDateString();
    };

    $count = 0;
    $skippedSameDay = 0;

    CalendarEvent::query()
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', CalendarEvent::STATUS_AGENDADO)
        ->whereNull('booking_sms_reminder_sent_at')
        ->whereNull('booking_sms_reminder_failed_at')
        ->where('start_at', '>=', $windowStartUtc)
        ->where('start_at', '<', $windowEndUtc)
        ->whereHas('client', function ($q): void {
            $q->where('notify_sms_booking_reminders', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '');
        })
        ->select(['id', 'start_at', 'created_at'])
        ->orderBy('id')
        ->chunkById(100, function ($events) use (&$count, &$skippedSameDay, $maxPerRun, $sameDayCreationSkip): bool {
            foreach ($events as $event) {
                if ($count >= $maxPerRun) {
                    return false;
                }
                if ($sameDayCreationSkip($event)) {
                    $skippedSameDay++;

                    continue;
                }
                SendBookingReminderSmsJob::dispatch((int) $event->id);
                $count++;
            }

            return true;
        });

    $this->info(sprintf(
        'SMS lembretes despachados: %d (dia da marcação = amanhã %s; TZ=%s; máx/run=%d; ignorados marcação-no-mesmo-dia=%d)',
        $count,
        $tomorrowStartLocal->format('Y-m-d'),
        $tz,
        $maxPerRun,
        $skippedSameDay
    ));

    return self::SUCCESS;
})->purpose('Despacha SMS de lembrete no dia anterior à marcação (timezone de negócio)');

Schedule::command('booking:dispatch-sms-reminders')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('wallet:reconcile {--store=} {--fix}', function (ClientWalletService $wallet) {
    $storeId = $this->option('store');
    $fix = (bool) $this->option('fix');

    if ($storeId !== null && ! ctype_digit((string) $storeId)) {
        $this->error('A opção --store deve ser um ID numérico.');

        return self::FAILURE;
    }

    $storeFilter = $storeId !== null ? (int) $storeId : null;

    $this->comment(sprintf(
        'A reconciliar carteiras%s%s...',
        $storeFilter !== null ? ' da loja #'.$storeFilter : '',
        $fix ? ' (corrigir saldos em cache)' : '',
    ));

    $mismatches = $wallet->reconcileAll($storeFilter, $fix);

    if ($mismatches->isEmpty()) {
        $this->info('Todas as carteiras estão consistentes (SUM(ledger) = wallet_balance_cents).');

        return self::SUCCESS;
    }

    $this->table(
        ['client_id', 'store_id', 'cached_cents', 'ledger_cents', 'drift_cents', 'fixed'],
        $mismatches->map(static fn ($r) => [
            $r->clientId,
            $r->storeId,
            $r->cachedBalanceCents,
            $r->ledgerBalanceCents,
            $r->driftCents(),
            $r->wasFixed ? 'yes' : 'no',
        ])->all(),
    );

    if ($fix) {
        $remaining = $wallet->reconcileAll($storeFilter, false);
        if ($remaining->isEmpty()) {
            $this->info('Saldos corrigidos; reconciliação concluída sem discrepâncias.');

            return self::SUCCESS;
        }

        $this->error('Ainda existem discrepâncias após correção.');

        return self::FAILURE;
    }

    $this->warn(sprintf(
        '%d cliente(s) com discrepância. Execute com --fix para alinhar wallet_balance_cents ao ledger.',
        $mismatches->count(),
    ));

    return self::FAILURE;
})->purpose('Audita consistência entre ledger e saldo em cache das carteiras');

Artisan::command(
    'zappy:purge {--store=1 : ID da loja} {--dry-run : Simular sem apagar} {--force : Não pedir confirmação} {--without-clients : Não apagar clientes importados do Zappy} {--with-catalog : Apagar também serviços importados, extras e taxas}',
    function (ZappyImportService $importer) {
        $storeId = (int) ($this->option('store') ?: config('zappy_import.default_store_id', 1));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $purgeClients = ! (bool) $this->option('without-clients');
        $purgeCatalog = (bool) $this->option('with-catalog');

        $this->warn($purgeClients
            ? 'Remove marcações, vendas e clientes importados do Zappy.'
            : 'Remove marcações e vendas importadas do Zappy (clientes mantêm-se).');
        if ($purgeCatalog) {
            $this->warn('Com --with-catalog: apaga serviços com ref Zappy, extras e taxas da loja (categorias de serviços mantêm-se).');
        } else {
            $this->comment('Serviços/extras/taxas mantêm-se. Use --with-catalog para reimportar o catálogo do CSV.');
        }

        if (! $dryRun && ! $force && ! $this->confirm("Apagar dados Zappy da loja #{$storeId}?", false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $stats = $importer->purgeImportedData($storeId, $dryRun, $purgeClients, $purgeCatalog);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($stats as $key => $value) {
            if ($value > 0) {
                $rows[] = [$key, $value];
            }
        }

        if ($rows !== []) {
            $this->table(['o que será '.($dryRun ? 'apagado' : 'apagado'), 'quantidade'], $rows);
        } else {
            $this->comment('Nada encontrado para apagar.');
        }

        if ($stats['clients_skipped'] > 0) {
            $this->warn(sprintf(
                '%d cliente(s) com ref Zappy não apagado(s): têm marcações ou vendas fora do import.',
                $stats['clients_skipped'],
            ));
        }

        if ($dryRun) {
            $this->warn('Dry-run: nada foi apagado.');
        } else {
            $this->info('Purge concluído.');
        }

        $this->newLine();
        $this->line('Reimportação recomendada:');
        $catalog = $purgeCatalog ? ' --with-catalog' : '';
        $this->line('  1. php artisan zappy:purge --store='.$storeId.$catalog.' --force');
        $this->line('  2. php artisan zappy:import --store='.$storeId);
        $this->line('  3. php artisan zappy:import --store='.$storeId.' --repair-times --repair-orphan-paid --repair-missing-services --repair-sale-discounts --repair-sale-dates --repair-relink-sales --repair-client-dates');
        if (! $purgeClients) {
            $this->comment('Nota: usou --without-clients; a lista de clientes não foi limpa.');
        }

        return self::SUCCESS;
    }
)->purpose('Apaga marcações/vendas/referências importadas do Zappy (preparar reimportação)');

Artisan::command(
    'zappy:import {--store=1 : ID da loja} {--dry-run : Simular sem gravar} {--fresh : Apagar referências Zappy da loja antes de importar} {--only= : Passos separados por vírgula (services,clients,appointments,sales)} {--repair-times : Corrigir horas das marcações já importadas (fuso Zappy→UTC)} {--repair-invoice-alerts : Atualizar scope das vendas importadas para caixa_liquidacao} {--repair-merge : Fundir marcações consecutivas do mesmo cliente/técnica} {--repair-relink-sales : Religar vendas (inclui fora de canceladas + repartição)} {--repair-orphan-paid : Corrigir marcações pagas sem venda (relink ou venda sintética)} {--repair-sale-discounts : Preencher desconto das vendas importadas a partir do CSV} {--repair-sale-dates : Corrigir data_emissao das vendas importadas a partir do CSV} {--repair-missing-services : Associar serviço por defeito a marcações importadas sem serviço} {--repair-client-dates : Corrigir data de criação dos clientes a partir do CSV Zappy} {--repair-distribute-sales : Repartir faturas por evento após separação de visitas}',
    function (ZappyImportService $importer) {
    $storeId = (int) ($this->option('store') ?: config('zappy_import.default_store_id', 1));
    $dryRun = (bool) $this->option('dry-run');
    $fresh = (bool) $this->option('fresh');
    $repairTimes = (bool) $this->option('repair-times');
    $repairInvoiceAlerts = (bool) $this->option('repair-invoice-alerts');
    $repairMerge = (bool) $this->option('repair-merge');
    $repairRelinkSales = (bool) $this->option('repair-relink-sales');
    $repairOrphanPaid = (bool) $this->option('repair-orphan-paid');
    $repairSaleDiscounts = (bool) $this->option('repair-sale-discounts');
    $repairSaleDates = (bool) $this->option('repair-sale-dates');
    $repairMissingServices = (bool) $this->option('repair-missing-services');
    $repairDistributeSales = (bool) $this->option('repair-distribute-sales');
    $repairClientDates = (bool) $this->option('repair-client-dates');
    $only = trim((string) ($this->option('only') ?? ''));

    if ($repairTimes || $repairInvoiceAlerts || $repairMerge || $repairRelinkSales || $repairOrphanPaid || $repairSaleDiscounts || $repairSaleDates || $repairMissingServices || $repairDistributeSales || $repairClientDates) {
        $this->info(sprintf(
            'Reparação importação Zappy → loja #%d%s',
            $storeId,
            $dryRun ? ' [DRY-RUN]' : '',
        ));
        try {
            $stats = $importer->run($storeId, $dryRun, false, [], $repairTimes, $repairInvoiceAlerts, $repairMerge, $repairRelinkSales, $repairOrphanPaid, $repairSaleDiscounts, $repairMissingServices, $repairDistributeSales, $repairClientDates, $repairSaleDates);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    } else {
        $steps = $only !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $only))))
            : ['services', 'clients', 'appointments', 'sales'];

        $allowed = ['services', 'clients', 'appointments', 'sales'];
        $invalid = array_diff($steps, $allowed);
        if ($invalid !== []) {
            $this->error('Passos inválidos em --only: '.implode(', ', $invalid));
            $this->line('Permitidos: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Importação Zappy → loja #%d%s%s%s',
            $storeId,
            $dryRun ? ' [DRY-RUN]' : '',
            $fresh ? ' [FRESH]' : '',
            $only !== '' ? ' (apenas: '.implode(', ', $steps).')' : '',
        ));

        try {
            $stats = $importer->run($storeId, $dryRun, $fresh, $steps);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    $rows = [];
    foreach ($stats as $key => $value) {
        if ($value > 0) {
            $rows[] = [$key, $value];
        }
    }

    if ($rows !== []) {
        $this->table(['métrica', 'valor'], $rows);
    } else {
        $this->comment('Nenhuma alteração contabilizada.');
    }

    if ($dryRun) {
        $this->warn('Dry-run: nada foi gravado na base de dados.');
    } else {
        $this->info('Importação concluída.');
    }

    return self::SUCCESS;
})->purpose('Importa CSVs do Zappy (serviços, clientes, marcações, vendas)');

Artisan::command(
    'store:purge-sql {--store=1 : ID da loja} {--output= : Caminho do ficheiro .sql} {--with-catalog : Apagar também serviços, extras e taxas (preserva users/agents)} {--full : Apagar também agents e CRM}',
    function () {
        $storeId = max(1, (int) $this->option('store'));
        $mode = (bool) $this->option('full')
            ? \App\Services\StoreDataSqlPurger::MODE_FULL
            : ((bool) $this->option('with-catalog')
                ? \App\Services\StoreDataSqlPurger::MODE_CATALOG
                : \App\Services\StoreDataSqlPurger::MODE_DATA);
        $output = $this->option('output')
            ?: storage_path('app/exports/store_'.$storeId.'_purge.sql');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error('Não foi possível criar a pasta: '.$dir);

            return self::FAILURE;
        }

        $sql = (new \App\Services\StoreDataSqlPurger($storeId, $mode))->purgeSql();
        file_put_contents($output, $sql);

        $this->info('SQL de limpeza gravado em: '.$output);
        $this->comment(match ($mode) {
            \App\Services\StoreDataSqlPurger::MODE_FULL => 'Modo completo: inclui agents.',
            \App\Services\StoreDataSqlPurger::MODE_CATALOG => 'Modo catálogo: apaga categorias, serviços, extras e taxas; preserva users e agents.',
            default => 'Modo dados: preserva users, agents e catálogo.',
        });

        return self::SUCCESS;
    }
)->purpose('Gera SQL para limpar dados da loja no servidor (evita erros #1062 Duplicate entry)');

Artisan::command(
    'store:agents-sql {--store=1 : ID da loja} {--output= : Caminho do ficheiro .sql (por defeito: storage/app/exports/store_{id}_agents.sql)}',
    function () {
        $storeId = max(1, (int) $this->option('store'));
        $output = $this->option('output')
            ?: storage_path('app/exports/store_'.$storeId.'_agents.sql');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error('Não foi possível criar a pasta: '.$dir);

            return self::FAILURE;
        }

        file_put_contents($output, (new \App\Services\StoreAgentsSqlExporter($storeId))->export());
        $this->info('SQL de agentes gravado em: '.$output);
        $this->comment('Importar no servidor se técnicos/agenda não aparecerem (users devem existir com os mesmos emails).');

        return self::SUCCESS;
    }
)->purpose('Gera SQL de agentes ligados a users por email (para o servidor)');

Artisan::command(
    'store:export-sql {--store=1 : ID da loja} {--output= : Caminho do ficheiro .sql} {--without-org-store : Não exportar organizations/stores} {--with-purge : Gerar também o SQL de limpeza} {--with-catalog : Incluir serviços, extras e taxas no export/purge} {--full : Export/purge completo (inclui agents)}',
    function () {
        $storeId = max(1, (int) $this->option('store'));
        $withoutOrgStore = (bool) $this->option('without-org-store');
        $mode = (bool) $this->option('full')
            ? \App\Services\StoreDataSqlPurger::MODE_FULL
            : ((bool) $this->option('with-catalog')
                ? \App\Services\StoreDataSqlPurger::MODE_CATALOG
                : \App\Services\StoreDataSqlPurger::MODE_DATA);
        $output = $this->option('output')
            ?: storage_path('app/exports/store_'.$storeId.'_data.sql');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error('Não foi possível criar a pasta: '.$dir);

            return self::FAILURE;
        }

        $exporter = new \App\Services\StoreDataSqlExporter($storeId, $mode);
        $sql = $exporter->export($withoutOrgStore);

        file_put_contents($output, $sql);

        $sizeKb = round(filesize($output) / 1024, 1);
        $this->info('Export SQL gravado em: '.$output.' ('.$sizeKb.' KB)');
        $this->comment(match ($mode) {
            \App\Services\StoreDataSqlPurger::MODE_FULL => 'Modo completo: inclui catálogo e agents.',
            \App\Services\StoreDataSqlPurger::MODE_CATALOG => 'Modo catálogo: dados + categorias, serviços, extras e taxas (sem users/agents).',
            default => 'Modo dados: clientes, marcações, vendas… (preserva catálogo no servidor).',
        });

        if ((bool) $this->option('with-purge')) {
            $purgePath = storage_path('app/exports/store_'.$storeId.'_purge.sql');
            file_put_contents($purgePath, (new \App\Services\StoreDataSqlPurger($storeId, $mode))->purgeSql());
            $this->info('SQL de limpeza gravado em: '.$purgePath);
        }

        return self::SUCCESS;
    }
)->purpose('Exporta dados da loja para importar no servidor via phpMyAdmin');
