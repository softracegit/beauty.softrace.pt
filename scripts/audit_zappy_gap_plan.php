<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
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

// --- Zappy marcações Pagou May: each CSV row ---
$zappyLines = [];
$zappyTotal = 0.0;
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

// --- CRM events COMPLETO May ---
$events = CalendarEvent::query()
    ->where('store_id', $storeId)
    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
    ->where('status', CalendarEvent::STATUS_COMPLETO)
    ->whereDate('start_at', '>=', "{$year}-05-01")
    ->whereDate('start_at', '<=', "{$year}-05-31")
    ->with(['client', 'user', 'eventServiceItems', 'eventServiceItems.service'])
    ->get();

$eventSubtotal = 0.0;
$crmSalesTotal = 0.0;
$priceDiffs = [];
$eventsWithoutZappyRef = 0;

foreach ($events as $ev) {
    $sub = round((float) $ev->eventServiceItems->sum(fn ($es) => (float) $es->price), 2);
    $eventSubtotal += $sub;

    $sale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $ev->id)
        ->where('status', Sale::STATUS_PAGO)
        ->first();
    $saleTotal = $sale ? round((float) $sale->total, 2) : 0.0;
    $crmSalesTotal += $saleTotal;

    $ref = ZappyImportRef::query()
        ->where('store_id', $storeId)
        ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
        ->where('local_id', $ev->id)
        ->first();
    if (! $ref) {
        $eventsWithoutZappyRef++;
    }

    // Find matching zappy lines same client + start minute
    $client = mb_strtolower(trim($ev->client?->name ?? ''));
    $start = $ev->start_at?->timezone($tz)->format('Y-m-d H:i') ?? '';
    $matchingZappy = array_filter($zappyLines, fn ($z) => $z['client'] === $client && $z['start'] === $start);
    $zappySum = round(array_sum(array_column($matchingZappy, 'price')), 2);

    if ($matchingZappy && (abs($zappySum - $saleTotal) > 0.02 || abs($zappySum - $sub) > 0.02)) {
        $priceDiffs[] = [
            'event_id' => $ev->id,
            'client' => $ev->client?->name,
            'start' => $start,
            'zappy_sum' => $zappySum,
            'event_sub' => $sub,
            'sale_total' => $saleTotal,
            'zappy_lines' => count($matchingZappy),
            'services' => $ev->eventServiceItems->count(),
            'numero' => $sale?->numero_fatura,
        ];
    }
}

usort($priceDiffs, fn ($a, $b) => abs($b['zappy_sum'] - $b['sale_total']) <=> abs($a['zappy_sum'] - $a['sale_total']));

$crmUnified = app(\App\Services\VendasReportService::class)->sumVendasPagasPorMarcacao(
    Carbon::create($year, 5, 1)->startOfDay(),
    Carbon::create($year, 5, 31)->endOfDay(),
);

echo "=== Plano gap Zappy vs CRM — Maio {$year} ===\n\n";
echo "Zappy marcações Pagou (soma price_final): {$zappyTotal} € (".count($zappyLines)." linhas)\n";
echo "CRM vendas pagas (marcação completa):      {$crmUnified} €\n";
echo "CRM subtotal preços evento:                ".round($eventSubtotal, 2)." € ({$events->count()} eventos)\n";
echo "Gap CRM vs Zappy:                          ".round($zappyTotal - $crmUnified, 2)." €\n\n";

echo "Eventos sem ZappyImportRef appointment: {$eventsWithoutZappyRef}\n";
echo 'Diferenças preço Zappy vs CRM (top 15 por gap): '.count($priceDiffs)."\n";

$gapFromDiffs = 0.0;
foreach (array_slice($priceDiffs, 0, 15) as $d) {
    $gap = round($d['zappy_sum'] - $d['sale_total'], 2);
    $gapFromDiffs += $gap;
    echo "  ev#{$d['event_id']} {$d['client']} {$d['start']}: Zappy {$d['zappy_sum']} | evento {$d['event_sub']} | venda {$d['sale_total']} | Δ {$gap} ({$d['zappy_lines']} linhas Zappy, {$d['services']} svc) {$d['numero']}\n";
}

// Zappy lines with no CRM event match
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
echo "\nLinhas Zappy sem evento CRM (cliente+data): {$unmatchedCount} | ".round($unmatchedZappy, 2)." €\n";

// Missing vendas.csv
$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc !== '') {
        $byDoc[$doc][] = $row;
    }
}
$imported = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('zappy_key')
    ->flip();

$missingDocs = [];
foreach ($byDoc as $doc => $lines) {
    $dt = parseCsvDate($lines[0]['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    if (trim($lines[0]['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    if (isset($imported[$doc])) {
        continue;
    }
    $gross = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
    }
    $missingDocs[$doc] = round($gross, 2);
}

echo "\nFaturas vendas.csv Maio sem importar: ".count($missingDocs).' | '.round(array_sum($missingDocs), 2)." €\n";
foreach ($missingDocs as $doc => $amt) {
    echo "  {$doc}: {$amt} €\n";
}

$vendasCsvGross = 0.0;
foreach ($byDoc as $doc => $lines) {
    $dt = parseCsvDate($lines[0]['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    if (trim($lines[0]['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    foreach ($lines as $line) {
        $vendasCsvGross += parseDecimal($line['item_total_price'] ?? '0');
    }
}
echo "\nZappy vendas.csv Maio bruto: ".round($vendasCsvGross, 2)." €\n";
