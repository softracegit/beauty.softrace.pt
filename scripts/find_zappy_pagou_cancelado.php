<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;
use App\Services\ZappyImport\ZappyImportService;
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

$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
    ->pluck('local_id', 'zappy_key');

$service = app(ZappyImportService::class);
$rc = new ReflectionClass($service);
$fpMethod = $rc->getMethod('appointmentFingerprint');
$fpMethod->setAccessible(true);

$examples = [];

foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $rowIndex => $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    try {
        $dt = Carbon::parse(trim($row['date'] ?? ''), $tz);
    } catch (\Throwable) {
        continue;
    }
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
    $eventId = $refs[$fp] ?? null;
    if (! $eventId) {
        continue;
    }

    $ev = CalendarEvent::with(['client', 'user', 'eventServiceItems.service'])->find($eventId);
    if (! $ev || $ev->status !== CalendarEvent::STATUS_CANCELADO) {
        continue;
    }

    $sale = Sale::query()
        ->where('store_id', $storeId)
        ->where('calendar_event_id', $eventId)
        ->where('status', '!=', Sale::STATUS_ANULADO)
        ->first();

    $examples[] = [
        'csv_row' => $rowIndex + 2,
        'client' => $client,
        'date' => $dt->format('d/m/Y H:i'),
        'service' => $item,
        'technician' => $provider,
        'zappy_status' => trim($row['status'] ?? ''),
        'price_final' => $price,
        'payment_date' => trim($row['payment_date'] ?? ''),
        'cancel_reason_csv' => trim($row['cancel_reason'] ?? ''),
        'notes_csv' => trim($row['notes'] ?? ''),
        'event_id' => $eventId,
        'crm_status' => $ev->status,
        'crm_start' => $ev->start_at?->timezone($tz)->format('d/m/Y H:i'),
        'crm_cancel_reason' => $ev->cancellation_reason,
        'crm_description' => $ev->description,
        'sale_numero' => $sale?->numero_fatura,
        'sale_total' => $sale ? (float) $sale->total : null,
        'sale_status' => $sale?->status,
        'fingerprint' => $fp,
    ];
}

usort($examples, fn ($a, $b) => $b['price_final'] <=> $a['price_final']);

echo 'Exemplos Pagou (Zappy) → cancelado (CRM): '.count($examples)." linhas\n";
echo 'Soma price_final: '.round(array_sum(array_column($examples, 'price_final')), 2)." €\n\n";

foreach (array_slice($examples, 0, 5) as $i => $ex) {
    echo '--- Exemplo '.($i + 1)." ---\n";
    echo "CSV linha {$ex['csv_row']}: {$ex['client']} | {$ex['date']} | {$ex['service']}\n";
    echo "  Zappy: status={$ex['zappy_status']} | price_final={$ex['price_final']} € | payment_date={$ex['payment_date']}\n";
    if ($ex['cancel_reason_csv'] !== '') {
        echo "  CSV cancel_reason: {$ex['cancel_reason_csv']}\n";
    }
    if ($ex['notes_csv'] !== '') {
        echo "  CSV notes: {$ex['notes_csv']}\n";
    }
    echo "  CRM evento #{$ex['event_id']}: status={$ex['crm_status']} | start={$ex['crm_start']}\n";
    if ($ex['crm_cancel_reason']) {
        echo "  CRM motivo cancelamento: {$ex['crm_cancel_reason']}\n";
    }
    if ($ex['crm_description']) {
        echo "  CRM descrição: ".mb_substr($ex['crm_description'], 0, 120)."\n";
    }
    if ($ex['sale_numero']) {
        echo "  Venda CRM: {$ex['sale_numero']} | {$ex['sale_total']} € | status={$ex['sale_status']}\n";
    } else {
        echo "  Venda CRM: nenhuma\n";
    }
    echo "\n";
}
