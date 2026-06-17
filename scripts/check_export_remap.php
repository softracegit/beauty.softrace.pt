<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\StoreDataSqlExporter;
use App\Services\StoreDataSqlPurger;

foreach (['data', 'catalog', 'full'] as $mode) {
    $m = match ($mode) {
        'catalog' => StoreDataSqlPurger::MODE_CATALOG,
        'full' => StoreDataSqlPurger::MODE_FULL,
        default => StoreDataSqlPurger::MODE_DATA,
    };
    $sql = (new StoreDataSqlExporter(1, $m))->export(true);
    $has = str_contains($sql, 'Remapear colunas da agenda');
    echo "{$mode}: ".($has ? 'HAS' : 'NO')." user remap\n";
}
