<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

foreach (User::whereIn('id', [2, 3, 4, 5])->get() as $u) {
    echo "user#{$u->id} {$u->name} {$u->email} role={$u->role} org={$u->organization_id}\n";
}
foreach (Agent::whereIn('user_id', [2, 3, 4, 5])->get() as $a) {
    echo "agent#{$a->id} user={$a->user_id} store={$a->store_id} {$a->name} status={$a->status} agenda=".(int)$a->visible_in_agenda." booking=".(int)$a->visible_in_booking." order={$a->agenda_order}\n";
}

echo "max user id: ".(int) DB::table('users')->max('id')."\n";
echo "max agent id: ".(int) DB::table('agents')->max('id')."\n";
echo "store1 org: ".DB::table('stores')->where('id', 1)->value('organization_id')."\n";
echo "user columns: ".implode(', ', Schema::getColumnListing('users'))."\n";
echo "agent columns: ".implode(', ', Schema::getColumnListing('agents'))."\n";
