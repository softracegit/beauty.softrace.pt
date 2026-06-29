<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\User;

echo "Active service providers (relatórios filter):\n";
foreach (User::activeServiceProviders(1)->orderBy('name')->get(['id', 'name']) as $u) {
    $agent = Agent::query()->where('user_id', $u->id)->first(['id', 'name']);
    echo sprintf("  user_id=%d  agent_id=%s  name=%s  agent_name=%s\n", $u->id, $agent?->id ?? '?', $u->name, $agent?->name ?? '?');
}
