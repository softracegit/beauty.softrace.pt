<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$month = 5;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');

function parseDecimal(string $value): float
{
    $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
    if ($value === '') return 0.0;
    if (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace('.', '', $value);
    return (float) str_replace(',', '.', $value);
}

$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc !== '') $byDoc[$doc][] = $row;
}

foreach ([2024, 2025, 2026] as $year) {
    $csv = 0.0;
    foreach ($byDoc as $lines) {
        $first = $lines[0];
        $dt = Carbon::parse(trim($first['date'] ?? ''))->timezone($tz);
        if ((int)$dt->month !== $month || (int)$dt->year !== $year) continue;
        if (trim($first['cancelled_by_doc_id'] ?? '') !== '') continue;
        foreach ($lines as $l) $csv += parseDecimal($l['item_total_price'] ?? '0');
    }
    $db = (float) Sale::query()->where('store_id', $storeId)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->sum('total');
    $dbNoSynth = (float) Sale::query()->where('store_id', $storeId)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->where('numero_fatura', 'not like', 'ZAPPY-%')->sum('total');
    $synth = $db - $dbNoSynth;
    echo "Maio {$year}: CSV={$csv} CRM={$db} CRM_sem_sintéticas={$dbNoSynth} sintéticas={$synth}\n";
}

// CRM split: ZAPPY vs FR
$dbMay26 = Sale::query()->where('store_id', $storeId)->whereYear('data_emissao', 2026)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->get();
$zappy = 0; $fr = 0; $split = 0;
foreach ($dbMay26 as $s) {
    $n = (string)$s->numero_fatura;
    $t = (float)$s->total;
    if (str_starts_with($n, 'ZAPPY-')) $zappy += $t;
    elseif (str_contains($n, '@')) $split += $t;
    else $fr += $t;
}
echo "\nMaio 2026 CRM breakdown: FR={$fr} ZAPPY-synth={$zappy} split@={$split} total=".($fr+$zappy+$split)."\n";

// Try marcacoes Pagou in May by payment_date
$marcTotal = 0.0;
$marcCount = 0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') continue;
    $pay = trim($row['payment_date'] ?? '');
    if ($pay === '') continue;
    $dt = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $pay, $tz);
    if ((int)$dt->month !== 5 || (int)$dt->year !== 2026) continue;
    $marcTotal += parseDecimal($row['price_base'] ?? '0');
    $marcCount++;
}
echo "\nMarcacoes Pagou Maio/2026 por payment_date: {$marcCount} linhas, total price_base={$marcTotal}\n";

// All years May CSV
$csvAllMay = 0.0;
foreach ($byDoc as $lines) {
    $first = $lines[0];
    $dt = Carbon::parse(trim($first['date'] ?? ''))->timezone($tz);
    if ((int)$dt->month !== $month) continue;
    if (trim($first['cancelled_by_doc_id'] ?? '') !== '') continue;
    foreach ($lines as $l) $csvAllMay += parseDecimal($l['item_total_price'] ?? '0');
}
$dbAllMay = (float) Sale::query()->where('store_id', $storeId)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->sum('total');
echo "\nMaio todos os anos: CSV={$csvAllMay} CRM={$dbAllMay}\n";
