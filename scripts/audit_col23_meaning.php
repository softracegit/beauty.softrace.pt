<?php

$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);
$header = str_getcsv($csv[0], ';');

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Sandy Hurtado' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    echo "Sandy May row:\n";
    for ($idx = 19; $idx <= 27; $idx++) {
        echo "  [{$idx}] {$header[$idx]} = ".($c[$idx] ?? '')."\n";
    }
    break;
}

for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    echo "\nLaissa May row:\n";
    for ($idx = 19; $idx <= 27; $idx++) {
        echo "  [{$idx}] {$header[$idx]} = ".($c[$idx] ?? '')."\n";
    }
    break;
}

// Check: is col23 = round(col24 * 1.23, 2) per line?
$match = 0;
$mismatch = 0;
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $c23 = pe($c[23]);
    $c24 = pe($c[24]);
    if (abs($c23 - round($c24 * 1.23, 2)) < 0.02) {
        $match++;
    } else {
        $mismatch++;
        if ($mismatch <= 3) {
            echo "\nMismatch: c23={$c23} c24={$c24} c24*1.23=".round($c24*1.23,2)." item=".($c[20]??'')."\n";
        }
    }
}
echo "\nLaissa May: col23 vs col24*1.23 match={$match} mismatch={$mismatch}\n";

// Is col23 = item_total * pct / 100?
$match2 = 0;
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $item = pe($c[20]);
    $pct = pe($c[22]);
    $c23 = pe($c[23]);
    if ($item > 1 && abs($c23 - round($item * $pct / 100, 2)) < 0.02) {
        $match2++;
    }
}
echo "Laissa: col23 = item*pct% when item>1: {$match2} lines\n";

// Maybe item is shifted - col21 is item?
$match3 = 0;
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $item = pe($c[19] ?? '0'); // comission_after_discounts? 
    $pct = pe($c[22]);
    $c23 = pe($c[23]);
    if (abs($c23 - round($item * $pct / 100, 2)) < 0.02) {
        $match3++;
    }
}
echo "Laissa: col23 = col19*pct%: {$match3} lines\n";

// col19 for Laissa sample
$c = str_getcsv($csv[array_search(true, array_map(fn($l) => strpos($l,'Laissa Osto')!==false && strpos($l,'/05/2026')!==false, $csv))] ?? '', ';');
// skip
