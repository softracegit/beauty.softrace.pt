<?php

$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

function parseCommissionFromRow(array $c): array
{
    $col19 = pe($c[19] ?? '0');
    $col20 = pe($c[20] ?? '0');
    $shifted = $col19 >= 3 && $col20 <= 1.5;

    if ($shifted) {
        return [
            'com_iva' => pe($c[23] ?? '0'),
            'sem_iva' => pe($c[24] ?? '0'),
        ];
    }

    return [
        'com_iva' => pe($c[24] ?? '0'),
        'sem_iva' => pe($c[25] ?? '0'),
    ];
}

// Try all formulas for Laissa May
$sums = [];
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $p = parseCommissionFromRow($c);
    $sums['new_com'] = ($sums['new_com'] ?? 0) + $p['com_iva'];
    $sums['new_sem'] = ($sums['new_sem'] ?? 0) + $p['sem_iva'];
    $sums['old_com'] = ($sums['old_com'] ?? 0) + round(pe($c[24]) * 1.23, 2);
    $sums['col23'] = ($sums['col23'] ?? 0) + pe($c[23]);
    $sums['col25'] = ($sums['col25'] ?? 0) + pe($c[25]);
}

echo "Laissa May formulas:\n";
foreach ($sums as $k => $v) {
    echo "  {$k}: ".round($v, 2)."\n";
}

// Find lines where shifted detection fails
$fail = 0;
$failCom = 0.0;
for ($i = 1; $i < count($csv); $i++) {
    $c = str_getcsv($csv[$i], ';');
    if (trim($c[7] ?? '') !== 'Laissa Osto' || ! preg_match('#/05/2026#', $c[0])) {
        continue;
    }
    $col19 = pe($c[19] ?? '0');
    $col20 = pe($c[20] ?? '0');
    $shifted = $col19 >= 3 && $col20 <= 1.5;
    if (! $shifted) {
        $fail++;
        $failCom += pe($c[24] ?? '0');
    }
}
echo "Non-shifted rows: {$fail} comm col24 sum: ".round($failCom, 2)."\n";
