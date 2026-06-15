<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Agent::with('user:id,email,name,role')->orderBy('id')->get() as $a) {
    echo implode('|', [
        $a->id,
        $a->user_id,
        $a->name,
        $a->user?->email ?? '',
        $a->user?->role ?? '',
        $a->store_id,
    ])."\n";
}
