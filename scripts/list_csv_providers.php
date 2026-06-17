<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$f = fopen(__DIR__.'/../SmartAdmin-pro/assets/files/marcacoes.csv', 'r');
$header = fgetcsv($f, 0, ';');
$providers = [];
while (($row = fgetcsv($f, 0, ';')) !== false) {
    if (count($row) < 5) {
        continue;
    }
    $date = $row[0] ?? '';
    $prov = trim($row[4] ?? '');
    if ($prov === '') {
        continue;
    }
    if (! isset($providers[$prov])) {
        $providers[$prov] = ['count' => 0, 'first2024' => '', 'last2024' => ''];
    }
    $providers[$prov]['count']++;
    if (str_contains($date, '2024')) {
        if ($providers[$prov]['first2024'] === '' || $date < $providers[$prov]['first2024']) {
            $providers[$prov]['first2024'] = $date;
        }
        if ($providers[$prov]['last2024'] === '' || $date > $providers[$prov]['last2024']) {
            $providers[$prov]['last2024'] = $date;
        }
    }
}
fclose($f);

$mapped = array_keys(config('zappy_import.agent_user_map', []));
$ignored = config('zappy_import.ignored_agent_names', []);

uksort($providers, fn (string $a, string $b): int => $providers[$b]['count'] <=> $providers[$a]['count']);

foreach ($providers as $name => $info) {
    $status = in_array($name, $ignored, true)
        ? 'IGNORADO'
        : (in_array($name, $mapped, true) ? 'MAPEADO' : 'SEM MAPA');
    echo $status.' | '.$name.' | total='.$info['count'];
    if ($info['first2024'] !== '') {
        echo ' | 2024: '.$info['first2024'].' - '.$info['last2024'];
    }
    echo PHP_EOL;
}
