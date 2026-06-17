<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Services\ZappyImport\ZappyImportService;

$eventId = 16748;
$event = CalendarEvent::with('eventServices')->find($eventId);
$subtotal = round((float) $event->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
$sales = Sale::query()->where('calendar_event_id', $eventId)->where('status', '!=', Sale::STATUS_ANULADO)->get();
echo "subtotal={$subtotal} sum=".$sales->sum('total')." count=".$sales->count()."\n";

$svc = app(ZappyImportService::class);
$ref = new ReflectionClass($svc);
$m = $ref->getMethod('repairConsolidateDuplicateEventSales');
$m->setAccessible(true);
$r = $m->invoke($svc->run(1, false, false, [], false, false, false, false, false, false, false, false, false, false) ?: $svc, ...[]);
// can't invoke easily - run repair via artisan dry-run
