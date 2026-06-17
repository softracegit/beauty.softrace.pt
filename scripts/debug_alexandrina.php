<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Client;

$client = Client::where('name', 'like', '%Alexandrina%')->first();
if (! $client) {
    echo "client not found\n";
    exit(1);
}
echo "client#{$client->id} {$client->name}\n";
$events = CalendarEvent::where('client_id', $client->id)
    ->whereDate('start_at', '2026-05-07')
    ->with('eventServices')
    ->orderBy('start_at')
    ->get();
foreach ($events as $e) {
    echo "event#{$e->id} {$e->start_at} - {$e->end_at} status={$e->status}\n";
    foreach ($e->eventServices as $es) {
        echo "  svc id={$es->id} name={$es->name} dur={$es->pivot->duration} price={$es->pivot->price}\n";
    }
}
