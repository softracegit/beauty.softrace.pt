<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');

function parseDecimal(string $value): float
{
    $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
    if ($value === '') {
        return 0.0;
    }
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }

    return (float) str_replace(',', '.', $value);
}

function inMonth(Carbon $dt, int $year, int $month, string $tz): bool
{
    $local = $dt->copy()->timezone($tz);

    return (int) $local->year === $year && (int) $local->month === $month;
}

// --- CSV Zappy (by doc_id, invoice date) ---
$reader = new ZappyCsvReader();
$byDoc = [];
foreach ($reader->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc === '') {
        continue;
    }
    $byDoc[$doc][] = $row;
}

$csvDocsMay = [];
$csvGrossMay = 0.0;
$csvNetMay = 0.0;
$csvCancelledMay = 0.0;
$ignoredAgentMay = 0.0;
$ignoredAgents = config('zappy_import.ignored_agent_names', []);

foreach ($byDoc as $doc => $lines) {
    $first = $lines[0];
    $dateIso = trim($first['date'] ?? '');
    $dt = $dateIso !== '' ? Carbon::parse($dateIso)->timezone($tz) : null;
    if ($dt === null || ! inMonth($dt, $year, $month, $tz)) {
        continue;
    }

    $gross = 0.0;
    $disc = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
        $disc += parseDecimal($line['item_total_discount'] ?? '0');
    }
    $gross = round($gross, 2);
    $disc = round($disc, 2);
    $net = round($gross - $disc, 2);

    $isCancelled = trim($first['cancelled_by_doc_id'] ?? '') !== ''
        || str_contains(mb_strtolower($first['status_value'] ?? '', 'UTF-8'), 'cancel');
    $performer = trim($first['performer_name'] ?? '');
    $isIgnored = $performer !== '' && in_array($performer, $ignoredAgents, true);

    if ($isIgnored) {
        $ignoredAgentMay += $gross;

        continue;
    }

    if ($isCancelled) {
        $csvCancelledMay += $gross;
    } else {
        $csvGrossMay += $gross;
        $csvNetMay += $net;
    }

    $csvDocsMay[$doc] = [
        'gross' => $gross,
        'net' => $net,
        'disc' => $disc,
        'date' => $dt->format('Y-m-d H:i'),
        'cancelled' => $isCancelled,
    ];
}

// --- CRM DB ---
$dbSalesMay = Sale::query()
    ->where('store_id', $storeId)
    ->whereYear('data_emissao', $year)
    ->whereMonth('data_emissao', $month)
    ->get();

$dbByStatus = [];
$dbTotalAll = 0.0;
$dbTotalPaid = 0.0;
$dbSynthetic = 0.0;
$dbSplit = 0.0;
$dbNotInCsv = 0.0;
$dbValorPago = 0.0;

$zappyDocIds = array_flip(array_keys($byDoc));
$importedSaleIds = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('zappy_key')
    ->flip();

foreach ($dbSalesMay as $sale) {
    $total = round((float) $sale->total, 2);
    $dbTotalAll += $total;
    $dbValorPago += round((float) ($sale->valor_pago ?? $total), 2);
    $status = $sale->status ?? 'unknown';
    $dbByStatus[$status] = round(($dbByStatus[$status] ?? 0) + $total, 2);

    if ($sale->status !== Sale::STATUS_ANULADO) {
        $dbTotalPaid += $total;
    }

    $num = (string) $sale->numero_fatura;
    if (str_contains($num, '@')) {
        $dbSplit += $total;
    }
    if (! isset($importedSaleIds[$num]) && ! isset($zappyDocIds[$num])) {
        $dbSynthetic += $total;
        $dbNotInCsv += $total;
    }
}

// Compare doc by doc for May
$dbByDoc = [];
foreach ($dbSalesMay as $sale) {
    if ($sale->status === Sale::STATUS_ANULADO) {
        continue;
    }
    $num = (string) $sale->numero_fatura;
    $baseDoc = str_contains($num, '@') ? explode('@', $num, 2)[0] : $num;
    $dbByDoc[$num] = round((float) $sale->total, 2);
}

$onlyCsv = [];
$onlyDb = [];
$diffs = [];

$csvDocTotalsForCompare = [];
foreach ($csvDocsMay as $doc => $info) {
    if ($info['cancelled']) {
        continue;
    }
    $csvDocTotalsForCompare[$doc] = $info['gross'];
}

// DB docs in May (non-cancelled), map split back to parent doc
$dbDocAgg = [];
foreach ($dbSalesMay as $sale) {
    if ($sale->status === Sale::STATUS_ANULADO) {
        continue;
    }
    $num = (string) $sale->numero_fatura;
    $baseDoc = str_contains($num, '@') ? explode('@', $num, 2)[0] : $num;
    $dbDocAgg[$baseDoc] = round(($dbDocAgg[$baseDoc] ?? 0) + (float) $sale->total, 2);
}

foreach ($csvDocTotalsForCompare as $doc => $csvTotal) {
    $dbTotal = $dbDocAgg[$doc] ?? null;
    if ($dbTotal === null) {
        $onlyCsv[$doc] = $csvTotal;
    } elseif (abs($dbTotal - $csvTotal) > 0.02) {
        $diffs[$doc] = ['csv' => $csvTotal, 'db' => $dbTotal, 'diff' => round($dbTotal - $csvTotal, 2)];
    }
}

foreach ($dbDocAgg as $doc => $dbTotal) {
    if (! isset($csvDocTotalsForCompare[$doc])) {
        $onlyDb[$doc] = $dbTotal;
    }
}

// Also try data_emissao vs CSV date field mismatch - sales in DB may use different month
$dbByCreatedMay = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereYear('created_at', $year)
    ->whereMonth('created_at', $month)
    ->sum('total');

echo "=== Maio {$year} ===\n\n";
echo "CSV Zappy (faturas não canceladas, excl. técnicos ignorados):\n";
echo '  Faturas: '.count($csvDocTotalsForCompare)."\n";
echo '  Total bruto (item_total_price): '.number_format($csvGrossMay, 2, ',', '.')." €\n";
echo '  Total líquido (bruto - desconto): '.number_format($csvNetMay, 2, ',', '.')." €\n";
echo '  Canceladas no CSV: '.number_format($csvCancelledMay, 2, ',', '.')." €\n";
echo '  Ignoradas (técnico): '.number_format($ignoredAgentMay, 2, ',', '.')." €\n\n";

echo "CRM (data_emissao em Maio):\n";
echo '  Vendas: '.$dbSalesMay->count()."\n";
echo '  Total todas: '.number_format($dbTotalAll, 2, ',', '.')." €\n";
echo '  Total não anuladas: '.number_format($dbTotalPaid, 2, ',', '.')." €\n";
echo '  valor_pago soma: '.number_format($dbValorPago, 2, ',', '.')." €\n";
echo '  Sintéticas / sem CSV: '.number_format($dbSynthetic, 2, ',', '.')." €\n";
echo '  Parte de splits (@event): '.number_format($dbSplit, 2, ',', '.')." €\n";
echo '  Por status: '.json_encode($dbByStatus, JSON_UNESCAPED_UNICODE)."\n\n";

echo 'Diferença CRM não anuladas - CSV bruto: '.number_format($dbTotalPaid - $csvGrossMay, 2, ',', '.')." €\n";
echo 'Diferença CRM não anuladas - CSV líquido: '.number_format($dbTotalPaid - $csvNetMay, 2, ',', '.')." €\n\n";

echo "Faturas só no CSV (Maio): ".count($onlyCsv).' ('.number_format(array_sum($onlyCsv), 2, ',', '.')." €)\n";
foreach (array_slice($onlyCsv, 0, 10, true) as $doc => $t) {
    echo "  {$doc}: {$t} €\n";
}

echo "\nFaturas só no CRM / base doc sem CSV Maio: ".count($onlyDb).' ('.number_format(array_sum($onlyDb), 2, ',', '.')." €)\n";
foreach (array_slice($onlyDb, 0, 15, true) as $doc => $t) {
    echo "  {$doc}: {$t} €\n";
}

echo "\nTotais diferentes (top 15):\n";
uasort($diffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
foreach (array_slice($diffs, 0, 15, true) as $doc => $d) {
    echo "  {$doc}: CSV={$d['csv']} DB={$d['db']} diff={$d['diff']}\n";
}

// Check if Zappy uses date vs due_date
$csvGrossMayDue = 0.0;
foreach ($byDoc as $doc => $lines) {
    $first = $lines[0];
    $due = trim($first['due_date'] ?? '');
    $dt = $due !== '' ? Carbon::parse($due)->timezone($tz) : null;
    if ($dt === null || ! inMonth($dt, $year, $month, $tz)) {
        continue;
    }
    $cancelled = trim($first['cancelled_by_doc_id'] ?? '') !== '';
    if ($cancelled) {
        continue;
    }
    foreach ($lines as $line) {
        $csvGrossMayDue += parseDecimal($line['item_total_price'] ?? '0');
    }
}
echo "\nCSV total bruto Maio (campo due_date): ".number_format($csvGrossMayDue, 2, ',', '.')." €\n";
