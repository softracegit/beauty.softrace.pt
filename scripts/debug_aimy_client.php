<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\ZappyImportRef;

foreach (Client::query()->where('store_id', 1)->where('name', 'like', '%Aimy%')->get() as $c) {
    echo "#{$c->id} {$c->name} email={$c->email} phone={$c->phone} created={$c->created_at}\n";
    foreach (ZappyImportRef::query()->where('local_id', $c->id)->where('entity_type', ZappyImportRef::TYPE_CLIENT)->pluck('zappy_key') as $k) {
        echo "  ref: {$k}\n";
    }
}
