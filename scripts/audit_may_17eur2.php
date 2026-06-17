<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Sale;
use App\Services\ZappyImport\ZappyCsvReader;
use Carbon\Carbon;

$year = 2026; $month = 5;
$tz = config('zappy_import.source_timezone', 'Europe/Lisbon');

function parseDecimal(string $v): float {
    $v = trim(str_replace(["\xc2\xa0", ' '], '', $v));
    if ($v === '') return 0.0;
    if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace('.', '', $v);
    return (float) str_replace(',', '.', $v);
}

$byDoc = [];
foreach ((new ZappyCsvReader)->read(base_path('SmartAdmin-pro/assets/files/vendas.csv')) as $row) {
    $doc = trim($row['doc_id'] ?? '');
    if ($doc) $byDoc[$doc][] = $row;
}

// Parse date_format like "20 May 2026 15:42"
$months = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
$sumIso = 0; $sumFmt = 0; $countIso = 0; $countFmt = 0;
foreach ($byDoc as $lines) {
    $first = $lines[0];
    if (trim($first['cancelled_by_doc_id'] ?? '') !== '') continue;
    $gross = 0;
    foreach ($lines as $l) $gross += parseDecimal($l['item_total_price'] ?? '0');
    
    $iso = trim($first['date'] ?? '');
    if ($iso !== '') {
        $dt = Carbon::parse($iso)->timezone($tz);
        if ((int)$dt->year === $year && (int)$dt->month === $month) {
            $sumIso += $gross; $countIso++;
        }
    }
    
    $fmt = trim($first['date_format'] ?? '');
    if (preg_match('/(\d{1,2})\s+(\w{3})\s+(\d{4})/i', $fmt, $m)) {
        $mon = $months[strtolower($m[2])] ?? 0;
        if ($mon === $month && (int)$m[3] === $year) {
            $sumFmt += $gross; $countFmt++;
        }
    }
}

$db = (float) Sale::where('store_id', 1)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->sum('total');
$dbSynth = (float) Sale::where('store_id', 1)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)->where('status', '!=', 'anulado')->where('numero_fatura', 'like', 'ZAPPY-%')->sum('total');
$dbFr = $db - $dbSynth;

echo "Maio 2026 vendas.csv por date ISO: {$sumIso} € ({$countIso} faturas)\n";
echo "Maio 2026 vendas.csv por date_format: {$sumFmt} € ({$countFmt} faturas)\n";
echo "CRM total: {$db} € (FR={$dbFr}, sintéticas={$dbSynth})\n";
echo "CRM - CSV ISO: ".round($db - $sumIso, 2)." €\n";

// Synthetic list sum small ones?
$synths = Sale::where('store_id', 1)->whereYear('data_emissao', $year)->whereMonth('data_emissao', $month)
    ->where('numero_fatura', 'like', 'ZAPPY-%')->get(['numero_fatura', 'total']);
echo "Sintéticas count: ".$synths->count()."\n";

// CRM if we exclude synthetic = what user might expect from Zappy invoices only
echo "\nHipótese Zappy 9220 ≈ marcações Pagou; CRM 9237 = marcações + 17€ extra\n";
echo "Extra 17€ pode ser vendas sintéticas a mais vs contagem Zappy, ou diferenças pontuais fatura/marcação.\n";

// Find synthetic + FR same client same day total diff contributing ~17
// Sum CRM sales linked to events where marcacoes price sum != sale total for may events
use App\Models\CalendarEvent;
$mayEvents = CalendarEvent::where('store_id', 1)->whereYear('start_at', $year)->whereMonth('start_at', $month)->pluck('id');
$over = 0.0;
foreach ($mayEvents as $eid) {
    $event = CalendarEvent::with('eventServices')->find($eid);
    if (!$event) continue;
    $sub = round((float)$event->eventServices->sum(fn($s)=>(float)($s->pivot->price??0)),2);
    $salesTotal = round((float)Sale::where('calendar_event_id', $eid)->where('status','!=','anulado')->sum('total'),2);
    if ($salesTotal > $sub + 0.02) $over += ($salesTotal - $sub);
}
echo "Soma (vendas - marcação) onde vendas > marcação em eventos Maio: ".round($over,2)." €\n";
