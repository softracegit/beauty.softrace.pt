<?php

$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);
$header = str_getcsv($csv[0], ';');

foreach ($header as $i => $name) {
    echo "{$i}: {$name}\n";
}

function pe(string $v): float
{
    $v = trim(str_replace(['€', ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }

    return (float) str_replace(',', '.', $v);
}

$sums = [];
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    foreach ([20, 21, 22, 23, 24, 25, 26, 36, 37] as $idx) {
        $sums[$idx] = ($sums[$idx] ?? 0) + pe($c[$idx] ?? '0');
    }
}

echo "\nLaissa May 2026 column sums:\n";
foreach ($sums as $idx => $sum) {
    echo "  [{$idx}] {$header[$idx]}: ".round($sum, 2)."\n";
}

// Sample row
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    echo "\nSample row:\n";
    foreach ([20, 22, 23, 24, 25, 26] as $idx) {
        echo "  [{$idx}] {$header[$idx]} = ".($c[$idx] ?? '')."\n";
    }
    break;
}
