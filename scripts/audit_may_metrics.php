<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$year = 2026; $month = 5; $storeId = 1;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');
$start = Carbon::create($year, $month, 1)->startOfDay();
$end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

function parseDecimal(string $v): float {
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') return 0.0;
    if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace('.', '', $v);
    return (float) str_replace(',', '.', $v);
}

// 1) Dashboard: receitaMarcacoesEntre — venda PAGO + marcação COMPLETO + start_at no mês
$dashboard = (float) Sale::query()
    ->where('store_id', $storeId)
    ->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', function ($q) use ($start, $end, $storeId) {
        $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', CalendarEvent::STATUS_COMPLETO)
            ->whereBetween('start_at', [$start, $end]);
    })
    ->sum('total');

// 2) Relatório vendas: data_emissao no mês (qualquer status exc anulado, com evento não cancelado)
$relatorio = (float) Sale::query()
    ->where('store_id', $storeId)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO))
    ->sum('total');

// 3) Marcações Pagou payment_date no mês (CSV)
$marcPay = 0.0;
$marcPayCount = 0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') continue;
    $pay = trim($row['payment_date'] ?? '');
    if ($pay === '') continue;
    try { $dt = Carbon::createFromFormat('d/m/Y H:i', $pay, $tz); } catch (\Throwable) { continue; }
    if ((int)$dt->year !== $year || (int)$dt->month !== $month) continue;
    $marcPay += parseDecimal($row['price_base'] ?? '0');
    $marcPayCount++;
}

// 4) vendas.csv date no mês
$csvVendas = 0.0; $csvVendasCount = 0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $d = trim($row['date'] ?? '');
    if ($d === '') continue;
    try { $dt = Carbon::parse($d, $tz); } catch (\Throwable) { continue; }
    if ((int)$dt->year !== $year || (int)$dt->month !== $month) continue;
    $csvVendas += parseDecimal($row['total'] ?? '0');
    $csvVendasCount++;
}

// 5) Subtotal eventos COMPLETO com start_at no mês (sem venda)
$eventSubtotal = (float) CalendarEvent::query()
    ->where('store_id', $storeId)
    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
    ->where('status', CalendarEvent::STATUS_COMPLETO)
    ->whereBetween('start_at', [$start, $end])
    ->with('eventServices')
    ->get()
    ->sum(fn ($e) => (float) $e->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)));

$salesDashboard = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', function ($q) use ($start, $end, $storeId) {
        $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', CalendarEvent::STATUS_COMPLETO)
            ->whereBetween('start_at', [$start, $end]);
    })->get();

$fr = round((float)$salesDashboard->filter(fn ($s) => !str_starts_with((string)$s->numero_fatura, 'ZAPPY-'))->sum('total'), 2);
$synth = round((float)$salesDashboard->filter(fn ($s) => str_starts_with((string)$s->numero_fatura, 'ZAPPY-'))->sum('total'), 2);

// Vendas com data_emissao fora de Maio mas start_at em Maio (dashboard conta, relatório por emissão não)
$emisFora = (float) Sale::query()
    ->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->where(function ($q) use ($year, $month) {
        $q->whereYear('data_emissao', '!=', $year)->orWhereMonth('data_emissao', '!=', $month);
    })
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', CalendarEvent::STATUS_COMPLETO)
        ->whereBetween('start_at', [$start, $end]))
    ->sum('total');

// Vendas data_emissao Maio mas start_at fora Maio
$startFora = (float) Sale::query()
    ->where('store_id', $storeId)->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)
    ->whereHas('calendarEvent', function ($q) use ($start, $end, $storeId) {
        $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->where(function ($q2) use ($start, $end) {
                $q2->where('start_at', '<', $start)->orWhere('start_at', '>', $end);
            });
    })->sum('total');

// Duplicados no dashboard set
$byEvent = [];
foreach ($salesDashboard as $s) {
    if (!$s->calendar_event_id) continue;
    $byEvent[$s->calendar_event_id][] = $s;
}
$dupExtra = 0.0;
foreach ($byEvent as $eid => $list) {
    if (count($list) < 2) continue;
    $event = $list[0]->calendarEvent;
    $sub = $event ? round((float)$event->eventServices->sum(fn ($es) => (float)($es->pivot->price ?? 0)), 2) : 0;
    $sum = round(array_sum(array_map(fn ($s) => (float)$s->total, $list)), 2);
    if ($sum > $sub + 0.02) $dupExtra += ($sum - $sub);
}

echo "=== Maio {$year} — comparação de métricas ===\n\n";
echo "Zappy referência utilizador:     ~9220 €\n";
echo "Dashboard (start_at + PAGO):     ".round($dashboard, 2)." €  (FR {$fr} + sintéticas {$synth}, {$salesDashboard->count()} vendas)\n";
echo "Relatório (data_emissao):        ".round($relatorio, 2)." €\n";
echo "CSV marcações Pagou payment_date:".round($marcPay, 2)." € ({$marcPayCount} linhas)\n";
echo "CSV vendas.csv date:             ".round($csvVendas, 2)." € ({$csvVendasCount} faturas)\n";
echo "Subtotal eventos COMPLETO start_at Maio: ".round($eventSubtotal, 2)." €\n\n";

echo "Gaps vs Zappy 9220:\n";
echo "  Dashboard - Zappy:  ".round($dashboard - 9220, 2)." €\n";
echo "  Relatório - Zappy:  ".round($relatorio - 9220, 2)." €\n";
echo "  Marcações CSV - Zappy: ".round($marcPay - 9220, 2)." €\n\n";

echo "Cruzamento de datas:\n";
echo "  Dashboard conta, emissão FORA Maio: ".round($emisFora, 2)." €\n";
echo "  Relatório conta, start_at FORA Maio: ".round($startFora, 2)." €\n";
echo "  Vendas duplicadas (soma>subtotal evento): ".round($dupExtra, 2)." €\n\n";

echo "Dashboard - subtotal eventos: ".round($dashboard - $eventSubtotal, 2)." € (gorjetas/faturas>marcação/duplicados)\n";
echo "Dashboard - marcações Pagou CSV: ".round($dashboard - $marcPay, 2)." €\n";
