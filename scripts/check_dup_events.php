<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use Carbon\Carbon;

$start = Carbon::create(2026, 5, 1)->startOfDay();
$end = Carbon::create(2026, 5, 1)->endOfMonth()->endOfDay();

foreach ([16748, 16642, 16806] as $eid) {
    $e = CalendarEvent::find($eid);
    if (!$e) { echo "event {$eid} missing\n"; continue; }
    echo "event {$eid}: start={$e->start_at} status={$e->status}\n";
}

$total = Sale::query()->where('store_id', 1)->where('status', 'pago')
    ->whereHas('calendarEvent', fn($q)=>$q->whereBetween('start_at', [$start,$end])->where('status','completo'))
    ->sum('total');
echo "\nDashboard sum: {$total}\n";

$dupInMay = 0;
foreach ([16748, 16642, 16806] as $eid) {
    $e = CalendarEvent::find($eid);
    if (!$e || !$e->start_at->between($start, $end)) continue;
    $sum = Sale::where('calendar_event_id',$eid)->where('status','pago')->sum('total');
    echo "May dup event {$eid} sales sum: {$sum}\n";
    $dupInMay += $sum;
}
