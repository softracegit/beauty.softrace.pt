<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\ZappyImportRef;
use App\Services\VendasReportService;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$zappyRef = 9220.0;
$crmRef = 9173.0;
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

function zappyMarcacoesMay(int $year, int $month, string $tz): array
{
    $byPayment = 0.0;
    $byPaymentCount = 0;
    $byAppointment = 0.0;
    $byAppointmentCount = 0;

    foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/marcacoes.csv')) as $row) {
        if (trim($row['status'] ?? '') !== 'Pagou') {
            continue;
        }
        $price = parseDecimal($row['price_final'] ?? $row['price_base'] ?? '0');
        if ($price <= 0) {
            continue;
        }

        $pay = trim($row['payment_date'] ?? '');
        if ($pay !== '') {
            try {
                $payDt = Carbon::createFromFormat('d/m/Y H:i', $pay, $tz);
            } catch (\Throwable) {
                try {
                    $payDt = Carbon::parse($pay, $tz);
                } catch (\Throwable) {
                    $payDt = null;
                }
            }
            if ($payDt && (int) $payDt->year === $year && (int) $payDt->month === $month) {
                $byPayment += $price;
                $byPaymentCount++;
            }
        }

        $appt = trim($row['date'] ?? '');
        if ($appt !== '') {
            try {
                $apptDt = Carbon::parse($appt, $tz);
            } catch (\Throwable) {
                $apptDt = null;
            }
            if ($apptDt && (int) $apptDt->year === $year && (int) $apptDt->month === $month) {
                $byAppointment += $price;
                $byAppointmentCount++;
            }
        }
    }

    return [
        'by_payment' => round($byPayment, 2),
        'by_payment_count' => $byPaymentCount,
        'by_appointment' => round($byAppointment, 2),
        'by_appointment_count' => $byAppointmentCount,
    ];
}

function zappyVendasCsvMay(int $year, int $month, string $tz): array
{
    $byDoc = [];
    foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
        $doc = trim($row['doc_id'] ?? '');
        if ($doc === '') {
            continue;
        }
        $byDoc[$doc][] = $row;
    }

    $gross = 0.0;
    $net = 0.0;
    $count = 0;
    $ignoredAgents = config('zappy_import.ignored_agent_names', []);

    foreach ($byDoc as $doc => $lines) {
        $first = $lines[0];
        $dateRaw = trim($first['date'] ?? '');
        if ($dateRaw === '') {
            continue;
        }
        try {
            $dt = str_contains($dateRaw, '/') && preg_match('#^\d{2}/#', $dateRaw)
                ? Carbon::createFromFormat('d/m/Y H:i', $dateRaw, $tz)
                : Carbon::parse($dateRaw, $tz);
        } catch (\Throwable) {
            continue;
        }
        if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
            continue;
        }

        $isCancelled = trim($first['cancelled_by_doc_id'] ?? '') !== ''
            || str_contains(mb_strtolower($first['status_value'] ?? '', 'UTF-8'), 'cancel');
        $performer = trim($first['performer_name'] ?? '');
        if ($performer !== '' && in_array($performer, $ignoredAgents, true)) {
            continue;
        }
        if ($isCancelled) {
            continue;
        }

        $docGross = 0.0;
        $docDisc = 0.0;
        foreach ($lines as $line) {
            $docGross += parseDecimal($line['item_total_price'] ?? '0');
            $docDisc += parseDecimal($line['item_total_discount'] ?? '0');
        }
        $gross += round($docGross, 2);
        $net += round($docGross - $docDisc, 2);
        $count++;
    }

    return ['gross' => round($gross, 2), 'net' => round($net, 2), 'count' => $count];
}

function crmMetrics(int $year, int $month, int $storeId): array
{
    $start = Carbon::create($year, $month, 1)->startOfDay();
    $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

    $svc = app(VendasReportService::class);
    $reportSales = $svc->reportQuery([
        'desde' => $start->toDateString(),
        'ate' => $end->toDateString(),
    ])->with(['client', 'calendarEvent', 'items', 'items.calendarEventService.event'])->get();
    $lines = $svc->resumoCollection($reportSales, null, null);
    $totais = $svc->totaisRodape($lines);

    $byEmissao = (float) Sale::query()
        ->where('store_id', $storeId)
        ->where('status', '!=', Sale::STATUS_ANULADO)
        ->whereYear('data_emissao', $year)
        ->whereMonth('data_emissao', $month)
        ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO))
        ->sum('total');

    $byStartAt = (float) Sale::query()
        ->where('store_id', $storeId)
        ->where('status', '!=', Sale::STATUS_ANULADO)
        ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereBetween('start_at', [$start, $end]))
        ->sum('total');

    $completoStartAt = (float) Sale::query()
        ->where('store_id', $storeId)
        ->where('status', Sale::STATUS_PAGO)
        ->whereHas('calendarEvent', fn ($q) => $q->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', CalendarEvent::STATUS_COMPLETO)
            ->whereBetween('start_at', [$start, $end]))
        ->sum('total');

    return [
        'report_valor_com_gorjeta' => $totais['total_valor_com_gorjeta'],
        'report_valor' => $totais['total_valor'],
        'report_absoluto' => $totais['total_absoluto'],
        'report_gorjeta' => $totais['total_gorjeta'],
        'report_taxas' => $totais['total_taxas'],
        'sales_count' => $reportSales->count(),
        'sum_total_emissao' => round($byEmissao, 2),
        'sum_total_start_at' => round($byStartAt, 2),
        'pago_completo_start_at' => round($completoStartAt, 2),
    ];
}

echo "=== Análise gap Zappy vs CRM (Maio) ===\n\n";

foreach ([2025, 2026] as $year) {
    echo "--- Maio {$year} ---\n";
    $zMarc = zappyMarcacoesMay($year, 5, $tz);
    $zVendas = zappyVendasCsvMay($year, 5, $tz);
    $crm = crmMetrics($year, 5, $storeId);

    echo "Zappy marcações Pagou (data marcação):  {$zMarc['by_appointment']} € ({$zMarc['by_appointment_count']} linhas)\n";
    echo "Zappy marcações Pagou (payment_date):   {$zMarc['by_payment']} € ({$zMarc['by_payment_count']} linhas)\n";
    echo "Zappy vendas.csv (data fatura, bruto):  {$zVendas['gross']} € ({$zVendas['count']} docs)\n";
    echo "Zappy vendas.csv (líquido c/ desc.):   {$zVendas['net']} €\n";
    echo "CRM relatório total_absoluto:           {$crm['report_absoluto']} €\n";
    echo "CRM relatório (só valor linhas):        {$crm['report_valor']} €\n";
    echo "CRM sum(sales.total) data_emissao:      {$crm['sum_total_emissao']} €\n";
    echo "CRM sum(sales.total) start_at marcação: {$crm['sum_total_start_at']} €\n";
    echo "CRM PAGO+COMPLETO start_at:             {$crm['pago_completo_start_at']} €\n";

    foreach ([
        'Zappy marcação vs CRM emissão' => $zMarc['by_appointment'] - $crm['sum_total_emissao'],
        'Zappy marcação vs CRM relatório' => $zMarc['by_appointment'] - $crm['report_valor_com_gorjeta'],
        'Zappy payment vs CRM emissão' => $zMarc['by_payment'] - $crm['sum_total_emissao'],
        'Zappy vendas.csv vs CRM emissão' => $zVendas['gross'] - $crm['sum_total_emissao'],
        'vs referência Zappy 9220' => $crm['report_valor_com_gorjeta'] - $zappyRef,
        'vs referência CRM 9173' => $crm['report_valor_com_gorjeta'] - $crmRef,
    ] as $label => $diff) {
        echo "  Gap {$label}: ".round($diff, 2)." €\n";
    }
    echo "\n";
}

// Missing imports: Zappy docs in May not in CRM
$year = 2026;
$month = 5;
$zVendas = zappyVendasCsvMay($year, $month, $tz);
$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc !== '') {
        $byDoc[$doc][] = $row;
    }
}

$importedKeys = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('zappy_key')
    ->flip();

$missing = [];
$missingTotal = 0.0;
foreach ($byDoc as $doc => $lines) {
    $first = $lines[0];
    $dateRaw = trim($first['date'] ?? '');
    try {
        $dt = str_contains($dateRaw, '/') && preg_match('#^\d{2}/#', $dateRaw)
            ? Carbon::createFromFormat('d/m/Y H:i', $dateRaw, $tz)
            : Carbon::parse($dateRaw, $tz);
    } catch (\Throwable) {
        continue;
    }
    if ((int) $dt->year !== $year || (int) $dt->month !== $month) {
        continue;
    }
    if (trim($first['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    $gross = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
    }
    $gross = round($gross, 2);
    if (! isset($importedKeys[$doc])) {
        $missing[$doc] = $gross;
        $missingTotal += $gross;
    }
}

echo "--- Maio {$year}: vendas.csv não importadas ao CRM ---\n";
echo 'Total: '.round($missingTotal, 2)." € em ".count($missing)." documentos\n";
foreach (array_slice($missing, 0, 15, true) as $doc => $amt) {
    echo "  {$doc}: {$amt} €\n";
}
if (count($missing) > 15) {
    echo '  ... +'.(count($missing) - 15)." mais\n";
}

// CRM sales in May without Zappy ref
$orphan = Sale::query()
    ->where('store_id', $storeId)
    ->whereYear('data_emissao', $year)
    ->whereMonth('data_emissao', $month)
    ->where('status', '!=', Sale::STATUS_ANULADO)
    ->whereNotIn('id', ZappyImportRef::query()
        ->where('store_id', $storeId)
        ->where('entity_type', ZappyImportRef::TYPE_SALE)
        ->pluck('local_id'))
    ->whereHas('calendarEvent', fn ($q) => $q->where('event_type', CalendarEvent::TYPE_MARCACAO))
    ->get(['id', 'numero_fatura', 'total', 'data_emissao']);

echo "\n--- CRM Maio {$year}: vendas sem ref Zappy ---\n";
echo 'Count: '.$orphan->count().' | Total: '.round((float) $orphan->sum('total'), 2)." €\n";
foreach ($orphan->take(10) as $s) {
    echo "  #{$s->id} {$s->numero_fatura} {$s->data_emissao} => {$s->total} €\n";
}
