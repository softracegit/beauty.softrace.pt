<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = \App\Models\Client::query()->where('name', 'like', '%Lisa marcos%')->first();
if (! $client) {
    echo "Client not found\n";
    exit(1);
}

echo "Client #{$client->id} {$client->name}\n\n";

$events = \App\Models\CalendarEvent::query()
    ->where('client_id', $client->id)
    ->whereDate('start_at', '2026-02-27')
    ->with(['user', 'eventServiceItems.service'])
    ->orderBy('start_at')
    ->get();

foreach ($events as $ev) {
    echo "Event #{$ev->id} {$ev->start_at} {$ev->status} tech={$ev->user?->name}\n";
    foreach ($ev->eventServiceItems as $es) {
        echo "  svc: {$es->service?->name} price={$es->price}\n";
    }
    $sales = \App\Models\Sale::query()->where('calendar_event_id', $ev->id)->orWhere('numero_fatura', 'like', '%@'.$ev->id)->get(['id','numero_fatura','total','status']);
    foreach ($sales as $s) {
        echo "  sale #{$s->id} {$s->numero_fatura} total={$s->total}\n";
    }
    echo "\n";
}

$allSales = \App\Models\Sale::query()
    ->where('client_id', $client->id)
    ->whereDate('data_emissao', '2026-02-27')
    ->with('items')
    ->get();

echo "=== All sales 27/02 ===\n";
foreach ($allSales as $s) {
    echo "#{$s->id} {$s->numero_fatura} total={$s->total} items=".$s->items->count()."\n";
    foreach ($s->items as $i) {
        echo "  {$i->descricao} {$i->subtotal}\n";
    }
}

echo "\n=== Invoice 2788 variants ===\n";
$variants = \App\Models\Sale::query()->where('numero_fatura', 'like', 'FR CAIXA1/2788%')->with('items')->get();
foreach ($variants as $s) {
    echo "#{$s->id} {$s->numero_fatura} event={$s->calendar_event_id} total={$s->total}\n";
}
