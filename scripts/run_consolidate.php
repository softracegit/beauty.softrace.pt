<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ZappyImport\ZappyImportService;

$stats = app(ZappyImportService::class)->run(
    storeId: 1,
    dryRun: false,
    fresh: false,
    steps: [],
    repairDistributeSales: true,
);

print_r($stats);

use App\Models\Sale;
foreach ([16748, 16642, 16806] as $eid) {
    $n = Sale::where('calendar_event_id', $eid)->where('status', '!=', 'anulado')->count();
    $sum = Sale::where('calendar_event_id', $eid)->where('status', '!=', 'anulado')->sum('total');
    echo "event {$eid}: {$n} sales, sum={$sum}\n";
}
