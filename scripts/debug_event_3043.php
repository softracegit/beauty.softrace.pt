<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ApplicableFees;

$e = CalendarEvent::with('eventServices')->find(3043);
echo 'start='.$e->start_at.' end='.$e->end_at.' status='.$e->status.PHP_EOL;
$subtotal = 0;
foreach ($e->eventServices as $s) {
    $p = (float) ($s->pivot->price ?? 0);
    $subtotal += $p;
    echo "  svc {$s->name} price={$p}\n";
}
echo 'subtotal='.$subtotal.PHP_EOL;

$sales = Sale::where('calendar_event_id', 3043)->get();
foreach ($sales as $s) {
    echo "sale#{$s->id} total={$s->total} paid={$s->valor_pago} desconto={$s->desconto} scope={$s->scope}\n";
    foreach (SaleItem::where('sale_id', $s->id)->get() as $si) {
        echo "  item: {$si->description} price={$si->price}\n";
    }
}

$items = $e->eventServiceItems()->with('extras.extra')->get();
$sub = ApplicableFees::servicesExtrasSubtotalFromEventItems($items);
$due = ApplicableFees::amountDueCashFromEventId(3043, $sub);
echo 'ApplicableFees subtotal='.$sub.' amountDue='.$due.PHP_EOL;

$events = CalendarEvent::where('client_id', 1717)->whereDate('start_at', '2026-05-08')->get(['id','start_at','end_at','status']);
echo "events on day: ".$events->count()."\n";
foreach ($events as $ev) {
    echo "  #{$ev->id} {$ev->start_at} - {$ev->end_at}\n";
}
