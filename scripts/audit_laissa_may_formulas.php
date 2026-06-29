<?php

$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

$sumA = 0.0; // sum(performer)*1.23 rounded at end
$sumB = 0.0; // sum(round(performer*1.23,2)) per line
$sumC = 0.0; // sum(round(item_total*pct/100,2)) per line - CRM-like on gross
$sumD = 0.0; // sum(round(item_total*pct/100*1.23,2))

for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $item = pe($c[20] ?? '0');
    $pct = pe($c[22] ?? '0');
    $perf = pe($c[24] ?? '0');
    $sumA += $perf;
    $sumB += round($perf * (123 / 100), 2);
    $sumC += round($item * $pct / 100, 2);
    $sumD += round($item * $pct / 100 * (123 / 100), 2);
}

echo "Laissa May Zappy formulas:\n";
echo 'A sum(perf)*1.23: '.round($sumA * 1.23, 2)."\n";
echo "B sum(round(perf*1.23)): ".round($sumB, 2)."\n";
echo "C sum(round(item*pct%)): ".round($sumC, 2)."\n";
echo "D sum(round(item*pct%*1.23)): ".round($sumD, 2)."\n";

// FR 01P2026/461 lines
echo "\nFR 01P2026/461 in Zappy:\n";
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto') {
        continue;
    }
    if (stripos($c[2] ?? '', '01P2026/461') === false) {
        continue;
    }
    echo "  {$c[0]} | item=".pe($c[20])." pct=".pe($c[22])." perf=".pe($c[24])." | {$c[8]}\n";
}
