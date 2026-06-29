<?php

$lines = file(__DIR__.'/../SmartAdmin-pro/assets/files/2026.csv', FILE_IGNORE_NEW_LINES);

function pe(string $v): float
{
    return (float) str_replace(',', '.', trim(str_replace(['€', ' '], '', $v)));
}

function monthTotals(string $tech, string $monthPattern): array
{
    global $lines;
    $sums = [
        'lines' => 0,
        'item_total' => 0.0,
        'item_total_no_tax' => 0.0,
        'performer_com' => 0.0,
        'performer_sem' => 0.0,
        'pct_item' => 0.0,
        'pct_item_net' => 0.0,
        'performer_x123' => 0.0,
        'performer_x123_rounded' => 0.0,
    ];

    for ($i = 1; $i < count($lines); $i++) {
        $c = str_getcsv($lines[$i], ';');
        if (trim($c[7] ?? '') !== $tech) {
            continue;
        }
        if (! preg_match($monthPattern, $c[0])) {
            continue;
        }

        $item = pe($c[20] ?? '0');
        $pct = pe($c[22] ?? '0');
        $pc = pe($c[24] ?? '0');
        $ps = pe($c[25] ?? '0');
        $itemNet = pe($c[26] ?? '0');

        $sums['lines']++;
        $sums['item_total'] += $item;
        $sums['item_total_no_tax'] += $itemNet;
        $sums['performer_com'] += $pc;
        $sums['performer_sem'] += $ps;
        $sums['pct_item'] += $item * $pct / 100;
        $sums['pct_item_net'] += $itemNet * $pct / 100;
        $sums['performer_x123'] += $pc * (123 / 100);
        $sums['performer_x123_rounded'] += round($pc * (123 / 100), 2);
    }

    foreach ($sums as $k => $v) {
        if ($k !== 'lines') {
            $sums[$k] = round($v, 2);
        }
    }

    return $sums;
}

foreach (['02', '03'] as $m) {
    echo "Vanessa 2026-$m\n";
    print_r(monthTotals('Vanessa Pereira', "#/{$m}/2026#"));
}
