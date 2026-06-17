<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use Carbon\Carbon;

$start = Carbon::create(2026, 5, 1)->startOfDay();
$end = Carbon::create(2026, 5, 1)->endOfMonth()->endOfDay();
$storeId = 1;

$sales = Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', CalendarEvent::STATUS_COMPLETO)
        ->whereBetween('start_at', [$start, $end]))
    ->with('calendarEvent.eventServices')->get();

$byEvent = [];
foreach ($sales as $s) {
    if (!$s->calendar_event_id) continue;
    $byEvent[$s->calendar_event_id][] = $s;
}

$rows = [];
foreach ($byEvent as $eid => $list) {
    if (count($list) < 2) continue;
    $event = $list[0]->calendarEvent;
    $sub = $event ? round((float)$event->eventServices->sum(fn ($es) => (float)($es->pivot->price ?? 0)), 2) : 0;
    $sum = round(array_sum(array_map(fn ($s) => (float)$s->total, $list)), 2);
    if ($sum <= $sub + 0.02) continue;
    $rows[] = ['eid' => $eid, 'sub' => $sub, 'sum' => $sum, 'extra' => round($sum - $sub, 2),
        'nums' => implode(', ', array_map(fn ($s) => $s->numero_fatura.'='.$s->total, $list))];
}
usort($rows, fn ($a, $b) => $b['extra'] <=> $a['extra']);

echo "Duplicados Maio (dashboard): ".count($rows)." eventos, +".round(array_sum(array_column($rows, 'extra')), 2)." €\n\n";
foreach (array_slice($rows, 0, 15) as $r) {
    echo "event {$r['eid']}: sub={$r['sub']} vendas={$r['sum']} (+{$r['extra']}) {$r['nums']}\n";
}

// sale > subtotal single sale
$singleOver = 0;
foreach ($byEvent as $eid => $list) {
    if (count($list) !== 1) continue;
    $event = $list[0]->calendarEvent;
    $sub = $event ? round((float)$event->eventServices->sum(fn ($es) => (float)($es->pivot->price ?? 0)), 2) : 0;
    $st = round((float)$list[0]->total, 2);
    $diff = round($st - $sub, 2);
    if ($diff > 0.02) $singleOver += $diff;
}
echo "\nVenda única > subtotal (gorjetas etc.): +".round($singleOver, 2)." €\n";
echo "Sem duplicados dashboard seria: ".round($sales->sum('total') - array_sum(array_column($rows, 'extra')), 2)." €\n";
