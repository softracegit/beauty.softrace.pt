<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$year = 2026; $month = 5; $storeId = 1;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');

function parseDecimal(string $v): float {
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') return 0.0;
    if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace('.', '', $v);
    return (float) str_replace(',', '.', $v);
}

// Marcações Pagou em Maio/2026 (payment_date) por evento aproximado: client+day
$marcByClientDay = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') continue;
    $pay = trim($row['payment_date'] ?? '');
    if ($pay === '') continue;
    try { $dt = Carbon::createFromFormat('d/m/Y H:i', $pay, $tz); } catch (\Throwable) { continue; }
    if ((int)$dt->year !== $year || (int)$dt->month !== $month) continue;
    $day = $dt->format('Y-m-d');
    $key = mb_strtolower(trim($row['client_name'] ?? ''), 'UTF-8').'|'.$day;
    $marcByClientDay[$key] = ($marcByClientDay[$key] ?? 0) + parseDecimal($row['price_base'] ?? '0');
}

$sales = Sale::query()->where('store_id', $storeId)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)
    ->where('status', '!=', Sale::STATUS_ANULADO)->with('calendarEvent.client')->get();

$pos = 0.0; $neg = 0.0;
$examples = [];
foreach ($sales as $sale) {
    $event = $sale->calendarEvent;
    if (!$event || !$event->client) continue;
    $day = $event->start_at->copy()->timezone($tz)->format('Y-m-d');
    $key = mb_strtolower(trim($event->client->name), 'UTF-8').'|'.$day;
    $marc = round($marcByClientDay[$key] ?? 0, 2);
    if ($marc <= 0) continue;
    $st = round((float)$sale->total, 2);
    $diff = round($st - $marc, 2);
    if (abs($diff) < 0.03) continue;
    if ($diff > 0) $pos += $diff; else $neg += $diff;
    $examples[] = ['client'=>$event->client->name,'day'=>$day,'marc'=>$marc,'sale'=>$st,'diff'=>$diff,'num'=>$sale->numero_fatura];
}
usort($examples, fn($a,$b)=>abs($b['diff'])<=>abs($a['diff']));

echo "CRM Maio 2026 total: ".$sales->sum('total')." €\n";
echo "Marcações Pagou (payment_date) Maio: ".round(array_sum($marcByClientDay),2)." €\n";
echo "Diferença CRM vs marcações (por cliente+dia com pagamento): +{$pos} / {$neg} net=".round($pos+$neg,2)." €\n\n";
echo "Exemplos maiores diffs (venda vs soma marcações mesmo dia):\n";
foreach (array_slice($examples,0,12) as $e) {
    echo "  {$e['day']} {$e['client']}: marc={$e['marc']} sale={$e['sale']} diff={$e['diff']} ({$e['num']})\n";
}

// What CRM would be if only counting FR (no synthetic) vs Zappy 9220
$fr = $sales->filter(fn($s)=>!str_starts_with((string)$s->numero_fatura,'ZAPPY-'))->sum('total');
$synth = $sales->filter(fn($s)=>str_starts_with((string)$s->numero_fatura,'ZAPPY-'))->sum('total');
echo "\nCRM FR only: {$fr} € | Sintéticas: {$synth} €\n";
echo "Zappy ~9220 vs CRM 9237 => gap 17€\n";
echo "Zappy ~9220 vs marcações CSV ".round(array_sum($marcByClientDay),2)." => gap ".round(9220-array_sum($marcByClientDay),2)." €\n";
