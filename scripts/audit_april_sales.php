<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Models\ZappyImportRef;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$storeId = 1;
$year = 2025;
$month = 4;
$sourceTz = (string) config('zappy_import.source_timezone', config('booking.business_timezone', 'Europe/Lisbon'));
$storageTz = (string) config('app.timezone', 'UTC');

function parseDecimal(string $value): float
{
    $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
    if ($value === '') {
        return 0.0;
    }
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }

    return (float) str_replace(',', '.', $value);
}

function parseDateTimeDMY(string $value, string $sourceTz, string $storageTz): ?Carbon
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y'] as $format) {
        try {
            $dt = Carbon::createFromFormat($format, $value, $sourceTz);
            if ($format === 'd/m/Y') {
                $dt = $dt->startOfDay();
            }

            return $dt->copy()->timezone($storageTz);
        } catch (\Throwable) {
        }
    }

    return null;
}

function parseDateTimeIso(string $value, string $sourceTz, string $storageTz): ?Carbon
{
    $value = trim(str_replace('"', '', $value));
    if ($value === '') {
        return null;
    }
    $dmy = parseDateTimeDMY($value, $sourceTz, $storageTz);
    if ($dmy !== null) {
        return $dmy;
    }
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd M Y H:i', 'd M Y'] as $format) {
        try {
            $dt = Carbon::createFromFormat($format, $value, $sourceTz);

            return $dt->copy()->timezone($storageTz);
        } catch (\Throwable) {
        }
    }
    try {
        $local = Carbon::parse($value, $sourceTz);

        return $local->copy()->timezone($storageTz);
    } catch (\Throwable) {
        return null;
    }
}

function inMonth(?Carbon $dt, int $year, int $month, string $sourceTz): bool
{
    if ($dt === null) {
        return false;
    }
    $local = $dt->copy()->timezone($sourceTz);

    return (int) $local->year === $year && (int) $local->month === $month;
}

$reader = new ZappyCsvReader();
$byDoc = [];
foreach ($reader->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc === '') {
        continue;
    }
    $byDoc[$doc][] = $row;
}

$ignoredAgents = config('zappy_import.ignored_agent_names', []);
$csvDocs = [];
$csvGross = 0.0;
$csvNet = 0.0;
$csvCancelled = 0.0;
$ignoredAgent = 0.0;
$csvParseFailures = [];

foreach ($byDoc as $doc => $lines) {
    $first = $lines[0];
    $dateRaw = trim($first['date'] ?? '');
    $dt = parseDateTimeIso($dateRaw, $sourceTz, $storageTz);
    if ($dt === null) {
        if ($dateRaw !== '') {
            $csvParseFailures[$doc] = $dateRaw;
        }
        continue;
    }
    if (! inMonth($dt, $year, $month, $sourceTz)) {
        continue;
    }

    $gross = 0.0;
    $disc = 0.0;
    foreach ($lines as $line) {
        $gross += parseDecimal($line['item_total_price'] ?? '0');
        $disc += parseDecimal($line['item_total_discount'] ?? '0');
    }
    $gross = round($gross, 2);
    $disc = round($disc, 2);
    $net = round($gross - $disc, 2);

    $isCancelled = trim($first['cancelled_by_doc_id'] ?? '') !== ''
        || str_contains(mb_strtolower($first['status_value'] ?? '', 'UTF-8'), 'cancel');
    $performer = trim($first['performer_name'] ?? '');
    $isIgnored = $performer !== '' && in_array($performer, $ignoredAgents, true);

    if ($isIgnored) {
        $ignoredAgent += $gross;
        continue;
    }

    if ($isCancelled) {
        $csvCancelled += $gross;
    } else {
        $csvGross += $gross;
        $csvNet += $net;
    }

    $csvDocs[$doc] = [
        'gross' => $gross,
        'net' => $net,
        'disc' => $disc,
        'date' => $dt->copy()->timezone($sourceTz)->format('Y-m-d H:i'),
        'cancelled' => $isCancelled,
    ];
}

$dbSales = Sale::query()
    ->where('store_id', $storeId)
    ->whereYear('data_emissao', $year)
    ->whereMonth('data_emissao', $month)
    ->orderBy('numero_fatura')
    ->get();

$zappyDocIds = array_flip(array_keys($byDoc));
$importedSaleKeys = ZappyImportRef::query()
    ->where('store_id', $storeId)
    ->where('entity_type', ZappyImportRef::TYPE_SALE)
    ->pluck('zappy_key')
    ->flip()
    ->all();

$dbTotalAll = 0.0;
$dbTotalNonAnulado = 0.0;
$dbByStatus = [];
$crmOnlyInvoices = [];
$syntheticInvoices = [];

foreach ($dbSales as $sale) {
    $total = round((float) $sale->total, 2);
    $dbTotalAll += $total;
    $status = $sale->status ?? 'unknown';
    $dbByStatus[$status] = round(($dbByStatus[$status] ?? 0) + $total, 2);
    if ($sale->status !== Sale::STATUS_ANULADO) {
        $dbTotalNonAnulado += $total;
    }

    $num = (string) $sale->numero_fatura;
    $baseDoc = str_contains($num, '@') ? explode('@', $num, 2)[0] : $num;
    $inCsv = isset($zappyDocIds[$baseDoc]) || isset($zappyDocIds[$num]);
    $isImported = isset($importedSaleKeys[$num]);
    $isSynthetic = str_starts_with($num, 'ZAPPY-') || (! $isImported && ! isset($zappyDocIds[$num]) && ! str_contains($num, '@'));

    if ($sale->status === Sale::STATUS_ANULADO) {
        continue;
    }

    if (! $inCsv) {
        $crmOnlyInvoices[$num] = [
            'total' => $total,
            'base_doc' => $baseDoc,
            'synthetic' => $isSynthetic,
            'import_ref' => $isImported,
            'data_emissao' => (string) $sale->data_emissao,
        ];
    }
    if ($isSynthetic) {
        $syntheticInvoices[$num] = $total;
    }
}

$csvDocTotalsForCompare = [];
foreach ($csvDocs as $doc => $info) {
    if ($info['cancelled']) {
        continue;
    }
    $csvDocTotalsForCompare[$doc] = $info['gross'];
}

$dbDocAgg = [];
$dbInvoiceByBase = [];
foreach ($dbSales as $sale) {
    if ($sale->status === Sale::STATUS_ANULADO) {
        continue;
    }
    $num = (string) $sale->numero_fatura;
    $baseDoc = str_contains($num, '@') ? explode('@', $num, 2)[0] : $num;
    $dbDocAgg[$baseDoc] = round(($dbDocAgg[$baseDoc] ?? 0) + (float) $sale->total, 2);
    $dbInvoiceByBase[$baseDoc][] = ['num' => $num, 'total' => round((float) $sale->total, 2)];
}

$onlyCsv = [];
$onlyDb = [];
$diffs = [];

foreach ($csvDocTotalsForCompare as $doc => $csvTotal) {
    $dbTotal = $dbDocAgg[$doc] ?? null;
    if ($dbTotal === null) {
        $onlyCsv[$doc] = $csvTotal;
    } elseif (abs($dbTotal - $csvTotal) > 0.02) {
        $diffs[$doc] = ['csv' => $csvTotal, 'db' => $dbTotal, 'diff' => round($dbTotal - $csvTotal, 2), 'invoices' => $dbInvoiceByBase[$doc] ?? []];
    }
}

foreach ($dbDocAgg as $doc => $dbTotal) {
    if (! isset($csvDocTotalsForCompare[$doc])) {
        $onlyDb[$doc] = ['db_total' => $dbTotal, 'invoices' => $dbInvoiceByBase[$doc] ?? []];
    }
}

// Duplicate split sales (@event) in April
$splitByNumero = [];
$splitByEventSuffix = [];
foreach ($dbSales as $sale) {
    if ($sale->status === Sale::STATUS_ANULADO) {
        continue;
    }
    $num = (string) $sale->numero_fatura;
    if (! str_contains($num, '@')) {
        continue;
    }
    $total = round((float) $sale->total, 2);
    $splitByNumero[$num][] = ['id' => $sale->id, 'total' => $total];
    $parts = explode('@', $num, 2);
    $suffix = $parts[1] ?? '';
    $splitByEventSuffix[$suffix][] = ['num' => $num, 'id' => $sale->id, 'total' => $total];
}

$duplicateSplitNumeros = array_filter($splitByNumero, fn ($list) => count($list) > 1);
$duplicateEventSuffixes = array_filter($splitByEventSuffix, fn ($list) => count($list) > 1);

echo "=== Abril {$year} — CSV vs CRM (data_emissao) ===\n\n";
echo "1) TOTAIS EXATOS\n";
echo 'CSV bruto (nao canceladas, excl. tecnicos ignorados): '.number_format($csvGross, 2, '.', '')." EUR\n";
echo 'CSV liquido (bruto - desconto): '.number_format($csvNet, 2, '.', '')." EUR\n";
echo 'CRM total todas (incl. anuladas): '.number_format($dbTotalAll, 2, '.', '')." EUR\n";
echo 'CRM total nao anuladas: '.number_format($dbTotalNonAnulado, 2, '.', '')." EUR\n";
echo 'Diferenca CRM - CSV bruto: '.number_format($dbTotalNonAnulado - $csvGross, 2, '.', '')." EUR\n";
echo 'Por status CRM: '.json_encode($dbByStatus, JSON_UNESCAPED_UNICODE)."\n";
echo 'CSV canceladas: '.number_format($csvCancelled, 2, '.', '')." | ignoradas agente: ".number_format($ignoredAgent, 2, '.', '')."\n";
echo 'Faturas CSV abril: '.count($csvDocTotalsForCompare)." | vendas CRM: ".$dbSales->count()."\n\n";

echo "2) FATURAS SO NO CRM (nao anuladas, base doc sem linha CSV abril)\n";
echo 'Count: '.count($crmOnlyInvoices).' | Soma: '.number_format(array_sum(array_column($crmOnlyInvoices, 'total')), 2, '.', '')." EUR\n";
uasort($crmOnlyInvoices, fn ($a, $b) => $b['total'] <=> $a['total']);
foreach ($crmOnlyInvoices as $num => $info) {
    $tag = $info['synthetic'] ? 'SYNTH' : 'other';
    echo "  {$num}: {$info['total']} EUR [{$tag}] emissao={$info['data_emissao']}\n";
}

echo "\n2b) Sinteticas (ZAPPY- ou sem ref import / sem doc CSV)\n";
echo 'Count: '.count($syntheticInvoices).' | Soma: '.number_format(array_sum($syntheticInvoices), 2, '.', '')." EUR\n";
foreach ($syntheticInvoices as $num => $t) {
    echo "  {$num}: {$t} EUR\n";
}

echo "\n3) FATURAS SO NO CSV (doc abril sem CRM agregado)\n";
echo 'Count: '.count($onlyCsv).' | Soma: '.number_format(array_sum($onlyCsv), 2, '.', '')." EUR\n";
foreach ($onlyCsv as $doc => $t) {
    $d = $csvDocs[$doc]['date'] ?? '';
    echo "  {$doc}: {$t} EUR date={$d}\n";
}

echo "\n4) TOTAIS DIFERENTES (doc base CSV vs soma CRM splits)\n";
echo 'Count: '.count($diffs).' | Soma diffs: '.number_format(array_sum(array_map(fn ($d) => $d['diff'], $diffs)), 2, '.', '')." EUR\n";
uasort($diffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
foreach ($diffs as $doc => $d) {
    $inv = implode(', ', array_map(fn ($i) => $i['num'].'='.$i['total'], $d['invoices']));
    echo "  {$doc}: CSV={$d['csv']} DB={$d['db']} diff={$d['diff']} | {$inv}\n";
}

echo "\n5) DUPLICATE SPLIT SALES (@event) em Abril\n";
echo 'Mesmo numero_fatura duplicado: '.count($duplicateSplitNumeros)."\n";
foreach ($duplicateSplitNumeros as $num => $list) {
    echo "  {$num}: ".json_encode($list)."\n";
}
echo 'Mesmo sufixo @eventId em varias vendas: '.count($duplicateEventSuffixes)."\n";
foreach ($duplicateEventSuffixes as $suffix => $list) {
    if (count($list) < 2) {
        continue;
    }
    $sum = array_sum(array_column($list, 'total'));
    echo "  @{$suffix}: ".count($list)." vendas, soma={$sum} | ".json_encode($list, JSON_UNESCAPED_UNICODE)."\n";
}

// Root cause buckets
$onlyDbSum = array_sum(array_map(fn ($x) => $x['db_total'], $onlyDb));
$onlyCsvSum = array_sum($onlyCsv);
$diffSum = array_sum(array_map(fn ($d) => $d['diff'], $diffs));
$synthSum = array_sum($syntheticInvoices);
$crmOnlySum = array_sum(array_column($crmOnlyInvoices, 'total'));

echo "\n6) ROOT CAUSE SUMMARY\n";
echo "User reported: Zappy=7498 CRM=7609 diff=111 EUR\n";
echo "This run: CSV bruto={$csvGross} CRM nao anuladas={$dbTotalNonAnulado} diff=".round($dbTotalNonAnulado - $csvGross, 2)." EUR\n";
echo "Buckets: CRM-only invoices sum={$crmOnlySum} | CSV-only docs sum={$onlyCsvSum} | per-doc mismatch net diff sum={$diffSum}\n";
echo "Synthetic invoices in April total={$synthSum}\n";
if (count($csvParseFailures)) {
    echo 'CSV date parse failures: '.count($csvParseFailures)."\n";
}

// Compare Carbon::parse vs DMY for april doc count
$csvGrossNaive = 0.0;
foreach ($byDoc as $doc => $lines) {
    $first = $lines[0];
    try {
        $dt = Carbon::parse(trim($first['date'] ?? ''))->timezone($sourceTz);
    } catch (\Throwable) {
        continue;
    }
    if ((int) $dt->month !== $month || (int) $dt->year !== $year) {
        continue;
    }
    if (trim($first['cancelled_by_doc_id'] ?? '') !== '') {
        continue;
    }
    foreach ($lines as $line) {
        $csvGrossNaive += parseDecimal($line['item_total_price'] ?? '0');
    }
}
echo 'CSV bruto com Carbon::parse (naive): '.number_format($csvGrossNaive, 2, '.', '')." EUR (DMY correct={$csvGross})\n";
