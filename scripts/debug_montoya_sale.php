<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');
$clientSearch = 'Montoya';

echo "=== BD: cliente Montoya ===\n";
$clients = Client::query()->where('store_id', $storeId)->where('name', 'like', '%Montoya%')->get();
foreach ($clients as $c) {
    echo "Client #{$c->id}: {$c->name}\n";
}

$targetDate = Carbon::create(2025, 4, 12, 18, 15, 0, $tz);
$targetDateUtc = $targetDate->copy()->timezone(config('app.timezone'));

echo "\n=== BD: eventos 12/04/2025 ~18:15 Montoya ===\n";
foreach ($clients as $c) {
    $events = CalendarEvent::query()
        ->where('store_id', $storeId)
        ->where('client_id', $c->id)
        ->whereBetween('start_at', [
            $targetDateUtc->copy()->subHours(2),
            $targetDateUtc->copy()->addHours(2),
        ])
        ->with(['user', 'eventServices', 'sales'])
        ->get();

    foreach ($events as $e) {
        $services = $e->eventServices->map(fn($es) => ($es->name ?? '?').' ('.$es->pivot->price.'€)')->join('; ');
        echo "Event #{$e->id}: {$e->start_at} status={$e->status} tech={$e->user?->name}\n";
        echo "  title: {$e->title}\n";
        echo "  services: {$services}\n";
        foreach ($e->sales as $s) {
            echo "  SALE #{$s->id}: {$s->numero_fatura} total={$s->total} status={$s->status} emissao={$s->data_emissao}\n";
        }
    }
}

echo "\n=== BD: vendas Montoya Abr-Mai 2025 ===\n";
foreach ($clients as $c) {
    $sales = Sale::query()
        ->where('store_id', $storeId)
        ->where('client_id', $c->id)
        ->whereYear('data_emissao', 2025)
        ->whereMonth('data_emissao', '>=', 4)
        ->whereMonth('data_emissao', '<=', 5)
        ->with('calendarEvent')
        ->orderBy('data_emissao')
        ->get();
    foreach ($sales as $s) {
        $ev = $s->calendarEvent;
        echo "Sale #{$s->id}: {$s->numero_fatura} total={$s->total} emissao={$s->data_emissao} event=".($ev?->id ?? 'null')." start=".($ev?->start_at ?? 'n/a')." status=".($ev?->status ?? 'n/a')."\n";
    }
}

echo "\n=== CSV marcacoes: Montoya Abr-Mai 2025 ===\n";
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
  $name = trim($row['client_name'] ?? '');
  if (stripos($name, 'Montoya') === false) continue;
  $d = trim($row['date'] ?? '');
  if (!str_contains($d, '2025-04') && !str_contains($d, '2025-05') && !str_contains($d, '04/2025') && !str_contains($d, '05/2025')) continue;
  echo "{$d} | {$row['status']} | {$name} | ".trim($row['item_name'] ?? '')." | ".trim($row['service_provider'] ?? '')." | price=".trim($row['price_base'] ?? '')."\n";
}

echo "\n=== CSV vendas: Montoya ===\n";
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
  $name = trim($row['client_name'] ?? '');
  if (stripos($name, 'Montoya') === false) continue;
  $d = trim($row['date'] ?? '');
  echo "{$d} | ".trim($row['doc_id'] ?? '')." | {$name} | ".trim($row['item_name'] ?? '')." | ".trim($row['item_total_price'] ?? '')." | appt=".trim($row['appointment_id'] ?? '')."\n";
}
