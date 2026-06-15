<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreDataSqlExporter
{
    private int $storeId;

    /** @var array<string, list<int>> */
    private array $idSets = [];

    public function __construct(int $storeId = 1)
    {
        $this->storeId = $storeId;
    }

    public function export(bool $withoutOrgStore = false): string
    {
        $this->collectIdSets();

        $lines = [];
        $lines[] = '-- Export loja store_id='.$this->storeId.' gerado em '.now()->toDateTimeString();
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        $order = $this->exportOrder();
        if ($withoutOrgStore) {
            $order = array_values(array_filter($order, fn (string $t) => ! in_array($t, ['organizations', 'stores'], true)));
        }

        foreach ($order as $table) {
            $chunk = $table === 'agents'
                ? $this->dumpAgentsTable()
                : $this->dumpTable($table);
            if ($chunk !== '') {
                $lines[] = $chunk;
                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function exportOrder(): array
    {
        return [
            'organizations',
            'stores',
            'categories',
            'extra_categories',
            'services',
            'extras',
            'service_extra',
            'fees',
            'agents',
            'agent_service',
            'personal_time_types',
            'clients',
            'calendar_events',
            'calendar_event_services',
            'calendar_event_service_extras',
            'sales',
            'sale_items',
            'sale_calendar_events',
            'client_wallet_transactions',
            'bookings',
            'crm_settings',
            'zappy_import_refs',
            'cash_register_sessions',
        ];
    }

    private function collectIdSets(): void
    {
        $storeId = $this->storeId;

        $orgId = (int) (DB::table('stores')->where('id', $storeId)->value('organization_id') ?? 0);
        $this->idSets['organizations'] = $orgId > 0 ? [$orgId] : [];
        $this->idSets['stores'] = [$storeId];

        $this->idSets['categories'] = $this->pluckIds('categories', 'store_id', $storeId);
        $this->idSets['extra_categories'] = $this->pluckIds('extra_categories', 'store_id', $storeId);
        $this->idSets['services'] = $this->pluckIds('services', 'store_id', $storeId);
        $this->idSets['fees'] = $this->pluckIds('fees', 'store_id', $storeId);
        $this->idSets['agents'] = $this->pluckIds('agents', 'store_id', $storeId);
        $this->idSets['personal_time_types'] = $this->pluckIds('personal_time_types', 'store_id', $storeId);
        $this->idSets['clients'] = $this->pluckIds('clients', 'store_id', $storeId);
        $this->idSets['calendar_events'] = $this->pluckIds('calendar_events', 'store_id', $storeId);
        $this->idSets['sales'] = $this->pluckIds('sales', 'store_id', $storeId);
        $this->idSets['bookings'] = $this->pluckIds('bookings', 'store_id', $storeId);
        $this->idSets['crm_settings'] = $this->pluckIds('crm_settings', 'store_id', $storeId);
        $this->idSets['zappy_import_refs'] = $this->pluckIds('zappy_import_refs', 'store_id', $storeId);
        $this->idSets['cash_register_sessions'] = $this->pluckIds('cash_register_sessions', 'store_id', $storeId);

        $this->idSets['extras'] = $this->pluckIdsWhereIn(
            'extras',
            'extra_category_id',
            $this->idSets['extra_categories']
        );

        $this->idSets['service_extra'] = DB::table('service_extra')
            ->whereIn('service_id', $this->idSets['services'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['agent_service'] = DB::table('agent_service')
            ->whereIn('agent_id', $this->idSets['agents'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['calendar_event_services'] = DB::table('calendar_event_services')
            ->whereIn('calendar_event_id', $this->idSets['calendar_events'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['calendar_event_service_extras'] = DB::table('calendar_event_service_extras')
            ->whereIn('calendar_event_service_id', $this->idSets['calendar_event_services'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['sale_items'] = DB::table('sale_items')
            ->whereIn('sale_id', $this->idSets['sales'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['sale_calendar_events'] = DB::table('sale_calendar_events')
            ->whereIn('sale_id', $this->idSets['sales'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idSets['client_wallet_transactions'] = DB::table('client_wallet_transactions')
            ->where('store_id', $storeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function pluckIds(string $table, string $column, int $value): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->where($column, $value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private function pluckIdsWhereIn(string $table, string $column, array $values): array
    {
        if ($values === [] || ! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function dumpTable(string $table): string
    {
        if (! Schema::hasTable($table)) {
            return '';
        }

        $ids = $this->idSets[$table] ?? null;
        if ($ids !== null && $ids === []) {
            return '';
        }

        $query = DB::table($table);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return '';
        }

        $columns = Schema::getColumnListing($table);
        $lines = [];
        $lines[] = '-- '.$table.' ('.$rows->count().' linhas)';
        $lines[] = "DELETE FROM `{$table}` WHERE `id` IN (".$rows->pluck('id')->map(fn ($id) => (int) $id)->join(',').');';

        foreach ($rows->chunk(50) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $rowArr = (array) $row;
                $escaped = [];
                foreach ($columns as $col) {
                    $escaped[] = $this->sqlValue($rowArr[$col] ?? null);
                }
                $values[] = '('.implode(', ', $escaped).')';
            }

            $colList = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));
            $lines[] = "INSERT INTO `{$table}` ({$colList}) VALUES\n".implode(",\n", $values).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Liga agentes ao user_id do servidor pelo email (evita IDs diferentes entre ambientes).
     */
    private function dumpAgentsTable(): string
    {
        if (! Schema::hasTable('agents')) {
            return '';
        }

        $ids = $this->idSets['agents'] ?? [];
        if ($ids === []) {
            return '';
        }

        $rows = DB::table('agents')->whereIn('id', $ids)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return '';
        }

        $userEmails = DB::table('users')
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->all())
            ->pluck('email', 'id');

        $columns = Schema::getColumnListing('agents');
        $lines = [];
        $lines[] = '-- agents ('.$rows->count().' linhas; user_id resolvido por email)';
        $lines[] = "DELETE FROM `agents` WHERE `id` IN (".$rows->pluck('id')->map(fn ($id) => (int) $id)->join(',').');';

        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $email = trim((string) ($userEmails[$rowArr['user_id']] ?? ''));
            if ($email === '') {
                continue;
            }

            $selectParts = [];
            foreach ($columns as $col) {
                if ($col === 'user_id') {
                    $selectParts[] = "(SELECT u.id FROM users u WHERE u.email = ".$this->sqlValue($email)." LIMIT 1)";

                    continue;
                }
                $selectParts[] = $this->sqlValue($rowArr[$col] ?? null);
            }

            $colList = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));
            $lines[] = 'INSERT INTO `agents` ('.$colList.') SELECT '.implode(', ', $selectParts).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Corrige calendar_events.user_id quando os IDs de users diferem entre local e servidor.
     */
    private function dumpCalendarUserIdRemapSql(): string
    {
        if (! Schema::hasTable('calendar_events') || ! Schema::hasTable('agents')) {
            return '';
        }

        $agents = DB::table('agents')
            ->where('store_id', $this->storeId)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get();

        if ($agents->isEmpty()) {
            return '';
        }

        $emails = DB::table('users')
            ->whereIn('id', $agents->pluck('user_id')->map(fn ($id) => (int) $id)->all())
            ->pluck('email', 'id');

        $lines = ['-- Remapear colunas da agenda (user_id local → user do servidor por email)'];
        foreach ($agents as $agent) {
            $localUserId = (int) $agent->user_id;
            $email = trim((string) ($emails[$localUserId] ?? ''));
            if ($email === '') {
                continue;
            }

            $lines[] = 'UPDATE calendar_events SET user_id = (SELECT u.id FROM users u WHERE u.email = '
                .$this->sqlValue($email).' LIMIT 1) WHERE store_id = '.$this->storeId.' AND user_id = '.$localUserId.';';
        }

        return implode("\n", $lines);
    }

    public function exportAgentsOnlySql(): string
    {
        $this->collectIdSets();

        $lines = [];
        $lines[] = '-- Agentes da loja store_id='.$this->storeId.' (user_id por email)';
        $lines[] = '-- Gerado em '.now()->toDateTimeString();
        $lines[] = 'SET @store_id := '.$this->storeId.';';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        $agentsSql = $this->dumpAgentsTable();
        if ($agentsSql !== '') {
            $lines[] = $agentsSql;
            $lines[] = '';
        }

        $agentServiceSql = $this->dumpTable('agent_service');
        if ($agentServiceSql !== '') {
            $lines[] = $agentServiceSql;
            $lines[] = '';
        }

        $remapSql = $this->dumpCalendarUserIdRemapSql();
        if ($remapSql !== '') {
            $lines[] = $remapSql;
            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines);
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return "'".$value->format('Y-m-d H:i:s')."'";
        }

        $string = (string) $value;
        $string = str_replace(["\\", "'", "\0", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], $string);

        return "'".$string."'";
    }
}
