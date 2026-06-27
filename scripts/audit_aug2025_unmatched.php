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

$year = 2025;
$month = 8;
$storeId = 1;
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

function parseCsvDate(string $raw, string $tz): ?Carbon
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    try {
        if (str_contains($raw, '/') && preg_match('#^\d{2}/#', $raw)) {
            return Carbon::createFromFormat('d/m/Y H:i', $raw, $tz);
        }

        return Carbon::parse($raw, $tz);
    } catch (\Throwable) {
        return null;
    }
}

$start = Carbon::create($year, $month, 1)->startOfDay();
$end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

$service = app(ZappyImportService::class);
$fpMethod = (new ReflectionClass($service))->getMethod('appointmentFingerprint');
$fpMethod->setAccessible(true);
$refs = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
    ->pluck('local_id', 'zappy_key');

$events = CalendarEvent::query()
    ->where('store_id', $storeId)
    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
    ->whereDate('start_at', '>=', $start->toDateString())
    ->whereDate('start_at', '<=', $end->toDateString())
    ->with('client')
    ->get();

// Unmatched Zappy Pagou lines - categorize
$categories = [
    'no_ref' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
    'cancelado' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
    'agendado_outro' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
    'completo_match_name' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
    'completo_diff_minute' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
    'merged_other' => ['count' => 0, 'sum' => 0.0, 'examples' => []],
];

foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $i => $row) {
    if (trim($row['status'] ?? '') !== 'Pagou') {
        continue;
    }
    $dt = parseCsvDate($row['date'] ?? '', $tz);
    if (! $dt || (int) $dt->year !== $year || (int) $dt->month !== $month) {
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

    $exactMatch = $events->first(function ($ev) use ($client, $dt, $tz) {
        return mb_strtolower(trim($ev->client?->name ?? '')) === mb_strtolower($client)
            && $ev->start_at?->timezone($tz)->format('Y-m-d H:i') === $dt->format('Y-m-d H:i');
    });

    if ($exactMatch && $exactMatch->status === CalendarEvent::STATUS_COMPLETO) {
        continue; // matched in main audit
    }

    $cat = 'merged_other';
    $detail = '';

    if (! $eventId) {
        $cat = 'no_ref';
        $detail = 'sem fingerprint/ref';
    } else {
        $ev = CalendarEvent::find($eventId);
        if ($ev && $ev->status === CalendarEvent::STATUS_CANCELADO) {
            $cat = 'cancelado';
            $detail = "ev#{$eventId} cancelado";
        } elseif ($ev && $ev->status !== CalendarEvent::STATUS_COMPLETO) {
            $cat = 'agendado_outro';
            $detail = "ev#{$eventId} {$ev->status}";
        } elseif ($exactMatch) {
            $cat = 'completo_match_name';
            $detail = "ev#{$exactMatch->id} completo";
        } else {
            // same client same day?
            $sameDay = $events->filter(function ($ev) use ($client, $dt, $tz) {
                return mb_strtolower(trim($ev->client?->name ?? '')) === mb_strtolower($client)
                    && $ev->start_at?->timezone($tz)->format('Y-m-d') === $dt->format('Y-m-d');
            });
            if ($sameDay->isNotEmpty()) {
                $cat = 'completo_diff_minute';
                $detail = 'mesmo dia, hora diferente: '.($sameDay->first()->start_at?->format('H:i') ?? '?');
            } elseif ($ev) {
                $detail = "ev#{$ev->id} {$ev->status} start=".$ev->start_at?->format('d/m H:i');
            }
        }
    }

    $categories[$cat]['count']++;
    $categories[$cat]['sum'] += $price;
    if (count($categories[$cat]['examples']) < 5) {
        $categories[$cat]['examples'][] = "{$client} | {$dt->format('d/m H:i')} | {$price}€ | {$detail}";
    }
}

echo "=== Linhas Zappy Pagou Ago/2025 sem match exato (cliente+minuto) ===\n\n";
foreach ($categories as $name => $c) {
    if ($c['count'] === 0) {
        continue;
    }
    echo strtoupper($name).": {$c['count']} linhas | ".round($c['sum'], 2)." €\n";
    foreach ($c['examples'] as $ex) {
        echo "  - {$ex}\n";
    }
    echo "\n";
}

// Cross-month CRM sales
$emissaoAug = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', Sale::STATUS_PAGO)
    ->whereDate('data_emissao', '>=', $start->toDateString())
    ->whereDate('data_emissao', '<=', $end->toDateString())
    ->whereHas('calendarEvent', fn ($q) => $q->where('status', '!=', CalendarEvent::STATUS_CANCELADO))
    ->with('calendarEvent')
    ->get();

$inAugOutMonth = 0.0;
$outAugInMonth = 0.0;
foreach ($emissaoAug as $sale) {
    $ev = $sale->calendarEvent;
    if (! $ev || ! $ev->start_at) {
        continue;
    }
    $m = (int) $ev->start_at->month;
    $y = (int) $ev->start_at->year;
    if ($y !== $year || $m !== $month) {
        $inAugOutMonth += (float) $sale->total;
    }
}

$marcacaoAug = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', Sale::STATUS_PAGO)
    ->whereHas('calendarEvent', function ($q) use ($start, $end) {
        $q->where('status', CalendarEvent::STATUS_COMPLETO)
            ->whereDate('start_at', '>=', $start->toDateString())
            ->whereDate('start_at', '<=', $end->toDateString());
    })
    ->with('calendarEvent')
    ->get();

foreach ($marcacaoAug as $sale) {
    $em = $sale->data_emissao;
    if (! $em) {
        continue;
    }
    if ((int) $em->year !== $year || (int) $em->month !== $month) {
        $outAugInMonth += (float) $sale->total;
    }
}

$semMarcacao = Sale::query()
    ->where('store_id', $storeId)
    ->where('status', Sale::STATUS_PAGO)
    ->whereDate('data_emissao', '>=', $start->toDateString())
    ->whereDate('data_emissao', '<=', $end->toDateString())
    ->whereNull('calendar_event_id')
    ->sum('total');

echo "=== Cross-mês CRM Ago/2025 ===\n";
echo "Fatura em Ago, marcação noutro mês: ".round($inAugOutMonth, 2)." €\n";
echo "Marcação em Ago, fatura noutro mês: ".round($outAugInMonth, 2)." €\n";
echo "Vendas caixa sem marcação (emissão Ago): ".round((float) $semMarcacao, 2)." €\n";
