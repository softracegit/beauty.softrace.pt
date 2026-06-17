<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;

$clientName = $argv[1] ?? 'Renata Abade';
$date = $argv[2] ?? '2026-05-08';

$client = Client::query()->where('store_id', 1)->where('name', 'like', '%'.$clientName.'%')->first();
if ($client === null) {
    echo "Client not found: {$clientName}\n";
    exit(1);
}

echo "client_id={$client->id} {$client->name}\n";

$events = CalendarEvent::query()
    ->where('store_id', 1)
    ->where('client_id', $client->id)
    ->whereDate('start_at', $date)
    ->orderBy('start_at')
    ->get();

foreach ($events as $e) {
    $sales = Sale::query()->where('store_id', 1)->where('calendar_event_id', $e->id)->get();
    echo "event#{$e->id} {$e->start_at} status={$e->status} user_id={$e->user_id}\n";
    echo "  desc: ".substr((string) $e->description, 0, 80)."\n";
    if ($sales->isEmpty()) {
        echo "  NO SALE linked\n";
    }
    foreach ($sales as $s) {
        echo "  sale#{$s->id} total={$s->total} paid={$s->valor_pago} desconto={$s->desconto} scope={$s->scope} invoice={$s->numero_fatura}\n";
    }
}
