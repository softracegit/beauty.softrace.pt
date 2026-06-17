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

$marcTotal = 0.0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') continue;
    $pay = trim($row['payment_date'] ?? '');
    if ($pay === '') continue;
    try { $dt = Carbon::createFromFormat('d/m/Y H:i', $pay, $tz); } catch (\Throwable) { continue; }
    if ((int)$dt->year !== $year || (int)$dt->month !== $month) continue;
    $marcTotal += parseDecimal($row['price_base'] ?? '0');
}

$sales = Sale::query()->where('store_id', $storeId)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)
    ->where('status', '!=', Sale::STATUS_ANULADO)->with(['calendarEvent.eventServices'])->get();

$crmTotal = round((float)$sales->sum('total'), 2);
$fr = round((float)$sales->filter(fn($s)=>!str_starts_with((string)$s->numero_fatura,'ZAPPY-'))->sum('total'), 2);
$synth = round($crmTotal - $fr, 2);

// Duplicate sales per event
$byEvent = [];
foreach ($sales as $s) {
    if (!$s->calendar_event_id) continue;
    $byEvent[$s->calendar_event_id][] = $s;
}
$dupOver = 0.0;
foreach ($byEvent as $eid => $list) {
    if (count($list) < 2) continue;
    $event = $list[0]->calendarEvent;
    $sub = $event ? round((float)$event->eventServices->sum(fn($es)=>(float)($es->pivot->price??0)),2) : 0;
    $sum = round(array_sum(array_map(fn($s)=>(float)$s->total,$list)),2);
    if ($sum > $sub + 0.02) {
        $dupOver += ($sum - $sub);
        echo "DUP event {$eid}: sub={$sub} sales={$sum} (+".round($sum-$sub,2).") ";
        foreach ($list as $s) echo $s->numero_fatura.'='.$s->total.' ';
        echo "\n";
    }
}

// Synthetic where event already has FR sale (any month)
$synthDup = 0.0;
foreach ($sales as $s) {
    if (!str_starts_with((string)$s->numero_fatura,'ZAPPY-') || !$s->calendar_event_id) continue;
    $other = Sale::query()->where('calendar_event_id',$s->calendar_event_id)->where('id','!=',$s->id)
        ->where('status','!=',Sale::STATUS_ANULADO)->where('numero_fatura','not like','ZAPPY-%')->exists();
    if ($other) {
        $synthDup += (float)$s->total;
        echo "SYNTH+FR event {$s->calendar_event_id}: {$s->numero_fatura}={$s->total}\n";
    }
}

// Sale total vs event subtotal (single sale per event)
$over = 0.0; $under = 0.0; $overLines = [];
foreach ($byEvent as $eid => $list) {
    if (count($list) !== 1) continue;
    $event = $list[0]->calendarEvent;
    if (!$event) continue;
    $sub = round((float)$event->eventServices->sum(fn($es)=>(float)($es->pivot->price??0)),2);
    $st = round((float)$list[0]->total,2);
    $diff = round($st - $sub, 2);
    if ($diff > 0.02) { $over += $diff; $overLines[] = [$eid,$sub,$st,$diff,$list[0]->numero_fatura]; }
    elseif ($diff < -0.02) $under += $diff;
}
usort($overLines, fn($a,$b)=>$b[3]<=>$a[3]);

echo "\n=== RESUMO Maio 2026 ===\n";
echo "Marcações Pagou (payment_date): ".round($marcTotal,2)." €\n";
echo "Zappy referência utilizador: ~9220 € (gap vs CSV: ".round(9220-$marcTotal,2)." €)\n";
echo "CRM data_emissao: {$crmTotal} € (FR {$fr} + sintéticas {$synth})\n";
echo "CRM gap vs marcações: ".round($crmTotal-$marcTotal,2)." €\n";
echo "CRM gap vs Zappy 9220: ".round($crmTotal-9220,2)." €\n";
echo "Vendas duplicadas no mesmo evento (soma>subtotal): ".round($dupOver,2)." €\n";
echo "Sintéticas onde já existe FR no evento: ".round($synthDup,2)." €\n";
echo "Venda única > subtotal evento: +".round($over,2)." €\n";
echo "Venda única < subtotal evento: ".round($under,2)." €\n";
echo "\nTop venda > subtotal:\n";
foreach (array_slice($overLines,0,8) as $l) {
    echo "  event {$l[0]}: sub={$l[1]} sale={$l[2]} +{$l[3]} ({$l[4]})\n";
}
