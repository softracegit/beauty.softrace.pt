<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use Carbon\Carbon;

$start = Carbon::create(2026, 5, 1)->startOfDay();
$end = Carbon::create(2026, 5, 1)->endOfMonth()->endOfDay();

$sales = Sale::query()->where('store_id', 1)->where('status', 'pago')
    ->whereHas('calendarEvent', fn($q)=>$q->where('store_id',1)->where('event_type','marcacao')->where('status','completo')->whereBetween('start_at', [$start,$end]))
    ->get();

echo "Count: ".$sales->count()." Sum: ".$sales->sum('total')."\n";

$byEvent = [];
foreach ($sales as $s) {
    $byEvent[$s->calendar_event_id][] = $s;
}
$multi = 0;
foreach ($byEvent as $eid => $list) {
    if (count($list) < 2) continue;
    $sum = array_sum(array_map(fn($s)=>(float)$s->total, $list));
    $e = CalendarEvent::with('eventServices')->find($eid);
    $sub = $e ? round((float)$e->eventServices->sum(fn($x)=>(float)($x->pivot->price??0)),2) : 0;
    if ($sum > $sub + 0.02) {
        $multi += ($sum - $sub);
        echo "event {$eid}: sub={$sub} sales={$sum} extra=".round($sum-$sub,2)." (".count($list)." sales)\n";
    }
}
echo "Total extra from multi-sales: {$multi}\n";
