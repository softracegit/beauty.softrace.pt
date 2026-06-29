<?php

/**
 * Agrega 2026.csv (export Zappy comissões) por técnica e mês.
 * Uso: php scripts/build_zappy_commission_totals.php
 */

$path = __DIR__.'/../SmartAdmin-pro/assets/files/2026.csv';
$lines = file($path, FILE_IGNORE_NEW_LINES);

function parseEuro(string $v): float
{
    $v = trim(str_replace(['€', ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }

    return (float) str_replace(',', '.', $v);
}

function parseMonth(string $s): ?string
{
    if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m)) {
        return sprintf('%04d-%02d', (int) $m[3], (int) $m[2]);
    }

    return null;
}

/** @var array<string, array<string, array{com_iva: float, sem_iva: float}>> */
$byTechMonth = [];

for ($i = 1; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '') {
        continue;
    }
    $c = str_getcsv($lines[$i], ';');
    $ym = parseMonth($c[0] ?? '');
    $tech = trim($c[7] ?? '');
    if ($ym === null || $tech === '') {
        continue;
    }

    $semIva = parseEuro($c[24] ?? '0');
    $comIva = round($semIva * (123 / 100), 2);

    $byTechMonth[$tech][$ym]['sem_iva'] = ($byTechMonth[$tech][$ym]['sem_iva'] ?? 0) + $semIva;
    $byTechMonth[$tech][$ym]['com_iva'] = ($byTechMonth[$tech][$ym]['com_iva'] ?? 0) + $comIva;
}

ksort($byTechMonth);
foreach ($byTechMonth as &$months) {
    ksort($months);
    foreach ($months as &$m) {
        $m['sem_iva'] = round($m['sem_iva'], 2);
        $m['com_iva'] = round($m['com_iva'], 2);
    }
}
unset($months, $m);

foreach (['Vanessa Pereira'] as $t) {
    echo "=== $t ===\n";
    foreach ($byTechMonth[$t] ?? [] as $ym => $v) {
        if (str_starts_with($ym, '2026-0')) {
            echo "  $ym com_iva={$v['com_iva']} sem_iva={$v['sem_iva']}\n";
        }
    }
}

$exportPath = __DIR__.'/../config/zappy_commission_totals.php';
$php = "<?php\n\n/**\n * Totais mensais de comissão Zappy por colaborador (alinhados ao relatório Zappy).\n * Gerado a partir de SmartAdmin-pro/assets/files/2026.csv\n * Não editar manualmente — correr: php scripts/build_zappy_commission_totals.php\n */\n\nreturn ".var_export($byTechMonth, true).";\n";
file_put_contents($exportPath, $php);
echo "\nWritten: $exportPath\n";
