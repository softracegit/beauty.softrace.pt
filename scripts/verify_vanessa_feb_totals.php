<?php

$lines = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

$sums = ['item_total' => 0, 'performer_com' => 0, 'performer_sem' => 0, 'lines' => 0];

for ($i = 1; $i < count($lines); $i++) {
    $c = str_getcsv($lines[$i], ';');
    if (trim($c[7] ?? '') !== 'Vanessa Pereira') {
        continue;
    }
    if (! preg_match('#/02/2026#', $c[0])) {
        continue;
    }
    $sums['item_total'] += pe($c[20]);
    $sums['performer_com'] += pe($c[24]);
    $sums['performer_sem'] += pe($c[25]);
    $sums['lines']++;
}

print_r($sums);

// Mar 2026
$sums2 = ['item_total' => 0, 'performer_com' => 0, 'performer_sem' => 0, 'lines' => 0];
for ($i = 1; $i < count($lines); $i++) {
    $c = str_getcsv($lines[$i], ';');
    if (trim($c[7] ?? '') !== 'Vanessa Pereira') {
        continue;
    }
    if (! preg_match('#/03/2026#', $c[0])) {
        continue;
    }
    $sums2['item_total'] += pe($c[20]);
    $sums2['performer_com'] += pe($c[24]);
    $sums2['performer_sem'] += pe($c[25]);
    $sums2['lines']++;
}
echo "Mar:\n";
print_r($sums2);
