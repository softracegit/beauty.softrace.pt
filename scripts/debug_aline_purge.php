<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyImportService;

$storeId = 1;
$client = Client::query()
    ->where('store_id', $storeId)
    ->where('name', 'like', '%Aline Nicomedes%')
    ->first();

if ($client === null) {
    echo "Aline Nicomedes not found\n";
    echo 'Total clients: '.Client::query()->where('store_id', $storeId)->count()."\n";
    exit(0);
}

echo "Client #{$client->id} {$client->name}\n";
echo "email={$client->email} phone={$client->phone}\n";
echo "created_at={$client->created_at}\n\n";

$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
    ->where('local_id', $client->id)
    ->get();
echo "Zappy refs: ".$refs->count()."\n";
foreach ($refs as $r) {
    echo "  {$r->zappy_key}\n";
}

$events = CalendarEvent::query()->where('client_id', $client->id)->count();
$sales = Sale::query()->where('client_id', $client->id)->count();
$bookings = Booking::query()->where('client_id', $client->id)->count();
$users = User::query()->where('client_id', $client->id)->count();

echo "\nLinked records:\n";
echo "  calendar_events: {$events}\n";
echo "  sales: {$sales}\n";
echo "  bookings: {$bookings}\n";
echo "  users: {$users}\n";

$nonImportedEvents = CalendarEvent::query()
    ->where('client_id', $client->id)
    ->where(function ($q) {
        $q->whereNull('description')
            ->orWhere('description', 'not like', '[Importado Zappy]%');
    })
    ->count();
echo "  events NOT [Importado Zappy]: {$nonImportedEvents}\n";

$nonImportedSales = Sale::query()
    ->where('client_id', $client->id)
    ->where(function ($q) {
        $q->where('issue_without_fiscal_id', false)
            ->orWhere('numero_fatura', 'not like', 'FR%')
            ->orWhereNull('numero_fatura');
    })
    ->count();
echo "  sales possibly non-import: {$nonImportedSales}\n";

$svc = app(ZappyImportService::class);
$r = new ReflectionMethod($svc, 'filterDeletableImportedClientIds');
$r->setAccessible(true);
$eventIds = (new ReflectionMethod($svc, 'collectImportedCalendarEventIds'))->invoke($svc, $storeId);
$saleIds = (new ReflectionMethod($svc, 'collectImportedSaleIds'))->invoke($svc, $storeId, $eventIds);
$clientIds = (new ReflectionMethod($svc, 'collectImportedClientIds'))->invoke($svc, $storeId);
$deletable = $r->invoke($svc, $storeId, $clientIds, $eventIds, $saleIds);

$inCandidates = in_array($client->id, $clientIds, true);
$inDeletable = in_array($client->id, $deletable, true);

echo "\nPurge analysis:\n";
echo '  imported client candidate: '.($inCandidates ? 'yes' : 'no')."\n";
echo '  deletable by purge: '.($inDeletable ? 'yes' : 'no')."\n";
echo '  total clients store: '.Client::query()->where('store_id', $storeId)->count()."\n";

$booking = Booking::query()->where('client_id', $client->id)->first();
if ($booking !== null) {
    echo "\nBooking #{$booking->id}\n";
    echo "  status={$booking->status}\n";
    echo "  created_at={$booking->created_at}\n";
    echo "  calendar_event_id=".($booking->calendar_event_id ?? 'null')."\n";
    echo "  source=".($booking->source ?? 'n/a')."\n";
}
