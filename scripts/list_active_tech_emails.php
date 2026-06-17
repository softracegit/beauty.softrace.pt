<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$names = ['Laissa', 'Sandy', 'Vanessa', 'Andrea'];
$agents = DB::table('agents')->where('store_id', 1)->get();
foreach ($agents as $a) {
    foreach ($names as $n) {
        if (stripos($a->name, $n) !== false) {
            $email = DB::table('users')->where('id', $a->user_id)->value('email');
            echo "{$a->name}: user_id={$a->user_id}, email={$email}, status={$a->status}, agenda={$a->visible_in_agenda}\n";
        }
    }
}
