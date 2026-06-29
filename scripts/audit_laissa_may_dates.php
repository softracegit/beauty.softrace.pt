<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Services\ComissoesReportService;

app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail(1));

$svc = app(ComissoesReportService::class);
$filters = ['desde' => '2026-05-01', 'ate' => '2026-05-31', 'tecnico' => 2, 'estado' => null];
$lines = $svc->linesCollection($svc->salesForReport($filters), null, 2);

$bySaleDate = 0.0;
$boundary = [];
foreach ($lines as $l) {
    $bySaleDate += (float) $l->comissao_com_iva;
    $sale = Sale::query()->find($l->sale_id);
    $event = CalendarEvent::query()->find($sale?->calendar_event_id);
    $saleDate = $sale?->data_emissao?->format('Y-m-d');
    $eventDate = $event?->start_at?->format('Y-m-d');
    if ($saleDate && $eventDate && substr($saleDate, 0, 7) !== substr($eventDate, 0, 7)) {
        $boundary[] = [
            'sale' => $saleDate,
            'event' => $eventDate,
            'client' => $l->cliente,
            'comm' => $l->comissao_com_iva,
            'fatura' => $l->numero_fatura,
        ];
    }
}

echo 'CRM total (event date filter): '.round($bySaleDate, 2).PHP_EOL;
echo 'Lines with sale month != event month: '.count($boundary).PHP_EOL;
foreach (array_slice($boundary, 0, 8) as $b) {
    echo "  event={$b['event']} sale={$b['sale']} comm={$b['comm']} {$b['fatura']}\n";
}
