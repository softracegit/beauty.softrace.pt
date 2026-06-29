<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Services\ComissoesReportService;
use App\Services\ZappyCommissionHistoricoService;

$storeId = (int) (config('zappy_import.default_store_id') ?: 1);
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

$filters = [
    'desde' => '2026-05-01',
    'ate' => '2026-05-31',
    'tecnico' => 2,
    'cliente' => null,
    'servico' => null,
    'estado' => null,
];

$svc = app(ComissoesReportService::class);
$zappySvc = app(ZappyCommissionHistoricoService::class);

$sales = $svc->salesForReport($filters);
$lines = $svc->linesCollection($sales, null, 2);
$crmRaw = round((float) $lines->sum(fn ($l) => (float) $l->comissao_com_iva), 2);
$crmRodape = $svc->totaisRodape($lines, $filters);
$zappyOverride = $zappySvc->footerTotals($filters, $lines);
$config = config('zappy_commission_totals.2.2026-05', []);

echo "Laissa Osto (user_id=2) Maio 2026\n";
echo "Config Zappy com_iva: ".($config['com_iva'] ?? '?')."\n";
echo "CRM lines sum com_iva: {$crmRaw}\n";
echo "CRM totaisRodape: ".json_encode($crmRodape)."\n";
echo "Zappy override rodapé: ".json_encode($zappyOverride)."\n";
echo "Lines: ".$lines->count()."\n";
echo "Diff CRM - Zappy config: ".round($crmRaw - (float) ($config['com_iva'] ?? 0), 2)."\n";
