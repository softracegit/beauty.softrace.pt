<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ZappyImport\ZappyCsvReader;

$rows = (new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/reports.csv'));
$byStatus = [];
$tempoFeriado = 0;
$tempoOther = 0;
$cancelWithReason = 0;
foreach ($rows as $row) {
    $s = trim($row['status'] ?? '');
    $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
    if ($s === 'Tempo pessoal') {
        $notes = trim($row['notes'] ?? '');
        if (stripos($notes, 'Feriado') === 0 || str_starts_with(mb_strtolower($notes), 'feriado')) {
            $tempoFeriado++;
        } else {
            $tempoOther++;
        }
    }
    if ($s === 'Cancelada' && trim($row['cancel_reason'] ?? '') !== '') {
        $cancelWithReason++;
    }
}
arsort($byStatus);
echo "Total rows: ".count($rows)."\n\nBy status:\n";
foreach ($byStatus as $s => $n) {
    echo "  {$s}: {$n}\n";
}
echo "\nTempo pessoal feriado (skip): {$tempoFeriado}\n";
echo "Tempo pessoal importável: {$tempoOther}\n";
echo "Cancelada com cancel_reason: {$cancelWithReason}\n";
