<?php

$csv = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);
$header = str_getcsv($csv[0], ';');

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

function sumsFor(string $tech, string $monthPattern): array
{
    global $csv;
    $sums = [];
    $aligned = 0;
    $shifted = 0;

    for ($i = 1; $i < count($csv); $i++) {
        $c = str_getcsv($csv[$i], ';');
        if (trim($c[7] ?? '') !== $tech || ! preg_match($monthPattern, $c[0])) {
            continue;
        }
        foreach ([23, 24, 25, 36] as $idx) {
            $sums[$idx] = ($sums[$idx] ?? 0) + pe($c[$idx] ?? '0');
        }
        // Heuristic: if col23 <= 100 and col24 > 0, "aligned" Sandy-style; else shifted Laissa-style
        $c23 = pe($c[23] ?? '0');
        $c24 = pe($c[24] ?? '0');
        if ($c23 <= 100 && $c24 > 0 && $c24 < $c23 * 2) {
            $aligned++;
        } else {
            $shifted++;
        }
    }
    $sums['_aligned'] = $aligned;
    $sums['_shifted'] = $shifted;

    return $sums;
}

foreach ([
    ['Laissa Osto', '#/05/2026#'],
    ['Sandy Hurtado', '#/05/2026#'],
    ['Vanessa Pereira', '#/02/2026#'],
] as [$tech, $pat]) {
    $s = sumsFor($tech, $pat);
    echo "=== {$tech} ===\n";
    echo "  col23 performer_comission_p_no_tax: ".round($s[23], 2)."\n";
    echo "  col24 performer_comission: ".round($s[24], 2)."\n";
    echo "  col25 performer_comission_no_tax: ".round($s[25], 2)."\n";
    echo "  col36 subtotal_comission: ".round($s[36], 2)."\n";
    echo "  col24*1.23: ".round($s[24] * 1.23, 2)."\n";
    echo "  aligned rows: {$s['_aligned']} shifted rows: {$s['_shifted']}\n\n";
}
