<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyImportService;

$clients = Client::query()
    ->where('store_id', 1)
    ->where('name', 'like', '%Sara Pais%')
    ->get(['id', 'name', 'email', 'phone', 'created_at', 'updated_at']);

echo "=== Clients Sara Pais ===\n";
foreach ($clients as $c) {
    echo "#{$c->id} {$c->name} email={$c->email} phone={$c->phone}\n";
    echo "  created_at={$c->created_at} updated_at={$c->updated_at}\n";
    $refs = ZappyImportRef::query()
        ->where('store_id', 1)
        ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
        ->where('local_id', $c->id)
        ->get(['zappy_key', 'meta']);
    foreach ($refs as $r) {
        echo "  ref: {$r->zappy_key} meta=".json_encode($r->meta)."\n";
    }
}

$svc = app(ZappyImportService::class);
$r = new ReflectionMethod($svc, 'parseClientCreatedOn');
$r->setAccessible(true);
$parsed = $r->invoke($svc, '2025-08-17 15:50:01');
echo "\nparseClientCreatedOn('2025-08-17 15:50:01') = {$parsed}\n";
