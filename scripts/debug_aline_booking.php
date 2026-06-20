<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Payment;

$client = Client::query()->where('store_id', 1)->where('name', 'like', '%Aline Nicomedes%')->first();
if ($client === null) {
    echo "Cliente não encontrado\n";
    exit(0);
}

echo "=== Cliente #{$client->id} {$client->name} ===\n\n";

$bookings = Booking::query()->where('client_id', $client->id)->get();
echo 'Bookings: '.$bookings->count()."\n\n";

foreach ($bookings as $b) {
    echo "--- Booking #{$b->id} ---\n";
    echo "public_id: {$b->public_id}\n";
    echo "store_id: {$b->store_id}\n";
    echo "calendar_event_id: ".($b->calendar_event_id ?? 'NULL')."\n";
    echo "payment_status: {$b->payment_status}\n";
    echo "total_price: {$b->total_price} paid: {$b->paid_amount} remaining: {$b->remaining_amount}\n";
    echo "stripe_pi: ".($b->stripe_payment_intent_id ?? 'null')."\n";
    echo "created_at: {$b->created_at} updated_at: {$b->updated_at}\n";
    echo "authenticated_booking_user_id: ".($b->authenticated_booking_user_id ?? 'null')."\n";

    $payload = $b->request_payload;
    if (is_array($payload) && $payload !== []) {
        echo "request_payload:\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    } else {
        echo "request_payload: (vazio)\n";
    }

    $payments = Payment::query()->where('booking_id', $b->id)->get();
    echo 'payments: '.$payments->count()."\n";
    foreach ($payments as $p) {
        echo "  Payment #{$p->id} amount={$p->amount} status=".($p->status ?? '?')."\n";
    }

    if ($b->calendar_event_id) {
        $ev = CalendarEvent::query()->find($b->calendar_event_id);
        if ($ev) {
            echo "Evento ligado: #{$ev->id} {$ev->start_at} {$ev->title} status={$ev->status}\n";
        }
    }
    echo "\n";
}

$events = CalendarEvent::query()
    ->where('client_id', $client->id)
    ->orderBy('start_at')
    ->get(['id', 'title', 'start_at', 'status', 'event_type', 'description']);

echo "=== Marcações na agenda (client_id={$client->id}): {$events->count()} ===\n";
foreach ($events as $e) {
    $desc = $e->description ? substr($e->description, 0, 60) : '';
    echo "#{$e->id} {$e->start_at} [{$e->status}] {$e->title} {$desc}\n";
}
