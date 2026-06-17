<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ZappyImport\ZappyCsvReader;

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

$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc === '') {
        continue;
    }
    $byDoc[$doc][] = $row;
}

foreach ($byDoc as $doc => $lines) {
    $disc = 0.0;
    $total = 0.0;
    foreach ($lines as $line) {
        $disc += parseDecimal($line['item_total_discount'] ?? '0');
        $total += parseDecimal($line['item_total_price'] ?? '0');
    }
    if ($disc <= 0) {
        continue;
    }
    echo "{$doc} gross={$total} disc={$disc} net=".round($total - $disc, 2)." lines=".count($lines)."\n";
}
