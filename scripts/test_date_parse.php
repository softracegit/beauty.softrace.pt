<?php
require __DIR__.'/../vendor/autoload.php';
use Carbon\Carbon;

$tz = 'Europe/Lisbon';
$samples = ['09/05/2026 14:30', '31/05/2026 11:11', '09/01/2026 22:04', '30/05/2026 18:11'];
foreach ($samples as $v) {
    $iso = Carbon::parse($v, $tz)->format('Y-m-d H:i');
    $dmy = null;
    try { $dmy = Carbon::createFromFormat('d/m/Y H:i', $v, $tz)->format('Y-m-d H:i'); } catch (Throwable $e) {}
    echo "{$v}\n  Carbon::parse (import): {$iso}\n  d/m/Y H:i (correto):   {$dmy}\n\n";
}
