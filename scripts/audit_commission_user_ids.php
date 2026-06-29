<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "agent_user_map vs users table:\n";
foreach (config('zappy_import.agent_user_map', []) as $mapName => $id) {
    $user = User::query()->find($id, ['id', 'name', 'email']);
    $dbName = $user?->name ?? 'NOT FOUND';
    $match = norm($mapName) === norm($dbName) ? 'OK' : 'MISMATCH';
    echo sprintf("  %3d  map=%-22s  db=%-22s  %s\n", $id, $mapName, $dbName, $match);
}

echo "\nConfig zappy_commission_totals keys:\n";
foreach (array_keys(config('zappy_commission_totals', [])) as $id) {
    $user = User::query()->find((int) $id, ['name']);
    echo sprintf("  %3d => %s\n", $id, $user?->name ?? 'NOT FOUND');
}

function norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

    return preg_replace('/[^a-z ]/', '', $t !== false ? $t : $s) ?? $s;
}
