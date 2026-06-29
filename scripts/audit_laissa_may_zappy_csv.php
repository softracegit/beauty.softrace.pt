<?php

$lines = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

$sumPerformer = 0.0;
$sumComIva = 0.0;
$count = 0;
$byDoc = [];

for ($i = 1; $i < count($lines); $i++) {
    $c = str_getcsv($lines[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto') {
        continue;
    }
    if (! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $count++;
    $sem = pe($c[24] ?? '0');
    $sumPerformer += $sem;
    $sumComIva += round($sem * (123 / 100), 2);
    $doc = $c[2] ?? '';
    $byDoc[$doc] = ($byDoc[$doc] ?? 0) + round($sem * (123 / 100), 2);
}

echo "Zappy CSV Laissa May 2026\n";
echo "Lines: {$count}\n";
echo "Sum performer_comission: ".round($sumPerformer, 2)."\n";
echo "Sum com_iva (x1.23): ".round($sumComIva, 2)."\n";
echo "Config says: ".(require __DIR__.'/../config/zappy_commission_totals.php')[2]['2026-05']['com_iva']."\n";
