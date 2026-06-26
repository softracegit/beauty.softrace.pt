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

// Zappy Pagou rows keyed by appointment date month
$zappyRows = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
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
    $key = mb_strtolower(trim($row['client_name'] ?? '')).'|'.$dt->format('Y-m-d H:i').'|'.$price;
    $zappyRows[$key] = ($zappyRows[$key] ?? 0) + 1;
}

// CRM sales May by emissao — bucket by client+event start+total
$start = Carbon::create($year, $month, 1)->startOfDay();
$end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

$sales = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereYear('data_emissao', $year)
    ->whereMonth('data_emissao', $month)
    ->with(['client', 'calendarEvent'])
    ->get();

$crmByStart = [];
$crmSynthetic = 0.0;
$crmRealFr = 0.0;
foreach ($sales as $sale) {
    $ev = $sale->calendarEvent;
    if (! $ev || $ev->event_type !== CalendarEvent::TYPE_MARCACAO) {
        continue;
    }
    $total = round((float) $sale->total, 2);
    if (str_starts_with((string) $sale->numero_fatura, 'ZAPPY-')) {
        $crmSynthetic += $total;
    } else {
        $crmRealFr += $total;
    }
    $client = mb_strtolower(trim($sale->client?->name ?? ''));
    $when = $ev->start_at?->timezone($tz)->format('Y-m-d H:i') ?? '';
    $key = $client.'|'.$when.'|'.$total;
    $crmByStart[$key] = ($crmByStart[$key] ?? 0) + 1;
}

$onlyZappy = [];
$onlyCrm = [];
foreach ($zappyRows as $key => $count) {
    $crmCount = $crmByStart[$key] ?? 0;
    if ($crmCount < $count) {
        $parts = explode('|', $key);
        $onlyZappy[] = ['key' => $key, 'missing' => $count - $crmCount, 'amount' => (float) ($parts[2] ?? 0)];
    }
}
foreach ($crmByStart as $key => $count) {
    $zCount = $zappyRows[$key] ?? 0;
    if ($count > $zCount) {
        $parts = explode('|', $key);
        $onlyCrm[] = ['key' => $key, 'extra' => $count - $zCount, 'amount' => (float) ($parts[2] ?? 0)];
    }
}

usort($onlyZappy, fn ($a, $b) => $b['amount'] <=> $a['amount']);
usort($onlyCrm, fn ($a, $b) => $b['amount'] <=> $a['amount']);

$missingSum = array_sum(array_map(fn ($r) => $r['amount'] * $r['missing'], $onlyZappy));
$extraSum = array_sum(array_map(fn ($r) => $r['amount'] * $r['extra'], $onlyCrm));

echo "=== Maio {$year}: reconciliação fina ===\n\n";
echo 'Zappy Pagou (data marcação): '.round(array_sum(array_map(fn ($k) => (float) explode('|', $k)[2], array_keys($zappyRows))), 2)." €\n";
echo 'CRM sales.total (data_emissao): '.round((float) $sales->sum('total'), 2)." €\n";
echo "  Sintéticas ZAPPY-*: {$crmSynthetic} €\n";
echo "  Faturas reais (FR…): {$crmRealFr} €\n\n";

echo 'Linhas Zappy sem par CRM (cliente|data|valor): '.count($onlyZappy).' | Soma: '.round($missingSum, 2)." €\n";
foreach (array_slice($onlyZappy, 0, 12) as $row) {
    echo "  - {$row['key']} (x{$row['missing']})\n";
}

echo "\nLinhas CRM sem par Zappy: ".count($onlyCrm).' | Soma: '.round($extraSum, 2)." €\n";
foreach (array_slice($onlyCrm, 0, 12) as $row) {
    echo "  + {$row['key']} (x{$row['extra']})\n";
}

echo "\nGap líquido (Zappy sem par - CRM sem par): ".round($missingSum - $extraSum, 2)." €\n";

// vendas.csv May docs: imported vs synthetic coverage
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

$csvMayGross = 0.0;
$csvMayImported = 0.0;
$csvMayMissing = 0.0;
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
    $gross = round($gross, 2);
    $csvMayGross += $gross;
    if (isset($imported[$doc])) {
        $csvMayImported += $gross;
    } else {
        $csvMayMissing += $gross;
    }
}

echo "\nvendas.csv Maio bruto: {$csvMayGross} €\n";
echo "  Com ref importação: {$csvMayImported} €\n";
echo "  Sem ref (poss. só em marcações): {$csvMayMissing} €\n";
echo 'Diferença vendas.csv vs marcações Pagou: '.round($csvMayGross - 9203, 2)." €\n";
