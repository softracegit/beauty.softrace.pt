<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
$desde = sprintf('%04d-%02d-01', $year, $month);
$ate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
$start = Carbon::create($year, $month, 1)->startOfDay();
$end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

function parseDecimal(string $v): float
{
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }
    if (str_contains($v, ',') && str_contains($v, '.')) {
        $v = str_replace('.', '', $v);
    }

    return (float) str_replace(',', '.', $v);
}

$events = CalendarEvent::query()
    ->where('store_id', $storeId)
    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
    ->where('status', CalendarEvent::STATUS_COMPLETO)
    ->whereDate('start_at', '>=', $desde)
    ->whereDate('start_at', '<=', $ate)
    ->with(['eventServiceItems.extras'])
    ->get();

$subtotalMarcacoes = $events->sum(function ($ev) {
    return $ev->eventServiceItems->sum(function ($es) {
        return (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price);
    });
});

$withSale = 0;
$withoutSale = 0;
$subtotalSemVenda = 0.0;
foreach ($events as $ev) {
    $hasSale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $ev->id)
        ->where('status', '!=', Sale::STATUS_ANULADO)
        ->exists();
    if ($hasSale) {
        $withSale++;
    } else {
        $withoutSale++;
        $subtotalSemVenda += $ev->eventServiceItems->sum(fn ($es) => (float) $es->price + $es->extras->sum(fn ($x) => (float) $x->price));
    }
}

$zappy = 0.0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = Carbon::parse(trim($row['date'] ?? ''), config('zappy_import.source_timezone', 'Europe/Lisbon'));
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $zappy += parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0');
}

echo "Maio {$year}\n";
echo "Zappy marcações Pagou: {$zappy} €\n";
echo "CRM subtotal marcações COMPLETO (start_at): ".round($subtotalMarcacoes, 2)." € ({$events->count()} eventos)\n";
echo "  Com venda: {$withSale} | Sem venda: {$withoutSale} | Subtotal sem venda: ".round($subtotalSemVenda, 2)." €\n";
