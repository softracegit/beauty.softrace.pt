<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$clients = App\Models\Client::query()
    ->where('store_id', 1)
    ->where('name', 'like', '%Fabiana%')
    ->get(['id', 'name']);

echo "Clients:\n";
foreach ($clients as $c) {
    echo "  #{$c->id} {$c->name}\n";
}

$c = App\Models\Client::query()
    ->where('store_id', 1)
    ->where(function ($q) {
        $q->where('name', 'like', '%Fabiana%Martins%')
            ->orWhere('name', 'like', '%Fabiana martins%');
    })
    ->first();

if (! $c) {
    echo "No client found\n";
    exit(1);
}

$day = '2026-05-06';
$evs = App\Models\CalendarEvent::query()
    ->where('store_id', 1)
    ->where('client_id', $c->id)
    ->whereDate('start_at', $day)
    ->orderBy('start_at')
    ->with('eventServices')
    ->get();

echo "\nEvents on {$day} for {$c->name}:\n";
foreach ($evs as $e) {
    $sub = round((float) $e->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
    $sales = App\Models\Sale::query()->where('calendar_event_id', $e->id)->where('status', '!=', 'anulado')->get();
    echo "  #{$e->id} {$e->start_at} status={$e->status} subtotal={$sub}\n";
    echo "    title: {$e->title}\n";
    foreach ($e->eventServices as $s) {
        echo "    - {$s->name} ({$s->pivot->price})\n";
    }
    echo "    sales: ".($sales->isEmpty() ? 'NONE' : $sales->pluck('id')->join(','))."\n";
    foreach ($sales as $sale) {
        echo "      sale #{$sale->id} {$sale->numero_fatura} total={$sale->total} scope={$sale->scope}\n";
    }
}

$salesDay = App\Models\Sale::query()
    ->where('store_id', 1)
    ->where('client_id', $c->id)
    ->whereDate('data_emissao', $day)
    ->get();

echo "\nAll sales for client on {$day}:\n";
foreach ($salesDay as $s) {
    $ev = $s->calendar_event_id ? App\Models\CalendarEvent::query()->find($s->calendar_event_id) : null;
    echo "  #{$s->id} event={$s->calendar_event_id} ({$ev?->status}) {$s->numero_fatura} total={$s->total}\n";
}
