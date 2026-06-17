<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = file_get_contents(__DIR__.'/../storage/app/exports/zappy_historic_technicians.sql');
DB::unprepared($sql);

$rows = DB::table('users')
    ->where('email', 'like', '%@historico.zappy')
    ->orderBy('id')
    ->get(['id', 'name', 'email']);

echo "Created/found {$rows->count()} users:\n";
foreach ($rows as $row) {
    echo "  {$row->id} | {$row->name} | {$row->email}\n";
}
