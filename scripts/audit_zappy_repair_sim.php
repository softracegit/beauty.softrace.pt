<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2026;
$month = 5;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

function parseDecimal(string $v): float
{
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }
    if (str_contains($v, ',') && str_contains($v, '.')) {
        $v = str_replace('.', '', $v);
    }

    return (float) str_replace(',', '.', $v);
}

// Map event_id => sum price_final from refs
$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
    ->get(['zappy_key', 'local_id', 'meta']);

$keyToEvent = [];
foreach ($refs as $ref) {
    $keyToEvent[$ref->zappy_key] = (int) $ref->local_id;
}

$eventZappyTotal = [];
$zappyCsvTotal = 0.0;
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = Carbon::parse(trim($row['date'] ?? ''), $tz);
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
    if ($price <= 0) {
        continue;
    }
    $zappyCsvTotal += $price;

    // Rebuild fingerprint like import - simplified: match by ref meta or skip
}

// Sum via refs: load fingerprints from DB and match CSV rows by parsing fingerprint
// fingerprint format from ZappyImportService - read it
$service = app(\App\Services\ZappyImport\ZappyImportService::class);
$refClass = new ReflectionClass($service);
$fpMethod = $refClass->getMethod('appointmentFingerprint');
$fpMethod->setAccessible(true);

foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = Carbon::parse(trim($row['date'] ?? ''), $tz);
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $price = round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2);
    if ($price <= 0) {
        continue;
    }
    $client = trim($row['client_name'] ?? '');
    $item = trim($row['item_name'] ?? '');
    $provider = trim($row['service_provider'] ?? '');
    $userId = (int) (config('zappy_import.agent_user_map')[$provider] ?? 0);
    $fp = $fpMethod->invoke($service, $dt, $client, $item, $userId);
    $eventId = $keyToEvent[$fp] ?? null;
    if ($eventId) {
        $eventZappyTotal[$eventId] = ($eventZappyTotal[$eventId] ?? 0) + $price;
    }
}

$crmCurrent = 0.0;
$crmAfterRepair = 0.0;
$adjustments = 0;
$adjustmentSum = 0.0;

$crmAfterRepair = 0.0;
$excludedZappy = 0.0;
$excludedReasons = [];

foreach ($eventZappyTotal as $eventId => $zTotal) {
    $ev = CalendarEvent::find($eventId);
    if (! $ev) {
        $excludedZappy += $zTotal;
        $excludedReasons['missing_event'] = ($excludedReasons['missing_event'] ?? 0) + $zTotal;
        continue;
    }
    if ($ev->status !== CalendarEvent::STATUS_COMPLETO) {
        $excludedZappy += $zTotal;
        $excludedReasons['not_completo:'.$ev->status] = ($excludedReasons['not_completo:'.$ev->status] ?? 0) + $zTotal;
        continue;
    }
    if ((int) $ev->start_at->year !== $year || (int) $ev->start_at->month !== $month) {
        $excludedZappy += $zTotal;
        $excludedReasons['start_outside_month'] = ($excludedReasons['start_outside_month'] ?? 0) + $zTotal;
        continue;
    }
    $sale = Sale::query()->where('calendar_event_id', $eventId)->where('status', Sale::STATUS_PAGO)->first();
    $saleTotal = $sale ? (float) $sale->total : 0;
    $crmCurrent += $saleTotal;
    $crmAfterRepair += $zTotal;
    if (abs($zTotal - $saleTotal) > 0.02) {
        $adjustments++;
        $adjustmentSum += ($zTotal - $saleTotal);
    }
}

$unmappedZappy = $zappyCsvTotal - array_sum($eventZappyTotal);

echo "Zappy CSV Maio Pagou: {$zappyCsvTotal} €\n";
echo "Mapeado a eventos via fingerprint: ".round(array_sum($eventZappyTotal), 2)." €\n";
echo "Sem fingerprint/ref: ".round($unmappedZappy, 2)." €\n";
echo "CRM actual (vendas desses eventos): ".round($crmCurrent, 2)." €\n";
echo "CRM após repair (totais Zappy por evento): ".round($crmAfterRepair, 2)." €\n";
echo "Ajustes necessários: {$adjustments} eventos | Δ ".round($adjustmentSum, 2)." €\n";
echo "Zappy em eventos fora do critério CRM Maio: ".round($excludedZappy, 2)." €\n";
foreach ($excludedReasons as $reason => $amt) {
    echo "  {$reason}: ".round($amt, 2)." €\n";
}
