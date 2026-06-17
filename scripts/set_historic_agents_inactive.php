<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$updated = DB::table('agents')
    ->join('users', 'users.id', '=', 'agents.user_id')
    ->where('users.email', 'like', '%@historico.zappy')
    ->update([
        'agents.status' => 'inactive',
        'agents.visible_in_agenda' => 0,
        'agents.visible_in_booking' => 0,
        'agents.updated_at' => now(),
    ]);

echo "Updated {$updated} historic agents to inactive.\n";
