<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('users')
    ->where('name', 'like', '%Alejandra%')
    ->orWhere('email', 'like', '%alejandra%')
    ->get(['id', 'name', 'email']);

foreach ($rows as $row) {
    echo "{$row->id} | {$row->name} | {$row->email}\n";
}
if ($rows->isEmpty()) {
    echo "NOT FOUND\n";
}
