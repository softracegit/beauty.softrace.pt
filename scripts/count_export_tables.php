<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$storeId = (int) ($argv[1] ?? 1);

$scoped = [
    'organizations', 'stores', 'categories', 'services', 'extra_categories', 'fees', 'agents',
    'clients', 'client_wallet_transactions', 'calendar_events', 'personal_time_types',
    'sales', 'bookings', 'crm_settings', 'zappy_import_refs', 'cash_register_sessions',
];

$child = [
    'extras' => 'extra_categories',
    'service_extra' => 'services',
    'agent_service' => 'agents',
    'calendar_event_services' => 'calendar_events',
    'calendar_event_service_extras' => 'calendar_event_services',
    'sale_items' => 'sales',
    'sale_calendar_events' => 'sales',
];

foreach ($scoped as $table) {
    if (! Schema::hasTable($table)) {
        echo "$table: MISSING\n";
        continue;
    }
    $q = DB::table($table);
    if (Schema::hasColumn($table, 'store_id')) {
        $q->where('store_id', $storeId);
    } elseif ($table === 'organizations') {
        $orgId = DB::table('stores')->where('id', $storeId)->value('organization_id');
        if ($orgId) {
            $q->where('id', $orgId);
        }
    } elseif ($table === 'stores') {
        $q->where('id', $storeId);
    }
    echo "$table: ".$q->count()."\n";
}

foreach (array_keys($child) as $table) {
    if (! Schema::hasTable($table)) {
        echo "$table: MISSING\n";
        continue;
    }
    echo "$table: ".DB::table($table)->count()."\n";
}
