<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Services\ComissoesReportService;

app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail(1));

$filters = ['desde' => '2026-05-01', 'ate' => '2026-05-31', 'tecnico' => 2, 'estado' => null];
$lines = app(ComissoesReportService::class)
    ->linesCollection(app(ComissoesReportService::class)->salesForReport($filters), null, 2);

echo "CRM FR 01P2026/461 lines:\n";
foreach ($lines as $l) {
    if (stripos((string) $l->numero_fatura, '01P2026/461') === false) {
        continue;
    }
    echo "  {$l->data_emissao?->format('Y-m-d')} | {$l->cliente} | {$l->servico} | valor={$l->valor_com_iva} comm={$l->comissao_com_iva} | {$l->numero_fatura}\n";
}
