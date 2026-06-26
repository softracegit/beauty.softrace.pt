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
$desde = '2026-05-01';
$ate = '2026-05-31';
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));
$svc = app(VendasReportService::class);

// Dashboard actual
$dash = (float) Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereDate('data_emissao', '>=', $desde)->whereDate('data_emissao', '<=', $ate)->sum('total');

$dashMarc = (float) Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereDate('data_emissao', '>=', $desde)->whereDate('data_emissao', '<=', $ate)
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', '!=', CalendarEvent::STATUS_CANCELADO))->sum('total');

$receitaMarc = (float) Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
        ->where('event_type', CalendarEvent::TYPE_MARCACAO)
        ->where('status', CalendarEvent::STATUS_COMPLETO)
        ->whereDate('start_at', '>=', $desde)->whereDate('start_at', '<=', $ate))->sum('total');

foreach ([VendasReportService::DATE_CRITERION_EMISSAO, VendasReportService::DATE_CRITERION_MARCACAO] as $mode) {
    $sales = $svc->reportQuery(['desde' => $desde, 'ate' => $ate, 'data_criterio' => $mode])->get();
    $lines = $svc->resumoCollection($sales, null, null, $mode);
    $footer = $svc->totaisRodape($lines, $mode, $sales);
    echo "{$mode}: {$sales->count()} vendas | lines {$footer['total_valor_com_gorjeta']} | sum(total) ".round((float) $sales->sum('total'), 2)
        .' | pago '.round((float) $sales->where('status', Sale::STATUS_PAGO)->sum('total'), 2)."\n";
}

echo "\nDashboard resumoVendasEntre (PAGO, emissão, sem filtro marcação): {$dash}\n";
echo "Dashboard + filtro marcação: {$dashMarc}\n";
echo "receitaMarcacoesEntre (PAGO, COMPLETO, start_at): {$receitaMarc}\n";

$extra = Sale::query()->where('store_id', $storeId)->where('status', Sale::STATUS_PAGO)
    ->whereDate('data_emissao', '>=', $desde)->whereDate('data_emissao', '<=', $ate)
    ->where(function ($q) use ($storeId) {
        $q->whereNull('calendar_event_id')
            ->orWhereDoesntHave('calendarEvent', fn ($cq) => $cq->where('store_id', $storeId)
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->where('status', '!=', CalendarEvent::STATUS_CANCELADO));
    })->get(['id', 'total', 'numero_fatura', 'calendar_event_id']);
echo "\nVendas PAGO emissão Maio FORA marcação: ".round((float) $extra->sum('total'), 2).' ('.$extra->count().")\n";
foreach ($extra as $s) {
    echo "  #{$s->id} {$s->numero_fatura} {$s->total}€\n";
}
