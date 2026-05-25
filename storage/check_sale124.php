<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s = App\Models\Sale::with('items')->find(124);
foreach ($s->items as $i) {
    echo $i->descricao.' '.$i->subtotal.' ces='.$i->calendar_event_service_id.PHP_EOL;
}
