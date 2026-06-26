<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Services\VendasReportService;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
$desde = sprintf('%04d-%02d-01', $year, $month);
$ate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));
$svc = app(VendasReportService::class);

foreach ([
    VendasReportService::DATE_CRITERION_EMISSAO => 'Data fatura',
    VendasReportService::DATE_CRITERION_MARCACAO => 'Data marcação',
] as $mode => $label) {
    $sales = $svc->reportQuery([
        'desde' => $desde,
        'ate' => $ate,
        'data_criterio' => $mode,
    ])->with(['calendarEvent', 'items'])->get();
    $lines = $svc->resumoCollection($sales, null, null, $mode);
    $totais = $svc->totaisRodape($lines, $mode, $sales);
    echo "{$label}: {$sales->count()} vendas | rodapé {$totais['total_valor_com_gorjeta']} € | sum(total) ".round((float) $sales->sum('total'), 2)." €\n";
}

$start = Carbon::create($year, $month, 1)->startOfDay();
$end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

$emissaoMay = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO])
    ->whereDate('data_emissao', '>=', $desde)
    ->whereDate('data_emissao', '<=', $ate)
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO))
    ->pluck('id');

$startMay = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO])
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
        ->whereDate('start_at', '>=', $desde)
        ->whereDate('start_at', '<=', $ate))
    ->pluck('id');

$onlyEmissao = $emissaoMay->diff($startMay);
$onlyStart = $startMay->diff($emissaoMay);

echo "\nSó emissão Maio: ".$onlyEmissao->count()." vendas\n";
echo "Só marcação Maio: ".$onlyStart->count()." vendas\n";

if ($onlyEmissao->isNotEmpty()) {
    $rows = Sale::with('calendarEvent')->whereIn('id', $onlyEmissao)->get();
    foreach ($rows->take(10) as $s) {
        echo "  emissão {$s->data_emissao} | marcação ".$s->calendarEvent?->start_at?->format('Y-m-d')." | {$s->total} € | {$s->numero_fatura}\n";
    }
}
if ($onlyStart->isNotEmpty()) {
    $rows = Sale::with('calendarEvent')->whereIn('id', $onlyStart)->get();
    foreach ($rows->take(10) as $s) {
        echo "  emissão {$s->data_emissao} | marcação ".$s->calendarEvent?->start_at?->format('Y-m-d')." | {$s->total} € | {$s->numero_fatura}\n";
    }
}
