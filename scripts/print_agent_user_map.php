<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('users')
    ->where('email', 'like', '%@historico.zappy')
    ->orderBy('name')
    ->get(['id', 'name', 'email']);

echo "'agent_user_map' => [\n";
echo "    // Equipa atual\n";
foreach (config('zappy_import.agent_user_map', []) as $name => $id) {
    echo "    '{$name}' => {$id},\n";
}
echo "    // Histórico Zappy\n";
foreach ($rows as $row) {
    echo "    '{$row->name}' => {$row->id},  // {$row->email}\n";
}
echo "],\n";
