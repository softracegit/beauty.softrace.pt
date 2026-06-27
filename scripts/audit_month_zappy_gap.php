<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\VendasReportService;
use App\Services\ZappyImport\ZappyCsvReader;
use App\Services\ZappyImport\ZappyImportService;
use Carbon\Carbon;

$year = (int) ($argv[1] ?? 2025);
$month = (int) ($argv[2] ?? 8);
$storeId = (int) ($argv[3] ?? 1);

$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

function parseDecimal(string $v): float
{
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }
    if (str_contains($v, ',') && str_contains($v, '.')) {
        $v = str_replace('.', '', $v);
    }

    return (float) str_replace(',', '.', $v);
}

function parseCsvDate(string $raw, string $tz): ?Carbon
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    try {
        if (str_contains($raw, '/') && preg_match('#^\d{2}/#', $raw)) {
            return Carbon::createFromFormat('d/m/Y H:i', $raw, $tz);
        }

        return Carbon::parse($raw, $tz);
    } catch (\Throwable) {
        return null;
    }
}

$monthLabel = sprintf('%04d-%02d', $year, $month);
$start = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfDay();
$end = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->endOfMonth()->endOfDay();

$service = app(ZappyImportService::class);
$fpMethod = (new ReflectionClass($service))->getMethod('appointmentFingerprint');
$fpMethod->setAccessible(true);

$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
    ->pluck('local_id', 'zappy_key');

// --- Zappy marcações Pagou (data marcação) ---
$zappyLines = [];
$zappyTotal = 0.0;
$zappyByPayment = 0.0;
$zappyPaymentCount = 0;

foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $i => $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if ($dt && (int) $dt->year === $year && (int) $dt->month === $month) {
        $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
        if ($price > 0) {
            $zappyTotal += $price;
            $zappyLines[] = [
                'idx' => $i,
                'client' => mb_strtolower(trim($row['client_name'] ?? '')),
                'start' => $dt->format('Y-m-d H:i'),
                'price' => $price,
                'service' => trim($row['item_name'] ?? ''),
                'provider' => trim($row['service_provider'] ?? ''),
            ];
        }
    }

    $payDt = parseCsvDate($row['payment_date'] ?? '', $tz);
    if ($payDt && (int) $payDt->year === $year && (int) $payDt->month === $month) {
        $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
        if ($price > 0 && trim($row['status'] ?? '') === 'Pagou') {
            $zappyByPayment += $price;
            $zappyPaymentCount++;
        }
    }
}

$vendasSvc = app(VendasReportService::class);
$crmMarcacao = $vendasSvc->sumVendasPagasPorMarcacao($start, $end);
$crmEmissao = $vendasSvc->sumVendasPagasPorEmissao($start, $end);

// CRM events completo
$events = CalendarEvent::query()
    ->where('store_id', $storeId)
    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
    ->where('status', CalendarEvent::STATUS_COMPLETO)
    ->whereDate('start_at', '>=', $start->toDateString())
    ->whereDate('start_at', '<=', $end->toDateString())
    ->with(['client', 'eventServiceItems'])
    ->get();

$crmSalesFromEvents = 0.0;
foreach ($events as $ev) {
    $sale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $ev->id)
        ->where('status', Sale::STATUS_PAGO)
        ->first();
    $crmSalesFromEvents += $sale ? round((float) $sale->total, 2) : 0.0;
}

// Pagou → cancelado
$pagouCancelado = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $i => $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
    if ($price <= 0) {
        continue;
    }
    $client = trim($row['client_name'] ?? '');
    $item = trim($row['item_name'] ?? '');
    $provider = trim($row['service_provider'] ?? '');
    $userId = (int) (config('zappy_import.agent_user_map')[$provider] ?? 0);
    $fp = $fpMethod->invoke($service, $dt, $client, $item, $userId);
    $eventId = $refs[$fp] ?? null;
    if (! $eventId) {
        continue;
    }
    $ev = CalendarEvent::find($eventId);
    if (! $ev || $ev->status !== CalendarEvent::STATUS_CANCELADO) {
        continue;
    }
    $pagouCancelado[] = [
        'line' => $i + 2,
        'client' => $client,
        'date' => $dt->format('d/m/Y H:i'),
        'service' => $item,
        'price' => $price,
        'event_id' => $eventId,
    ];
}

// Zappy lines sem evento CRM (cliente+data)
$unmatchedZappy = 0.0;
$unmatchedCount = 0;
foreach ($zappyLines as $z) {
    $found = $events->first(function ($ev) use ($z, $tz) {
        $client = mb_strtolower(trim($ev->client?->name ?? ''));
        $start = $ev->start_at?->timezone($tz)->format('Y-m-d H:i') ?? '';

        return $client === $z['client'] && $start === $z['start'];
    });
    if (! $found) {
        $unmatchedZappy += $z['price'];
        $unmatchedCount++;
    }
}

// Price diffs on matched completo events
$priceDiffs = [];
foreach ($events as $ev) {
    $sale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $ev->id)
        ->where('status', Sale::STATUS_PAGO)
        ->first();
    $saleTotal = $sale ? round((float) $sale->total, 2) : 0.0;
    $client = mb_strtolower(trim($ev->client?->name ?? ''));
    $startStr = $ev->start_at?->timezone($tz)->format('Y-m-d H:i') ?? '';
    $matchingZappy = array_filter($zappyLines, fn ($z) => $z['client'] === $client && $z['start'] === $startStr);
    $zappySum = round(array_sum(array_column($matchingZappy, 'price')), 2);
    if ($matchingZappy && abs($zappySum - $saleTotal) > 0.02) {
        $priceDiffs[] = [
            'event_id' => $ev->id,
            'client' => $ev->client?->name,
            'start' => $startStr,
            'zappy' => $zappySum,
            'sale' => $saleTotal,
            'delta' => round($zappySum - $saleTotal, 2),
            'numero' => $sale?->numero_fatura,
        ];
    }
}
usort($priceDiffs, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

// vendas.csv August gross + missing imports
$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc !== '') {
        $byDoc[$doc][] = $row;
    }
}
$importedSales = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('zappy_key')
    ->flip();

$vendasCsvGross = 0.0;
$missingDocs = [];
foreach ($byDoc as $doc => $lines) {
    $dt = parseCsvDate($lines[0]['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    if (trim($lines[0]['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    $gross = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
    }
    $vendasCsvGross += $gross;
    if (! isset($importedSales[$doc])) {
        $missingDocs[$doc] = round($gross, 2);
    }
}

// Events completo sem venda paga
$eventsSemVenda = 0.0;
$eventsSemVendaCount = 0;
foreach ($events as $ev) {
    $sale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $ev->id)
        ->where('status', Sale::STATUS_PAGO)
        ->exists();
    if (! $sale) {
        $sub = round((float) $ev->eventServiceItems->sum(fn ($es) => (float) $es->price), 2);
        $eventsSemVenda += $sub;
        $eventsSemVendaCount++;
    }
}

echo "=== Gap Zappy vs CRM — {$monthLabel} (loja #{$storeId}) ===\n\n";

echo "Zappy marcações Pagou (data marcação):     ".round($zappyTotal, 2)." € (".count($zappyLines)." linhas)\n";
echo "Zappy marcações Pagou (payment_date):      ".round($zappyByPayment, 2)." € ({$zappyPaymentCount} linhas)\n";
echo "Zappy vendas.csv bruto (data fatura):      ".round($vendasCsvGross, 2)." €\n\n";

echo "CRM vendas pagas (critério marcação):      {$crmMarcacao} €\n";
echo "CRM vendas pagas (critério emissão):       {$crmEmissao} €\n";
echo "CRM soma sale.total eventos completos:     ".round($crmSalesFromEvents, 2)." € ({$events->count()} eventos)\n\n";

echo "Gap Zappy Pagou vs CRM marcação:           ".round($zappyTotal - $crmMarcacao, 2)." €\n";
echo "Gap Zappy vendas.csv vs CRM marcação:      ".round($vendasCsvGross - $crmMarcacao, 2)." €\n";
echo "Gap Zappy vendas.csv vs CRM emissão:       ".round($vendasCsvGross - $crmEmissao, 2)." €\n\n";

$pcSum = round(array_sum(array_column($pagouCancelado, 'price')), 2);
$pdSum = round(array_sum(array_column($priceDiffs, 'delta')), 2);

echo "--- Componentes explicativos ---\n";
echo "Pagou → evento cancelado CRM:              {$pcSum} € (".count($pagouCancelado)." casos)\n";
echo "Δ totais venda vs Zappy (eventos ok):      {$pdSum} € (".count($priceDiffs)." eventos)\n";
echo "Linhas Zappy sem evento CRM (cli+data):    ".round($unmatchedZappy, 2)." € ({$unmatchedCount} linhas)\n";
echo "Eventos completos sem venda paga:          ".round($eventsSemVenda, 2)." € ({$eventsSemVendaCount} eventos)\n";
echo "Faturas vendas.csv não importadas:         ".round(array_sum($missingDocs), 2)." € (".count($missingDocs)." docs)\n\n";

if ($pagouCancelado !== []) {
    echo "Top Pagou→cancelado:\n";
    foreach (array_slice($pagouCancelado, 0, 8) as $c) {
        echo "  {$c['client']} | {$c['date']} | {$c['price']} € | linha {$c['line']} | ev#{$c['event_id']}\n";
    }
    echo "\n";
}

if ($priceDiffs !== []) {
    echo "Top Δ preço Zappy vs venda CRM:\n";
    foreach (array_slice($priceDiffs, 0, 10) as $d) {
        echo "  ev#{$d['event_id']} {$d['client']} {$d['start']}: Zappy {$d['zappy']} | venda {$d['sale']} | Δ {$d['delta']} | {$d['numero']}\n";
    }
    echo "\n";
}

if ($missingDocs !== []) {
    arsort($missingDocs);
    echo "Faturas vendas.csv em falta (top 10):\n";
    foreach (array_slice($missingDocs, 0, 10, true) as $doc => $amt) {
        echo "  {$doc}: {$amt} €\n";
    }
    echo "\n";
}

// --- Métricas Zappy alternativas (alinhar com UI Zappy) ---
$zappyDocTotals = [];
$zappyDocTotalsSettled = [];
$zappyNetNoTax = 0.0;
foreach ($byDoc as $doc => $lines) {
    $dt = parseCsvDate($lines[0]['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    if (trim($lines[0]['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    $gross = 0.0;
    $net = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
        $net += parseDecimal($line['item_total_price_no_tax'] ?? '0');
    }
    $zappyDocTotals[$doc] = round($gross, 2);
    $zappyNetNoTax += $net;
    if (trim($lines[0]['status_value'] ?? '') === 'settled' || trim($lines[0]['status'] ?? '') === 'Faturado') {
        $zappyDocTotalsSettled[$doc] = round($gross, 2);
    }
}

// Marcações: todos os estados com price > 0
$zappyAllStatuses = 0.0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
    if ($price > 0 && in_array(trim($row['status'] ?? ''), ['Pagou', 'Chegou', 'Confirmada', 'Agendada'], true)) {
        $zappyAllStatuses += $price;
    }
}

echo "--- Métricas Zappy alternativas ---\n";
echo "vendas.csv bruto (soma linhas):            ".round($vendasCsvGross, 2)." €\n";
echo "vendas.csv por documento (soma totais):    ".round(array_sum($zappyDocTotals), 2)." € (".count($zappyDocTotals)." docs)\n";
echo "vendas.csv settled/faturado:               ".round(array_sum($zappyDocTotalsSettled), 2)." € (".count($zappyDocTotalsSettled)." docs)\n";
echo "vendas.csv sem IVA (item_total_no_tax):    ".round($zappyNetNoTax, 2)." €\n";
echo "marcações Pagou+Chegou+Confirmada+Agendada:".round($zappyAllStatuses, 2)." €\n";

$zappyBaseOnly = 0.0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $zappyBaseOnly += round(parseDecimal($row['price_base'] ?? '0'), 2);
}
echo "marcações Pagou price_base (sem desconto):   ".round($zappyBaseOnly, 2)." €\n";
