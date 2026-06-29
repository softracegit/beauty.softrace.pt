<?php

/**
 * Agrega 2026.csv (export Zappy comissões) por user_id e mês.
 * Uso: php scripts/build_zappy_commission_totals.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$path = __DIR__.'/../SmartAdmin-pro/assets/files/2026.csv';
$lines = file($path, FILE_IGNORE_NEW_LINES);
$agentMap = config('zappy_import.agent_user_map', []);

function parseEuro(string $v): float
{
    $v = trim(str_replace(['€', ' '], '', $v));
    if ($v === '') {
        return 0.0;
    }

    return (float) str_replace(',', '.', $v);
}

function parseMonth(string $s): ?string
{
    if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m)) {
        return sprintf('%04d-%02d', (int) $m[3], (int) $m[2]);
    }

    return null;
}

function normName(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) {
        $s = $t;
    }

    return preg_replace('/[^a-z0-9 ]/', '', $s) ?? $s;
}

function nameVariants(string $s): array
{
    $variants = [trim($s)];
    if (! mb_check_encoding($s, 'UTF-8')) {
        $variants[] = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
    $latin = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    if ($latin !== false) {
        $variants[] = $latin;
    }
    $fromLatin = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
    if ($fromLatin !== false) {
        $variants[] = $fromLatin;
    }

    return array_values(array_unique(array_filter($variants, fn (string $v): bool => $v !== '')));
}

/** @var array<string, int> */
$nameToUserId = [];

foreach (User::query()->get(['id', 'name']) as $user) {
    foreach (nameVariants((string) $user->name) as $variant) {
        $nameToUserId[normName($variant)] = (int) $user->id;
    }
}

foreach ($agentMap as $name => $userId) {
    foreach (nameVariants((string) $name) as $variant) {
        $key = normName($variant);
        $nameToUserId[$key] ??= (int) $userId;
    }
}

function userIdForZappyName(string $tech, array $nameToUserId): ?int
{
    foreach (nameVariants($tech) as $variant) {
        $key = normName($variant);
        if (isset($nameToUserId[$key])) {
            return $nameToUserId[$key];
        }
    }

    return null;
}

/** @var array<int, array<string, array{com_iva: float, sem_iva: float}>> */
$byUserMonth = [];
/** @var array<string, int> */
$unmapped = [];

for ($i = 1; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '') {
        continue;
    }
    $c = str_getcsv($lines[$i], ';');
    $ym = parseMonth($c[0] ?? '');
    $tech = trim($c[7] ?? '');
    if ($ym === null || $tech === '') {
        continue;
    }

    $userId = userIdForZappyName($tech, $nameToUserId);
    if ($userId === null) {
        $unmapped[$tech] = ($unmapped[$tech] ?? 0) + 1;

        continue;
    }

    $semIva = parseEuro($c[24] ?? '0');
    $comIva = round($semIva * (123 / 100), 2);

    $byUserMonth[$userId][$ym]['sem_iva'] = ($byUserMonth[$userId][$ym]['sem_iva'] ?? 0) + $semIva;
    $byUserMonth[$userId][$ym]['com_iva'] = ($byUserMonth[$userId][$ym]['com_iva'] ?? 0) + $comIva;
}

ksort($byUserMonth);
foreach ($byUserMonth as &$months) {
    ksort($months);
    foreach ($months as &$m) {
        $m['sem_iva'] = round($m['sem_iva'], 2);
        $m['com_iva'] = round($m['com_iva'], 2);
    }
}
unset($months, $m);

$vanessaId = 4;
echo "=== user_id {$vanessaId} (Vanessa Pereira) ===\n";
foreach ($byUserMonth[$vanessaId] ?? [] as $ym => $v) {
    if (str_starts_with($ym, '2026-0')) {
        echo "  $ym com_iva={$v['com_iva']} sem_iva={$v['sem_iva']}\n";
    }
}

if ($unmapped !== []) {
    echo "\nUnmapped Zappy names:\n";
    foreach ($unmapped as $name => $count) {
        echo "  {$name}: {$count} lines\n";
    }
}

$userLabels = User::query()
    ->whereIn('id', array_keys($byUserMonth))
    ->pluck('name', 'id');

$exportPath = __DIR__.'/../config/zappy_commission_totals.php';
$php = "<?php\n\n";
$php .= "/**\n";
$php .= " * Totais mensais de comissão Zappy indexados por users.id.\n";
$php .= " * Gerado a partir de SmartAdmin-pro/assets/files/2026.csv\n";
$php .= " * Não editar manualmente — correr: php scripts/build_zappy_commission_totals.php\n";
$php .= " *\n";
foreach ($byUserMonth as $userId => $_) {
    $label = (string) ($userLabels[$userId] ?? '?');
    $php .= " * {$userId} => {$label}\n";
}
$php .= " */\n\nreturn [\n";

foreach ($byUserMonth as $userId => $months) {
    $label = (string) ($userLabels[$userId] ?? '?');
    $php .= "    // user_id {$userId} — {$label}\n";
    $php .= '    '.$userId.' => '.str_replace("\n", "\n    ", trim(var_export($months, true), "\n")).",\n\n";
}

$php .= "];\n";
file_put_contents($exportPath, $php);
echo "\nWritten: $exportPath\n";
echo 'Technicians mapped: '.count($byUserMonth)."\n";
