<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$storeId = 1;
$organizationId = (int) (DB::table('stores')->where('id', $storeId)->value('organization_id') ?? 1);
$name = 'Alejandra Silva';
$email = 'alejandra.silva@historico.zappy';
$now = now();

$user = User::query()->firstOrCreate(
    ['email' => $email],
    [
        'organization_id' => $organizationId,
        'name' => $name,
        'password' => Hash::make(Str::random(64)),
        'role' => User::ROLE_PRESTADOR,
        'must_set_password' => false,
    ],
);

$agendaOrder = (int) (DB::table('agents')->where('store_id', $storeId)->max('agenda_order') ?? 0) + 1;

Agent::query()->firstOrCreate(
    ['store_id' => $storeId, 'user_id' => $user->id],
    [
        'name' => $name,
        'status' => Agent::STATUS_INACTIVE,
        'visible_in_agenda' => false,
        'visible_in_booking' => false,
        'agenda_order' => $agendaOrder,
    ],
);

DB::table('store_user')->updateOrInsert(
    ['user_id' => $user->id, 'store_id' => $storeId],
    ['created_at' => $now, 'updated_at' => $now],
);

echo "user#{$user->id} {$name} <{$email}>\n";
