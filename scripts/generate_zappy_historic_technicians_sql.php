<?php

/**
 * Gera SQL para técnicas Zappy históricas (sem user no CRM).
 * Uso: php scripts/generate_zappy_historic_technicians_sql.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$storeId = 1;
$organizationId = (int) (DB::table('stores')->where('id', $storeId)->value('organization_id') ?? 1);

$currentTeam = [
    'Laissa Osto',
    'Sandy Hurtado',
    'Vanessa Pereira',
    'Andrea Velasquez',
];
$ignored = config('zappy_import.ignored_agent_names', []);

$f = fopen(__DIR__.'/../SmartAdmin-pro/assets/files/marcacoes.csv', 'r');
fgetcsv($f, 0, ';');
$providers = [];
while (($row = fgetcsv($f, 0, ';')) !== false) {
    $prov = trim($row[4] ?? '');
    if ($prov !== '') {
        $providers[$prov] = true;
    }
}
fclose($f);

$names = array_keys($providers);
sort($names, SORT_NATURAL | SORT_FLAG_CASE);

$toCreate = array_values(array_filter($names, function (string $name) use ($currentTeam, $ignored): bool {
    return ! in_array($name, $currentTeam, true) && ! in_array($name, $ignored, true);
}));

$passwordHash = Hash::make(Str::random(64));
$now = now()->format('Y-m-d H:i:s');

$lines = [];
$lines[] = '-- Técnicas históricas Zappy (sem acesso ao CRM)';
$lines[] = '-- Gerado em '.now()->toDateTimeString();
$lines[] = '-- Loja store_id='.$storeId.', organization_id='.$organizationId;
$lines[] = '-- Password: hash aleatório (login impossível). Emails: *@historico.zappy';
$lines[] = '-- Agentes: inativos, sem marcação online nem coluna na agenda ativa.';
$lines[] = '';
$lines[] = 'START TRANSACTION;';
$lines[] = '';

$agendaOrder = (int) (DB::table('agents')->where('store_id', $storeId)->max('agenda_order') ?? 0);

foreach ($toCreate as $name) {
    $slug = Str::slug(Str::ascii($name), '.');
    if ($slug === '') {
        $slug = 'tecnica.'.uniqid();
    }
    $email = $slug.'@historico.zappy';
    $escapedName = str_replace("'", "''", $name);
    $escapedEmail = str_replace("'", "''", $email);
    $agendaOrder++;

    $lines[] = "-- {$name}";
    $lines[] = "INSERT INTO `users` (`organization_id`, `name`, `email`, `role`, `client_id`, `must_set_password`, `email_verified_at`, `password`, `remember_token`, `agenda_use_offcanvas_marcacao_test`, `created_at`, `updated_at`)";
    $lines[] = "SELECT {$organizationId}, '{$escapedName}', '{$escapedEmail}', 'prestador', NULL, 0, NULL, '".$passwordHash."', NULL, 0, '{$now}', '{$now}'";
    $lines[] = "FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = '{$escapedEmail}');";
    $lines[] = '';
    $lines[] = "INSERT INTO `agents` (`store_id`, `user_id`, `name`, `status`, `visible_in_agenda`, `visible_in_booking`, `agenda_order`, `created_at`, `updated_at`)";
    $lines[] = "SELECT {$storeId}, u.id, '{$escapedName}', 'inactive', 0, 0, {$agendaOrder}, '{$now}', '{$now}'";
    $lines[] = "FROM `users` u";
    $lines[] = "WHERE u.email = '{$escapedEmail}'";
    $lines[] = "AND NOT EXISTS (SELECT 1 FROM `agents` a WHERE a.store_id = {$storeId} AND a.user_id = u.id);";
    $lines[] = '';
    $lines[] = "INSERT INTO `store_user` (`user_id`, `store_id`, `created_at`, `updated_at`)";
    $lines[] = "SELECT u.id, {$storeId}, '{$now}', '{$now}'";
    $lines[] = "FROM `users` u";
    $lines[] = "WHERE u.email = '{$escapedEmail}'";
    $lines[] = "ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`);";
    $lines[] = '';

}

$lines[] = 'COMMIT;';
$lines[] = '';
$lines[] = '-- Garantir inativo se o SQL for reimportado';
$lines[] = "UPDATE `agents` a";
$lines[] = "INNER JOIN `users` u ON u.id = a.user_id";
$lines[] = "SET a.status = 'inactive', a.visible_in_agenda = 0, a.visible_in_booking = 0, a.updated_at = '{$now}'";
$lines[] = "WHERE u.email LIKE '%@historico.zappy';";
$lines[] = '';
$lines[] = '-- Depois de importar, obter IDs para config/zappy_import.php:';
$lines[] = "-- SELECT u.id, u.name, u.email FROM users u WHERE u.email LIKE '%@historico.zappy' ORDER BY u.id;";

$mapPath = storage_path('app/exports/zappy_historic_technicians_map.txt');
$mapLines = ["# Colar em config/zappy_import.php → agent_user_map (após importar SQL e obter user_id)", ""];
foreach ($toCreate as $name) {
    $slug = Str::slug(Str::ascii($name), '.');
    $email = $slug.'@historico.zappy';
    $mapLines[] = "'{$name}' => null,  // {$email}";
}
file_put_contents($mapPath, implode(PHP_EOL, $mapLines).PHP_EOL);

$output = storage_path('app/exports/zappy_historic_technicians.sql');
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
}
file_put_contents($output, implode(PHP_EOL, $lines).PHP_EOL);

echo "Técnicas a criar: ".count($toCreate).PHP_EOL;
foreach ($toCreate as $name) {
    $slug = Str::slug(Str::ascii($name), '.');
    echo "  - {$name} <{$slug}@historico.zappy>".PHP_EOL;
}
echo PHP_EOL.'SQL gravado em: '.$output.PHP_EOL;
echo 'Mapa sugerido: '.$mapPath.PHP_EOL;
