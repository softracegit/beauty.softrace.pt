<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;

$count = CalendarEvent::query()
    ->where('store_id', 1)
    ->where('event_type', 'marcacao')
    ->where('status', 'chegou')
    ->whereHas('sales', fn ($q) => $q->where('status', 'pago'))
    ->count();

echo "Chegou events with paid sale: {$count}\n";

$rows = CalendarEvent::query()
    ->where('store_id', 1)
    ->where('event_type', 'marcacao')
    ->where('status', 'chegou')
    ->whereHas('sales', fn ($q) => $q->where('status', 'pago'))
    ->with(['client', 'sales'])
    ->orderBy('start_at')
    ->limit(15)
    ->get();

foreach ($rows as $e) {
    $s = $e->sales->first();
    echo "#{$e->id} {$e->start_at} {$e->client?->name} sale={$s?->numero_documento} {$s?->total}\n";
}
