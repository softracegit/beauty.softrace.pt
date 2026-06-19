<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\ZappyImportRef;

$names = ['Berta Camilo', 'BERTA CAMILE', 'berta camile', 'berta camilo'];

echo "=== Clients matching Berta ===\n";
$clients = Client::query()
    ->where('store_id', 1)
    ->where(function ($q) {
        $q->where('name', 'like', '%Berta%')
            ->orWhere('name', 'like', '%BERTA%')
            ->orWhere('name', 'like', '%Camile%');
    })
    ->get(['id', 'name', 'email', 'phone', 'created_at']);

foreach ($clients as $c) {
    echo "#{$c->id} | {$c->name} | {$c->email} | {$c->phone}\n";
    echo "  created_at={$c->created_at}\n";
    $refs = ZappyImportRef::query()
        ->where('store_id', 1)
        ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
        ->where('local_id', $c->id)
        ->pluck('zappy_key');
    echo '  refs: '.$refs->join(', ')."\n";
}
