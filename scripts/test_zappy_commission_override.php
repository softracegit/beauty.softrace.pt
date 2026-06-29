<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ZappyCommissionHistoricoService;

$svc = app(ZappyCommissionHistoricoService::class);

$feb = $svc->footerTotals([
    'desde' => '2026-02-01',
    'ate' => '2026-02-28',
    'tecnico' => '4',
    'cliente' => null,
    'servico' => null,
], collect());

echo "Vanessa Feb 2026:\n";
print_r($feb);

$mar = $svc->footerTotals([
    'desde' => '2026-03-01',
    'ate' => '2026-03-31',
    'tecnico' => '4',
], collect());

echo "Vanessa Mar 2026:\n";
print_r($mar);

$jun = $svc->footerTotals([
    'desde' => '2026-06-01',
    'ate' => '2026-06-30',
    'tecnico' => '4',
], collect());

echo "Vanessa Jun 2026 (should be null = CRM):\n";
var_export($jun);
echo "\n";
