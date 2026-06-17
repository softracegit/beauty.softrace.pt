<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;

$storeId = 1;
$reader = new ZappyCsvReader();
$csvPath = base_path('SmartAdmin-pro/assets/files/vendas.csv');

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

$grouped = [];
foreach ($reader->read($csvPath) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc === '') {
        continue;
    }
    $grouped[$doc][] = $row;
}

$csvTotals = [];
$csvDiscounts = [];
foreach ($grouped as $doc => $lines) {
    $t = 0.0;
    $d = 0.0;
    foreach ($lines as $line) {
        $t += parseDecimal($line['item_total_price'] ?? '0');
        $d += parseDecimal($line['item_total_discount'] ?? '0');
    }
    $csvTotals[$doc] = round($t, 2);
    $csvDiscounts[$doc] = round($d, 2);
}

$saleIds = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('local_id', 'zappy_key');

$mismatches = [];
$missing = 0;
$ok = 0;
$discountMismatch = 0;

foreach ($csvTotals as $doc => $csvTotal) {
    $saleId = $saleIds[$doc] ?? null;
    if ($saleId === null) {
        $sale = Sale::query()->where('store_id', $storeId)->where('numero_fatura', $doc)->first();
    } else {
        $sale = Sale::query()->find($saleId);
    }
    if ($sale === null) {
        $missing++;

        continue;
    }
    $dbTotal = round((float) $sale->total, 2);
    $dbDiscount = round((float) ($sale->desconto ?? 0), 2);
    $csvDisc = $csvDiscounts[$doc] ?? 0.0;
    $totalDiff = abs($dbTotal - $csvTotal);
    $discDiff = abs($dbDiscount - $csvDisc);
    if ($totalDiff > 0.02) {
        $mismatches[] = [
            'doc' => $doc,
            'csv_total' => $csvTotal,
            'db_total' => $dbTotal,
            'csv_disc' => $csvDisc,
            'db_disc' => $dbDiscount,
            'valor_pago' => round((float) $sale->valor_pago, 2),
            'diff' => round($totalDiff, 2),
        ];
    } else {
        $ok++;
        if ($discDiff > 0.02) {
            $discountMismatch++;
        }
    }
}

// Split sales @event
$splitSales = Sale::query()
    ->where('store_id', $storeId)
    ->where('numero_fatura', 'like', '%@%')
    ->count();

echo 'CSV faturas: '.count($csvTotals).PHP_EOL;
echo "OK total: {$ok}".PHP_EOL;
echo "Missing sale: {$missing}".PHP_EOL;
echo 'Total mismatch (>2c): '.count($mismatches).PHP_EOL;
echo "Discount mismatch (total ok): {$discountMismatch}".PHP_EOL;
echo "Split sales (@event): {$splitSales}".PHP_EOL.PHP_EOL;

usort($mismatches, fn ($a, $b) => $b['diff'] <=> $a['diff']);
echo "Top 15 total mismatches:\n";
foreach (array_slice($mismatches, 0, 15) as $m) {
    echo "  {$m['doc']} csv={$m['csv_total']} db={$m['db_total']} disc csv={$m['csv_disc']} db={$m['db_disc']} pago={$m['valor_pago']} diff={$m['diff']}\n";
}

$withDisc = count(array_filter($csvDiscounts, fn ($d) => $d > 0));
echo PHP_EOL."CSV faturas com desconto: {$withDisc}\n";
