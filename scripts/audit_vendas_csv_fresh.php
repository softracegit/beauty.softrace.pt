<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');
$storeId = 1;

function parseDecimal(string $v): float {
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') return 0.0;
    if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace('.', '', $v);
    return (float) str_replace(',', '.', $v);
}

function parseCsvDate(string $value, string $tz): ?Carbon {
    $value = trim(str_replace('"', '', $value));
    if ($value === '') return null;
    foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
        try {
            return Carbon::createFromFormat($format, $value, $tz);
        } catch (\Throwable) {}
    }
    try { return Carbon::parse($value, $tz); } catch (\Throwable) { return null; }
}

// CSV vendas by invoice date (correct DMY parse)
$byMonth = [];
$byYear = [];
$mayCsv = 0.0; $mayDocs = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['internal_doc_id'] ?? $row['doc_id'] ?? '');
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if ($dt === null) continue;
    $key = $dt->format('Y-m');
    $price = parseDecimal($row['item_total_price'] ?? '0');
    $byMonth[$key] = ($byMonth[$key] ?? 0) + $price;
    $byYear[$dt->year] = ($byYear[$dt->year] ?? 0) + $price;
    if ((int)$dt->year === 2026 && (int)$dt->month === 5) {
        $mayCsv += $price;
        $mayDocs[$doc] = ($mayDocs[$doc] ?? 0) + $price;
    }
}

echo "=== vendas.csv (ficheiro atual) ===\n";
echo "Linhas totais processadas com data válida\n";
echo "Totais por ano: ".json_encode($byYear)."\n";
echo "Maio 2026 CSV (soma linhas item_total_price): ".round($mayCsv, 2)." € (".count($mayDocs)." faturas únicas)\n";
echo "Últimos meses no CSV:\n";
krsort($byMonth);
foreach (array_slice($byMonth, 0, 8, true) as $m => $v) {
    echo "  {$m}: ".round($v, 2)." €\n";
}

// DB data_emissao May 2026
$dbMay = Sale::query()->where('store_id', $storeId)
    ->whereYear('data_emissao', 2026)->whereMonth('data_emissao', 5)
    ->where('status', '!=', Sale::STATUS_ANULADO)->get();
$dbTotal = round((float)$dbMay->sum('total'), 2);
$dbFr = round((float)$dbMay->filter(fn($s)=>!str_starts_with((string)$s->numero_fatura,'ZAPPY-'))->sum('total'), 2);

echo "\n=== BD Maio 2026 data_emissao ===\n";
echo "Total: {$dbTotal} € (FR {$dbFr}, sintéticas ".round($dbTotal-$dbFr,2)." €, {$dbMay->count()} vendas)\n";

// Sample data_emissao distribution in DB for May dashboard sales
$dashboardSales = Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', fn($q)=>$q->where('store_id',$storeId)
        ->where('event_type','marcacao')->where('status','completo')
        ->whereBetween('start_at', [Carbon::create(2026,5,1), Carbon::create(2026,5,31)->endOfDay()]))
    ->get();

$emisMonth = [];
foreach ($dashboardSales as $s) {
    $m = $s->data_emissao ? Carbon::parse($s->data_emissao)->format('Y-m') : 'null';
    $emisMonth[$m] = ($emisMonth[$m] ?? 0) + (float)$s->total;
}
krsort($emisMonth);
echo "\n=== Vendas do dashboard Maio (start_at) — data_emissao real na BD ===\n";
echo "Total dashboard Maio: ".round($dashboardSales->sum('total'),2)." €\n";
foreach (array_slice($emisMonth, 0, 10, true) as $m => $v) {
    echo "  emissão {$m}: ".round($v, 2)." €\n";
}
