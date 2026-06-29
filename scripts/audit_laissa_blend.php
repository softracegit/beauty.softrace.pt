<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Services\ComissoesReportService;
use App\Services\ZappyCommissionHistoricoService;

app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail(1));

$zappy = app(ZappyCommissionHistoricoService::class);
$svc = app(ComissoesReportService::class);

$scenarios = [
    ['desde' => '2026-05-01', 'ate' => '2026-05-31', 'label' => 'May only'],
    ['desde' => '2026-05-01', 'ate' => '2026-06-30', 'label' => 'May-Jun'],
    ['desde' => '2026-06-01', 'ate' => '2026-06-30', 'label' => 'Jun only'],
];

foreach ($scenarios as $s) {
    $filters = [
        'desde' => $s['desde'],
        'ate' => $s['ate'],
        'tecnico' => 2,
        'cliente' => null,
        'servico' => null,
    ];
    $lines = $svc->linesCollection($svc->salesForReport($filters), null, 2);
    $crm = round((float) $lines->sum(fn ($l) => (float) $l->comissao_com_iva), 2);
    $override = $zappy->footerTotals($filters, $lines);
    echo "{$s['label']} ({$s['desde']} → {$s['ate']})\n";
    echo "  CRM lines: {$crm}\n";
    echo '  Override: '.json_encode($override)."\n\n";
}

// June CRM only commission for Laissa
$junLines = $svc->linesCollection(
    $svc->salesForReport(['desde' => '2026-06-01', 'ate' => '2026-06-30', 'tecnico' => 2, 'estado' => null]),
    null,
    2
);
echo 'Laissa June CRM com_iva sum: '.round((float) $junLines->sum(fn ($l) => (float) $l->comissao_com_iva), 2)."\n";
echo 'Config June com_iva: '.(config('zappy_commission_totals.2.2026-06.com_iva') ?? '?')."\n";
