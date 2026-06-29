<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function parseEuro(mixed $v): float
{
    if ($v === null || $v === '') {
        return 0.0;
    }
    if (is_numeric($v)) {
        return (float) $v;
    }
    $s = trim((string) $v);
    $s = str_replace(['€', ' '], '', $s);
    $s = str_replace(',', '.', $s);

    return (float) $s;
}

function norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) {
        $s = $t;
    }
    $s = preg_replace('/[^a-z0-9 ]/', '', $s) ?? $s;

    return $s;
}

function parseDateKey(mixed $date, bool $crmUsFormat = false): string
{
    if ($date instanceof \DateTimeInterface) {
        return $date->format('Y-m-d');
    }
    $s = trim((string) $date);
    // Zappy: 28/02/2026 19:18 (d/m/Y)
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m)) {
        $a = (int) $m[1];
        $b = (int) $m[2];
        $y = (int) $m[3];
        if ($crmUsFormat || $a <= 12 && $b <= 12) {
            // Excel export: m/d/Y when first part <= 12
            if ($crmUsFormat || ($a <= 12 && $b > 12)) {
                return sprintf('%04d-%02d-%02d', $y, $a, $b);
            }
        }

        return sprintf('%04d-%02d-%02d', $y, $b, $a);
    }

    return norm($s);
}

function matchKey(array $r, bool $crm = false): string
{
    return parseDateKey($r['date'], $crm).'|'.norm($r['client']).'|'.norm($r['service']).'|'.number_format($r['valor'], 2, '.', '');
}

// --- Zappy ---
$lines = file(__DIR__.'/../SmartAdmin-pro/assets/files/reports_comission.csv', FILE_IGNORE_NEW_LINES);
$zappy = [];
for ($i = 1; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '') {
        continue;
    }
    $c = str_getcsv($lines[$i], ';');
    if (count($c) < 8) {
        continue;
    }
    $valor = parseEuro($c[4]);
    $pct = parseEuro($c[5]);
    $comm = parseEuro($c[7]);
    $zappy[] = [
        'date' => $c[0],
        'date_key' => parseDateKey($c[0], false),
        'client' => $c[3],
        'service' => $c[2],
        'valor' => $valor,
        'pct' => $pct,
        'comm' => $comm,
    ];
}

// --- CRM ---
$sheet = IOFactory::load(__DIR__.'/../SmartAdmin-pro/assets/files/Pasta1.xlsx')->getActiveSheet();
$data = $sheet->toArray();
$crm = [];
for ($i = 1; $i < count($data); $i++) {
    $r = $data[$i];
    if (! array_filter($r, fn ($v) => $v !== null && $v !== '')) {
        continue;
    }
    $valor = parseEuro($r[4] ?? 0);
    $pct = parseEuro($r[5] ?? 0);
    $comm = parseEuro($r[6] ?? 0);
    $crm[] = [
        'date' => $r[0],
        'date_key' => parseDateKey($r[0], true),
        'client' => (string) ($r[2] ?? ''),
        'service' => (string) ($r[3] ?? ''),
        'valor' => $valor,
        'pct' => $pct,
        'comm' => $comm,
    ];
}

$zValor = array_sum(array_column($zappy, 'valor'));
$zComm = array_sum(array_column($zappy, 'comm'));
$cValor = array_sum(array_column($crm, 'valor'));
$cComm = array_sum(array_column($crm, 'comm'));

echo "=== TOTAIS ===\n";
echo 'Zappy: '.count($zappy)." linhas | valor={$zValor} | comissão={$zComm} | 60% valor=".round($zValor * 0.6, 2)."\n";
echo 'CRM:   '.count($crm)." linhas | valor={$cValor} | comissão={$cComm}\n";
echo 'Gap valor: '.round($zValor - $cValor, 2)." | Gap comissão: ".round($zComm - $cComm, 2).' | Gap 60% base: '.round(($zValor - $cValor) * 0.6, 2)."\n\n";

// Greedy match (date+client+service+valor)
$crmCopy = $crm;
$onlyZ2 = [];
foreach ($zappy as $z) {
    $k = matchKey($z, false);
    $found = false;
    foreach ($crmCopy as $idx => $c) {
        if (matchKey($c, true) === $k) {
            unset($crmCopy[$idx]);
            $found = true;
            break;
        }
    }
    if (! $found) {
        $onlyZ2[] = $z;
    }
}
$onlyC2 = array_values($crmCopy);

echo "=== SÓ NO ZAPPY (".count($onlyZ2).' linhas, valor '.array_sum(array_column($onlyZ2, 'valor')).', comissão '.array_sum(array_column($onlyZ2, 'comm')).") ===\n";
foreach ($onlyZ2 as $r) {
    echo "{$r['date']} | {$r['client']} | {$r['service']} | valor {$r['valor']} | com {$r['comm']}\n";
}

echo "\n=== SÓ NO CRM (".count($onlyC2).' linhas, valor '.array_sum(array_column($onlyC2, 'valor')).', comissão '.array_sum(array_column($onlyC2, 'comm')).") ===\n";
foreach ($onlyC2 as $r) {
    echo "{$r['date']} | {$r['client']} | {$r['service']} | valor {$r['valor']} | com {$r['comm']}\n";
}

// Fuzzy: same date+client+valor, different service name
echo "\n=== Possível mesmo serviço, nome diferente (date+client+valor) ===\n";
foreach ($onlyZ2 as $z) {
    foreach ($onlyC2 as $c) {
        if ($z['date_key'] === $c['date_key']
            && norm($z['client']) === norm($c['client'])
            && abs($z['valor'] - $c['valor']) < 0.01
            && norm($z['service']) !== norm($c['service'])) {
            echo "Z: {$z['service']}\nC: {$c['service']}\n  {$z['date']} | {$z['client']} | {$z['valor']}€\n\n";
        }
    }
}

// Same client+service+valor, different date
echo "=== Mesmo cliente/serviço/valor, data diferente ===\n";
foreach ($onlyZ2 as $z) {
    foreach ($onlyC2 as $c) {
        if (norm($z['client']) === norm($c['client'])
            && norm($z['service']) === norm($c['service'])
            && abs($z['valor'] - $c['valor']) < 0.01
            && $z['date_key'] !== $c['date_key']) {
            echo "Z {$z['date_key']} vs C {$c['date_key']} | {$z['client']} | {$z['service']} | {$z['valor']}\n";
        }
    }
}

// Group onlyZ by valor sum
echo "\n=== Resumo só-Zappy por valor ===\n";
$byVal = [];
foreach ($onlyZ2 as $r) {
    $byVal[(string) $r['valor']] = ($byVal[(string) $r['valor']] ?? 0) + 1;
}
ksort($byVal, SORT_NUMERIC);
foreach ($byVal as $v => $n) {
    echo "  {$n}x {$v}€ = ".($n * (float) $v)."€ base, com ".($n * (float) $v * 0.6)."€\n";
}

// Match só por data+cliente+valor (ignora nome do serviço)
function key3(array $r, bool $crm = false): string
{
    return parseDateKey($r['date'], $crm).'|'.norm($r['client']).'|'.number_format($r['valor'], 2, '.', '');
}

$crmCopy3 = $crm;
$onlyZ3 = [];
foreach ($zappy as $z) {
    $k = key3($z, false);
    $found = false;
    foreach ($crmCopy3 as $idx => $c) {
        if (key3($c, true) === $k) {
            unset($crmCopy3[$idx]);
            $found = true;
            break;
        }
    }
    if (! $found) {
        $onlyZ3[] = $z;
    }
}
$onlyC3 = array_values($crmCopy3);

echo "\n=== DIFERENÇA REAL (data+cliente+valor) ===\n";
echo 'Só Zappy: '.count($onlyZ3).' linhas | valor '.array_sum(array_column($onlyZ3, 'valor')).' | com 60% '.round(array_sum(array_column($onlyZ3, 'valor')) * 0.6, 2)."\n";
foreach ($onlyZ3 as $r) {
    echo "  Z | {$r['date']} | {$r['client']} | {$r['service']} | {$r['valor']}€\n";
}
echo 'Só CRM: '.count($onlyC3).' linhas | valor '.array_sum(array_column($onlyC3, 'valor')).' | com 60% '.round(array_sum(array_column($onlyC3, 'valor')) * 0.6, 2)."\n";
foreach ($onlyC3 as $r) {
    echo "  C | {$r['date']} | {$r['client']} | {$r['service']} | {$r['valor']}€\n";
}
echo 'Gap líquido valor: '.round(array_sum(array_column($onlyZ3, 'valor')) - array_sum(array_column($onlyC3, 'valor')), 2)."\n";
echo 'Gap líquido comissão 60%: '.round((array_sum(array_column($onlyZ3, 'valor')) - array_sum(array_column($onlyC3, 'valor'))) * 0.6, 2)."\n";
