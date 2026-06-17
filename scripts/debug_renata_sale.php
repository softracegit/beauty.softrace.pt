<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;

$reader = new ZappyCsvReader();
$path = base_path('SmartAdmin-pro/assets/files/vendas.csv');
foreach ($reader->read($path) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc !== 'FR 01P2026/469' && !str_contains($doc, '3385')) {
        continue;
    }
    echo 'doc_id='.$doc.' appt='.trim($row['appointment_id'] ?? '').' item='.trim($row['item_name'] ?? '').' price='.trim($row['item_total_price'] ?? '').PHP_EOL;
}

$refs = ZappyImportRef::where('store_id', 1)->where('entity_type', 'sale')->whereIn('zappy_key', ['FR 01P2026/469', 'FR CAIXA1/3385'])->get();
foreach ($refs as $r) {
    echo "ref {$r->zappy_key} -> sale {$r->local_id}\n";
}
