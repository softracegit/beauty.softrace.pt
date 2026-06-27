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

$service = app(ZappyImportService::class);
$fpMethod = (new ReflectionClass($service))->getMethod('appointmentFingerprint');
$fpMethod->setAccessible(true);

$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
    ->pluck('local_id', 'zappy_key');

// --- 1) Cancelada + Pagou no CSV (mesmo slot) ---
echo "=== 1) CSV Maio: slots com Cancelada + Pagou (mesma data/cliente/serviço/técnica) ===\n\n";
$bySlot = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $i => $row) {
    try {
        $dt = Carbon::parse(trim($row['date'] ?? ''), $tz);
    } catch (\Throwable) {
        continue;
    }
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    $key = $dt->format('Y-m-d H:i').'|'.trim($row['client_name'] ?? '').'|'.trim($row['item_name'] ?? '').'|'.trim($row['service_provider'] ?? '');
    $bySlot[$key][] = [
        'line' => $i + 2,
        'status' => trim($row['status'] ?? ''),
        'price' => round(parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0'), 2),
        'payment_date' => trim($row['payment_date'] ?? ''),
    ];
}

foreach ($bySlot as $key => $items) {
    $statuses = array_column($items, 'status');
    if (! in_array('Pagou', $statuses, true) || ! in_array('Cancelada', $statuses, true)) {
        continue;
    }
    [$when, $client, $svc, $tech] = explode('|', $key, 4);
    echo "{$client} | ".Carbon::parse($when, $tz)->format('d/m/Y H:i')." | {$svc} | {$tech}\n";
    foreach ($items as $it) {
        $extra = $it['payment_date'] !== '' ? " | pagamento {$it['payment_date']}" : '';
        echo "  linha {$it['line']}: {$it['status']} | {$it['price']} €{$extra}\n";
    }
    $dt = Carbon::parse($when, $tz);
    $userId = (int) (config('zappy_import.agent_user_map')[$tech] ?? 0);
    $fp = $fpMethod->invoke($service, $dt, $client, $svc, $userId);
    $eventId = $refs[$fp] ?? null;
    $ev = $eventId ? CalendarEvent::find($eventId) : null;
    echo '  CRM: '.($eventId ? "evento #{$eventId} ({$ev?->status})" : 'sem ref')."\n\n";
}

// --- 2) Pagou → cancelado CRM (impacto no total) ---
echo "=== 2) Pagou no Zappy → evento cancelado no CRM (perdem-se no total) ===\n\n";
$cancelados = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $i => $row) {
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
    $ev = CalendarEvent::find($eventId);
    if (! $ev || $ev->status !== CalendarEvent::STATUS_CANCELADO) {
        continue;
    }
    $cancelados[] = compact('i', 'client', 'dt', 'item', 'provider', 'price', 'eventId', 'ev');
}

echo count($cancelados).' casos | '.round(array_sum(array_column($cancelados, 'price')), 2)." €\n\n";
foreach ($cancelados as $c) {
    echo "- {$c['client']} | {$c['dt']->format('d/m/Y H:i')} | {$c['item']} | {$c['price']} €\n";
    echo "  CSV linha ".($c['i'] + 2)." | CRM #{$c['eventId']} cancelado | descrição: ".mb_substr($c['ev']->description ?? '', 0, 40)."\n\n";
}

// --- 3) Total venda CRM ≠ soma Zappy Pagou (mesmo evento) ---
echo "=== 3) Evento completo: total venda CRM ≠ soma linhas Pagou Zappy ===\n\n";
$eventZappy = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
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
    if ($eventId) {
        $eventZappy[$eventId] = ($eventZappy[$eventId] ?? 0) + $price;
    }
}

$mismatches = [];
foreach ($eventZappy as $eventId => $zTotal) {
    $ev = CalendarEvent::find($eventId);
    if (! $ev || $ev->status !== CalendarEvent::STATUS_COMPLETO) {
        continue;
    }
    if ((int) $ev->start_at->year !== $year || (int) $ev->start_at->month !== $month) {
        continue;
    }
    $sale = Sale::query()->where('calendar_event_id', $eventId)->where('status', Sale::STATUS_PAGO)->first();
    $saleTotal = $sale ? round((float) $sale->total, 2) : 0.0;
    $delta = round($zTotal - $saleTotal, 2);
    if (abs($delta) > 0.02) {
        $mismatches[] = [
            'event_id' => $eventId,
            'client' => $ev->client?->name,
            'start' => $ev->start_at->timezone($tz)->format('d/m/Y H:i'),
            'zappy' => $zTotal,
            'sale' => $saleTotal,
            'delta' => $delta,
            'fatura' => $sale?->numero_fatura,
        ];
    }
}

usort($mismatches, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));
echo count($mismatches).' eventos | Δ total '.round(array_sum(array_column($mismatches, 'delta')), 2)." €\n\n";
foreach (array_slice($mismatches, 0, 10) as $m) {
    echo "- {$m['client']} | {$m['start']} | Zappy {$m['zappy']} € vs venda {$m['sale']} € (Δ {$m['delta']} €)\n";
    echo "  CRM #{$m['event_id']} | {$m['fatura']}\n\n";
}
