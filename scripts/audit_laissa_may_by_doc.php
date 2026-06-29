<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\Store;
use App\Services\ComissoesReportService;

$storeId = (int) (config('zappy_import.default_store_id') ?: 1);
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

function normDoc(string $s): string
{
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s) ?? $s));
}

// Zappy by doc
$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);
$zByDoc = [];
$zTotal = 0.0;
$zLines = 0;
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $zLines++;
    $comm = pe($c[24] ?? '0');
    $commIva = round($comm * (123 / 100), 2);
    $zTotal += $commIva;
    $doc = normDoc($c[2] ?? '');
    $zByDoc[$doc] = ($zByDoc[$doc] ?? 0) + $commIva;
}

// CRM
$filters = ['desde' => '2026-05-01', 'ate' => '2026-05-31', 'tecnico' => 2, 'estado' => null];
$svc = app(ComissoesReportService::class);
$lines = $svc->linesCollection($svc->salesForReport($filters), null, 2);

$cByDoc = [];
$cTotal = 0.0;
foreach ($lines as $l) {
    $cTotal += (float) $l->comissao_com_iva;
    $doc = normDoc((string) ($l->numero_fatura ?? ''));
    $cByDoc[$doc] = ($cByDoc[$doc] ?? 0) + (float) $l->comissao_com_iva;
}

$agent = Agent::query()->where('user_id', 2)->first();
echo 'Laissa agent commission: '.$agent?->formatCommissionDisplay().PHP_EOL;
echo "Zappy lines: {$zLines} total com_iva: ".round($zTotal, 2).PHP_EOL;
echo 'CRM lines: '.$lines->count().' total com_iva: '.round($cTotal, 2).PHP_EOL;
echo 'Diff CRM-Zappy: '.round($cTotal - $zTotal, 2).PHP_EOL;

$allDocs = array_unique(array_merge(array_keys($zByDoc), array_keys($cByDoc)));
sort($allDocs);
$diffs = [];
foreach ($allDocs as $doc) {
    $z = round($zByDoc[$doc] ?? 0, 2);
    $c = round($cByDoc[$doc] ?? 0, 2);
    if (abs($z - $c) > 0.02) {
        $diffs[] = ['doc' => $doc, 'z' => $z, 'c' => $c, 'd' => round($c - $z, 2)];
    }
}
usort($diffs, fn ($a, $b) => abs($b['d']) <=> abs($a['d']));

echo "\nDocs with commission diff (top 15):\n";
foreach (array_slice($diffs, 0, 15) as $d) {
    echo "  {$d['doc']}: Z={$d['z']} C={$d['c']} diff={$d['d']}\n";
}

echo "\nCRM-only docs: ".count(array_diff_key($cByDoc, $zByDoc))."\n";
echo "Zappy-only docs: ".count(array_diff_key($zByDoc, $cByDoc))."\n";

$crmOnly = 0.0;
foreach ($cByDoc as $doc => $v) {
    if (! isset($zByDoc[$doc])) {
        $crmOnly += $v;
    }
}
$zappyOnly = 0.0;
foreach ($zByDoc as $doc => $v) {
    if (! isset($cByDoc[$doc])) {
        $zappyOnly += $v;
    }
}
echo 'CRM-only comm sum: '.round($crmOnly, 2).PHP_EOL;
echo 'Zappy-only comm sum: '.round($zappyOnly, 2).PHP_EOL;
