<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Services\ZappyImport\ZappyImportService;

$client = Client::where('name', 'like', '%Alexandrina%')->first();
$event = CalendarEvent::where('client_id', $client->id)
    ->whereDate('start_at', '2026-05-07')
    ->with('eventServices', 'client')
    ->first();

echo "event#{$event->id} user_id={$event->user_id}\n";
echo "start={$event->start_at} end={$event->end_at}\n";
foreach ($event->eventServices as $es) {
    echo "  service#{$es->id} {$es->name}\n";
}

$importer = app(ZappyImportService::class);
$ref = new ReflectionClass($importer);
$m = $ref->getMethod('buildCsvSlotsForMergedEvent');
$m->setAccessible(true);

$agentMap = config('zappy_import.agent_user_map', []);
$userIdToProvider = [];
foreach ($agentMap as $name => $uid) {
    $userIdToProvider[(int) $uid] = $name;
}
echo "expected provider: ".($userIdToProvider[(int)$event->user_id] ?? 'null')."\n";

$slots = $m->invoke($importer, $event, $userIdToProvider);
echo "slots: ".count($slots)."\n";
foreach ($slots as $s) {
    echo "  {$s['start_at']} - {$s['end_at']} {$s['item_name']}\n";
}
