<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Services\ComissoesReportService;

$storeId = (int) (config('zappy_import.default_store_id') ?: 1);
app(\App\Support\CurrentStore::class)->set(Store::query()->findOrFail($storeId));

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

function norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) {
        $s = $t;
    }

    return preg_replace('/[^a-z0-9 ]/', '', $s) ?? $s;
}

function dateKey(string $s): string
{
    if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    return '';
}

// Zappy lines
$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);
$zappy = [];
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $valor = pe($c[20] ?? '0');
    $pct = pe($c[22] ?? '0');
    $comm = pe($c[24] ?? '0');
    $key = dateKey($c[0]).'|'.norm($c[16] ?? '').'|'.norm($c[8] ?? '').'|'.number_format($valor, 2, '.', '');
    $zappy[$key] = [
        'date' => $c[0],
        'client' => $c[16] ?? '',
        'service' => $c[8] ?? '',
        'valor' => $valor,
        'pct' => $pct,
        'comm' => $comm,
        'doc' => $c[2] ?? '',
    ];
}

// CRM lines
$filters = ['desde' => '2026-05-01', 'ate' => '2026-05-31', 'tecnico' => 2, 'estado' => null];
$svc = app(ComissoesReportService::class);
$sales = $svc->salesForReport($filters);
$lines = $svc->linesCollection($sales, null, 2);

$crm = [];
foreach ($lines as $l) {
    $dk = $l->data_emissao?->format('Y-m-d') ?? '';
    $key = $dk.'|'.norm($l->cliente).'|'.norm($l->servico).'|'.number_format((float) $l->valor_com_iva, 2, '.', '');
    $crm[$key] = [
        'date' => $dk,
        'client' => $l->cliente,
        'service' => $l->servico,
        'valor' => (float) $l->valor_com_iva,
        'comm' => (float) $l->comissao_com_iva,
        'fatura' => $l->numero_fatura,
        'taxa' => $l->comissao_taxa,
    ];
}

$onlyZ = array_diff_key($zappy, $crm);
$onlyC = array_diff_key($crm, $zappy);
$both = array_intersect_key($zappy, $crm);

$commDiff = 0.0;
$valorDiff = 0.0;
$commMismatch = [];
foreach ($both as $k => $z) {
    $c = $crm[$k];
    $commDiff += $c['comm'] - round($z['comm'] * 1.23, 2);
    $valorDiff += $c['valor'] - $z['valor'];
    if (abs($c['comm'] - round($z['comm'] * 1.23, 2)) > 0.02 || abs($c['valor'] - $z['valor']) > 0.02) {
        $commMismatch[$k] = ['zappy' => $z, 'crm' => $c];
    }
}

$zappyOnlyComm = array_sum(array_map(fn ($z) => round($z['comm'] * 1.23, 2), $onlyZ));
$crmOnlyComm = array_sum(array_map(fn ($c) => $c['comm'], $onlyC));

echo "Zappy lines: ".count($zappy)." CRM lines: ".$lines->count()."\n";
echo "Only Zappy: ".count($onlyZ)." comm=".round($zappyOnlyComm, 2)."\n";
echo "Only CRM: ".count($onlyC)." comm=".round($crmOnlyComm, 2)."\n";
echo "Matched keys with comm diff: ".count($commMismatch)."\n";
echo "CRM - Zappy est. gap from only lines: ".round($crmOnlyComm - $zappyOnlyComm, 2)."\n";

echo "\n--- Only Zappy (first 10) ---\n";
foreach (array_slice($onlyZ, 0, 10, true) as $k => $z) {
    echo "{$z['date']} | {$z['client']} | {$z['service']} | {$z['valor']}€ | comm=".round($z['comm']*1.23,2)." | {$z['doc']}\n";
}

echo "\n--- Only CRM (first 10) ---\n";
foreach (array_slice($onlyC, 0, 10, true) as $k => $c) {
    echo "{$c['date']} | {$c['client']} | {$c['service']} | {$c['valor']}€ | comm={$c['comm']} | {$c['fatura']}\n";
}

echo "\n--- Comm mismatch matched (first 10) ---\n";
foreach (array_slice($commMismatch, 0, 10, true) as $k => $pair) {
    $z = $pair['zappy'];
    $c = $pair['crm'];
    echo "{$z['date']} | {$z['client']} | {$z['service']} | Z {$z['valor']}/".round($z['comm']*1.23,2)." vs C {$c['valor']}/{$c['comm']}\n";
}
