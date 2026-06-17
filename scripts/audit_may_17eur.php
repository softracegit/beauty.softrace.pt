<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');

function parseDecimal(string $value): float
{
    $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
    if ($value === '') return 0.0;
    if (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace('.', '', $value);
    return (float) str_replace(',', '.', $value);
}

// Marcações Pagou em Maio/2026 por payment_date
$marcByKey = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') continue;
    $pay = trim($row['payment_date'] ?? '');
    if ($pay === '') continue;
    try {
        $dt = Carbon::createFromFormat('d/m/Y H:i', $pay, $tz);
    } catch (\Throwable) {
        continue;
    }
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) continue;
    $key = trim($row['client_name'] ?? '').'|'.$pay.'|'.trim($row['item_name'] ?? '');
    $marcByKey[$key] = parseDecimal($row['price_base'] ?? '0');
}

$marcSum = round(array_sum($marcByKey), 2);

// CRM sales Maio 2026
$sales = Sale::query()
    ->where('store_id', $storeId)
    ->whereYear('data_emissao', $year)
    ->whereMonth('data_emissao', $month)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->with('calendarEvent.client')
    ->get();

$synth = 0.0;
$real = 0.0;
$synthList = [];
$realList = [];

foreach ($sales as $sale) {
    $t = (float) $sale->total;
    $n = (string) $sale->numero_fatura;
    if (str_starts_with($n, 'ZAPPY-')) {
        $synth += $t;
        $synthList[] = ['num' => $n, 'total' => $t, 'event' => $sale->calendar_event_id, 'client' => $sale->client?->name];
    } else {
        $real += $t;
        $realList[] = ['num' => $n, 'total' => $t];
    }
}

echo "Marcacoes Pagou Maio/2026 (payment_date): {$marcSum} €\n";
echo "CRM total: ".round($sales->sum('total'), 2)." €\n";
echo "  Real (FR/...): {$real} €\n";
echo "  Sintéticas ZAPPY-*: {$synth} €\n";
echo "CRM - Marcações: ".round($sales->sum('total') - $marcSum, 2)." €\n";
echo "Se Zappy ~9220, CRM-Zappy user ~17€ => CRM ".round($sales->sum('total'), 2)." vs 9220 diff=".round($sales->sum('total') - 9220, 2)."\n\n";

// Synthetic sales: do they overlap events that also have FR sale?
$overlap = 0.0;
$overlapItems = [];
foreach ($sales as $sale) {
    if (! str_starts_with((string) $sale->numero_fatura, 'ZAPPY-')) {
        continue;
    }
    $eventId = (int) $sale->calendar_event_id;
    $hasFr = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $eventId)
        ->where('status', '!=', Sale::STATUS_ANULADO)
        ->where('numero_fatura', 'not like', 'ZAPPY-%')
        ->exists();
    if ($hasFr) {
        $overlap += (float) $sale->total;
        $overlapItems[] = ['event' => $eventId, 'total' => (float) $sale->total, 'num' => $sale->numero_fatura, 'client' => $sale->client?->name];
    }
}

echo "Sintéticas em eventos que JÁ têm fatura FR: ".round($overlap, 2)." € (".count($overlapItems)." vendas)\n";
foreach (array_slice($overlapItems, 0, 10) as $item) {
    echo "  event#{$item['event']} {$item['client']} {$item['total']}€ {$item['num']}\n";
}

// Compare event subtotal vs sale for May events
$eventDiffs = [];
foreach ($sales as $sale) {
    if (! $sale->calendar_event_id) continue;
    $event = CalendarEvent::with('eventServices')->find($sale->calendar_event_id);
    if (! $event) continue;
    $sub = round((float) $event->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
    $st = round((float) $sale->total, 2);
    if (abs($sub - $st) > 0.02) {
        $eventDiffs[] = ['event' => $event->id, 'client' => $event->client?->name, 'sub' => $sub, 'sale' => $st, 'num' => $sale->numero_fatura, 'diff' => round($st - $sub, 2)];
    }
}
usort($eventDiffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
echo "\nEvento subtotal != venda total (top 10, Maio 2026):\n";
foreach (array_slice($eventDiffs, 0, 10) as $d) {
    echo "  #{$d['event']} {$d['client']}: marcação={$d['sub']} venda={$d['sale']} diff={$d['diff']} ({$d['num']})\n";
}
$sumEventDiff = round(array_sum(array_column($eventDiffs, 'diff')), 2);
echo "Soma diffs evento vs venda: {$sumEventDiff} €\n";

// What if CRM report matched Zappy = only real FR (no synthetic)?
echo "\nCRM sem sintéticas: {$real} € (vs Zappy ~9220, diff=".round($real - 9220, 2).")\n";
echo "CRM sem sintéticas vs marcacoes: ".round($real - $marcSum, 2)." €\n";
