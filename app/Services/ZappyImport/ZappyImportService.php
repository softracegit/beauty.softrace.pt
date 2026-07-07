<?php

namespace App\Services\ZappyImport;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Store;
use App\Models\ZappyImportRef;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ZappyImportService
{
    private const PT_MONTHS = [
        'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
        'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
        'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12',
        'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'marco' => '03',
        'abril' => '04', 'maio' => '05', 'junho' => '06', 'julho' => '07',
        'agosto' => '08', 'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',
        'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04', 'may' => '05', 'jun' => '06',
        'jul' => '07', 'aug' => '08', 'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
        'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04', 'may' => '05',
        'june' => '06', 'july' => '07', 'august' => '08', 'september' => '09', 'october' => '10',
        'november' => '11', 'december' => '12',
    ];

    private int $storeId;

    private bool $dryRun;

    private bool $fresh;

    /** @var array<string, int> */
    private array $stats = [];

    /** @var array<string, int> */
    private array $serviceIdsByName = [];

    /** 0 = não configurado / não encontrado; >0 = id do serviço fallback */
    private int $defaultServiceIdResolved = 0;

    /** @var array<string, list<int>> */
    private array $clientIdsByName = [];

    /** @var array<string, list<int>> */
    private array $clientIdsByMatchKey = [];

    /** @var array<int, int> */
    private array $serviceDurations = [];

    /** @var array<string, int> */
    private array $categoryIdsByName = [];

    /** @var array<string, int> */
    private array $appointmentIndex = [];

    /** @var array<string, true> */
    private array $usedPhones = [];

    /** @var array<string, true> */
    private array $usedEmails = [];

    /** @var list<string> */
    private array $ignoredAgents = [];

    /** @var array<string, int> */
    private array $agentUserMap = [];

    /**
     * Último evento da cadeia «visita» por cliente+técnica+dia (para fundir serviços seguidos).
     *
     * @var array<string, array{event_id: int, start_at: Carbon, end_at: Carbon, status_label: string, client_name: string, payment_key: string}>
     */
    private array $mergeChainHeads = [];

    /** @var array<string, Carbon> */
    private array $clientCreatedOnByName = [];

    public function __construct(
        private readonly ZappyCsvReader $reader = new ZappyCsvReader,
    ) {}

    /**
     * @param  list<string>  $steps
     * @return array<string, int>
     */
    public function run(
        int $storeId,
        bool $dryRun,
        bool $fresh,
        array $steps,
        bool $repairTimes = false,
        bool $repairInvoiceAlerts = false,
        bool $repairMergeConsecutive = false,
        bool $repairRelinkSales = false,
        bool $repairOrphanPaid = false,
        bool $repairSaleDiscounts = false,
        bool $repairMissingServices = false,
        bool $repairDistributeSales = false,
        bool $repairClientDates = false,
        bool $repairSaleDates = false,
    ): array {
        $this->storeId = $storeId;
        $this->dryRun = $dryRun;
        $this->fresh = $fresh;
        $this->resetRuntimeState();

        if ($repairTimes || $repairInvoiceAlerts || $repairMergeConsecutive || $repairRelinkSales || $repairOrphanPaid || $repairSaleDiscounts || $repairMissingServices || $repairDistributeSales || $repairClientDates || $repairSaleDates) {
            $repairStats = [];
            if ($repairTimes) {
                $repairStats = array_merge($repairStats, $this->repairSplitOverMergedAppointments());
                $repairStats = array_merge($repairStats, $this->repairAppointmentTimes());
                $repairStats = array_merge($repairStats, $this->repairMergedAppointmentWindows());
                $repairStats = array_merge($repairStats, $this->repairRelinkSalesOffCancelledEvents());
                $repairStats = array_merge($repairStats, $this->repairDistributeSalesToEvents());
            }
            if ($repairInvoiceAlerts) {
                $repairStats = array_merge($repairStats, $this->repairImportedSalesScope());
            }
            if ($repairMergeConsecutive) {
                $repairStats = array_merge($repairStats, $this->repairMergeConsecutiveAppointments());
            }
            if ($repairRelinkSales) {
                $repairStats = array_merge($repairStats, $this->repairRelinkSalesOffCancelledEvents());
                $repairStats = array_merge($repairStats, $this->repairDistributeSalesToEvents());
                $repairStats = array_merge($repairStats, $this->repairSalesEventLinks());
                $repairStats = array_merge($repairStats, $this->repairConsolidateDuplicateEventSales());
            }
            if ($repairDistributeSales) {
                $repairStats = array_merge($repairStats, $this->repairRelinkSalesOffCancelledEvents());
                $repairStats = array_merge($repairStats, $this->repairDistributeSalesToEvents());
                $repairStats = array_merge($repairStats, $this->repairConsolidateDuplicateEventSales());
            }
            if ($repairOrphanPaid) {
                $repairStats = array_merge($repairStats, $this->repairOrphanPaidAppointments());
            }
            if ($repairSaleDiscounts) {
                $repairStats = array_merge($repairStats, $this->repairImportedSaleDiscounts());
            }
            if ($repairSaleDates) {
                $repairStats = array_merge($repairStats, $this->repairImportedSaleDates());
            }
            if ($repairMissingServices) {
                $repairStats = array_merge($repairStats, $this->repairMissingAppointmentServices());
            }
            if ($repairClientDates) {
                $repairStats = array_merge($repairStats, $this->repairImportedClientCreatedAt());
            }

            return $repairStats;
        }

        if (! Store::query()->whereKey($storeId)->exists()) {
            throw new \InvalidArgumentException("Loja #{$storeId} não existe.");
        }

        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);

        if ($fresh && ! $dryRun) {
            ZappyImportRef::query()->where('store_id', $storeId)->delete();
        }

        $this->loadCategoryMap();
        $this->loadExistingServiceRefs();
        $this->loadExistingClientRefs();
        $this->loadExistingAppointmentIndex();
        $this->loadClientCreatedOnIndex();

        $order = ['services', 'clients', 'appointments', 'sales'];
        foreach ($order as $step) {
            if (! in_array($step, $steps, true)) {
                continue;
            }
            match ($step) {
                'services' => $this->importServices(),
                'clients' => $this->importClients(),
                'appointments' => $this->importAppointments(),
                'sales' => $this->importSales(),
                default => null,
            };
        }

        if (! $dryRun && (in_array('appointments', $steps, true) || in_array('sales', $steps, true))) {
            $placeholderStats = $this->repairPlaceholderClientCreatedAt();
            foreach ($placeholderStats as $key => $value) {
                $this->stats[$key] = ($this->stats[$key] ?? 0) + $value;
            }
        }

        return $this->stats;
    }

    private function resetRuntimeState(): void
    {
        $this->stats = [
            'services_created' => 0,
            'services_skipped' => 0,
            'clients_created' => 0,
            'clients_skipped' => 0,
            'clients_phone_cleared' => 0,
            'placeholder_clients_dates_repaired' => 0,
            'placeholder_clients_dates_unchanged' => 0,
            'appointments_created' => 0,
            'appointments_skipped' => 0,
            'appointments_ignored_agent' => 0,
            'appointments_no_client' => 0,
            'appointments_no_service' => 0,
            'appointments_default_service' => 0,
            'appointments_merged' => 0,
            'appointments_client_from_notes' => 0,
            'appointments_ignored_holiday' => 0,
            'sales_created' => 0,
            'sales_skipped' => 0,
            'sales_ignored_agent' => 0,
            'sales_lines_skipped' => 0,
            'sales_linked_event' => 0,
        ];
        $this->serviceIdsByName = [];
        $this->clientIdsByName = [];
        $this->serviceDurations = [];
        $this->categoryIdsByName = [];
        $this->appointmentIndex = [];
        $this->mergeChainHeads = [];
        $this->usedPhones = [];
        $this->usedEmails = [];
        $this->clientCreatedOnByName = [];
        $this->defaultServiceIdResolved = 0;
    }

    private function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    private function csvPath(string $key): string
    {
        $files = config('zappy_import.files', []);
        $filename = $files[$key] ?? throw new \InvalidArgumentException("Ficheiro CSV desconhecido: {$key}");

        return rtrim((string) config('zappy_import.csv_directory'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$filename;
    }

    private function loadCategoryMap(): void
    {
        $this->categoryIdsByName = Category::query()
            ->where('store_id', $this->storeId)
            ->pluck('id', 'name')
            ->all();
    }

    private function resolveCategoryName(string $nameFromCsv): string
    {
        if ($nameFromCsv === '') {
            return '';
        }

        if (isset($this->categoryIdsByName[$nameFromCsv])) {
            return $nameFromCsv;
        }

        $aliases = config('zappy_import.category_aliases', []);
        if (isset($aliases[$nameFromCsv])) {
            return $aliases[$nameFromCsv];
        }

        $normalizedCsv = $this->normalizeKey($nameFromCsv);
        foreach (array_keys($this->categoryIdsByName) as $dbName) {
            if ($this->normalizeKey($dbName) === $normalizedCsv) {
                return $dbName;
            }
        }

        return $nameFromCsv;
    }

    private function loadExistingServiceRefs(): void
    {
        $refs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SERVICE)
            ->get();

        foreach ($refs as $ref) {
            $this->serviceIdsByName[$ref->zappy_key] = (int) $ref->local_id;
        }

        Service::query()
            ->where('store_id', $this->storeId)
            ->get(['id', 'name', 'duration'])
            ->each(function (Service $service): void {
                $key = $this->normalizeKey($service->name);
                $this->serviceIdsByName[$key] ??= (int) $service->id;
                $this->serviceDurations[(int) $service->id] = (int) $service->duration;
            });

        $this->defaultServiceIdResolved = 0;
    }

    /**
     * @return array{0: ?int, 1: bool} [service_id, used_default_fallback]
     */
    private function resolveServiceIdForImport(string $itemName): array
    {
        $itemName = trim($itemName);
        if ($itemName === '') {
            return [null, false];
        }

        $knownId = $this->serviceIdsByName[$this->normalizeKey($itemName)] ?? null;
        if ($knownId !== null && $knownId > 0) {
            return [(int) $knownId, false];
        }

        $defaultId = $this->defaultServiceId();
        if ($defaultId !== null) {
            return [$defaultId, true];
        }

        return [null, false];
    }

    private function defaultServiceId(): ?int
    {
        if ($this->defaultServiceIdResolved !== 0) {
            return $this->defaultServiceIdResolved > 0 ? $this->defaultServiceIdResolved : null;
        }

        $name = trim((string) config('zappy_import.default_service_name', ''));
        if ($name === '') {
            $this->defaultServiceIdResolved = -1;

            return null;
        }

        $key = $this->normalizeKey($name);
        $id = $this->serviceIdsByName[$key] ?? null;
        if ($id === null || $id <= 0) {
            $service = Service::query()
                ->where('store_id', $this->storeId)
                ->get(['id', 'name'])
                ->first(fn (Service $s): bool => $this->normalizeKey($s->name) === $key);
            $id = $service !== null ? (int) $service->id : null;
        }

        $this->defaultServiceIdResolved = $id !== null && $id > 0 ? (int) $id : -1;

        return $this->defaultServiceIdResolved > 0 ? $this->defaultServiceIdResolved : null;
    }

    private function appendZappyOriginalServiceNote(string $description, string $zappyItemName): string
    {
        $note = 'Serviço Zappy (não catalogado): '.$zappyItemName;
        if (str_contains($description, $note)) {
            return $description;
        }

        return rtrim($description)."\n".$note;
    }

    private function loadExistingClientRefs(): void
    {
        $refs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
            ->get();

        foreach ($refs as $ref) {
            $this->pushClientNameIndex($ref->zappy_key, (int) $ref->local_id);
        }

        Client::query()
            ->where('store_id', $this->storeId)
            ->get(['id', 'name', 'phone', 'email'])
            ->each(function (Client $client): void {
                $this->pushClientNameIndex($this->normalizeName($client->name), (int) $client->id);
                $this->pushClientMatchKeyIndex($this->clientMatchKey($client->name), (int) $client->id);
                if ($client->phone) {
                    $this->usedPhones[$this->normalizePhoneKey($client->phone)] = true;
                }
                if ($client->email) {
                    $this->usedEmails[mb_strtolower(trim($client->email), 'UTF-8')] = true;
                }
            });
    }

    private function loadExistingAppointmentIndex(): void
    {
        $refs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
            ->get();

        foreach ($refs as $ref) {
            $this->appointmentIndex[$ref->zappy_key] = (int) $ref->local_id;
        }

        $zappyRefs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT_ZAPPY)
            ->get();

        foreach ($zappyRefs as $ref) {
            $meta = $ref->meta ?? [];
            if (! empty($meta['fingerprint'])) {
                $this->appointmentIndex[$meta['fingerprint']] = (int) $ref->local_id;
            }
        }
    }

    private function importServices(): void
    {
        $rows = $this->reader->read($this->csvPath('services'));

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $key = $this->normalizeKey($name);
            if (isset($this->serviceIdsByName[$key])) {
                $this->bump('services_skipped');

                continue;
            }

            if ($this->refExists(ZappyImportRef::TYPE_SERVICE, $key)) {
                $id = (int) $this->getRefLocalId(ZappyImportRef::TYPE_SERVICE, $key);
                $this->serviceIdsByName[$key] = $id;
                $this->ensureServiceDuration($id);
                $this->bump('services_skipped');

                continue;
            }

            $categoryName = $this->resolveCategoryName(trim($row['category'] ?? ''));
            $categoryId = $this->categoryIdsByName[$categoryName] ?? null;
            if ($categoryId === null) {
                throw new \RuntimeException("Categoria não encontrada na loja: «{$categoryName}» (serviço «{$name}»).");
            }

            $duration = (int) ($row['duration'] ?? 30);
            $price = $this->parseDecimal($row['price'] ?? '0');
            $online = trim($row['price_online'] ?? '') !== ''
                ? $this->parseDecimal($row['price_online'])
                : $price;

            if ($this->dryRun) {
                $this->serviceIdsByName[$key] = -1;
                $this->serviceDurations[-1] = $duration;
                $this->bump('services_created');

                continue;
            }

            $service = Service::withoutEvents(fn () => Service::create([
                'store_id' => $this->storeId,
                'category_id' => $categoryId,
                'name' => $name,
                'duration' => $duration,
                'price' => round($price, 2),
                'online_price' => round($online, 2),
                'sort_order' => 0,
            ]));

            $this->serviceIdsByName[$key] = (int) $service->id;
            $this->serviceDurations[(int) $service->id] = $duration;
            $this->saveRef(ZappyImportRef::TYPE_SERVICE, $key, (int) $service->id);
            $this->bump('services_created');
        }
    }

    private function importClients(): void
    {
        $rows = $this->reader->read($this->csvPath('clients'));

        foreach ($rows as $i => $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $phone = trim($row['mobile'] ?? '');
            $email = trim($row['email'] ?? '');
            $refKey = $phone !== ''
                ? 'phone:'.$this->normalizePhoneKey($phone)
                : ($email !== '' ? 'email:'.mb_strtolower($email, 'UTF-8') : 'name:'.$this->normalizeName($name).':'.$i);

            $createdAt = $this->parseClientCreatedOn($row['createdon'] ?? '');

            if ($this->refExists(ZappyImportRef::TYPE_CLIENT, $refKey)) {
                $clientId = (int) $this->getRefLocalId(ZappyImportRef::TYPE_CLIENT, $refKey);
                $this->pushClientNameIndex($this->normalizeName($name), $clientId);
                if ($createdAt !== null && ! $this->dryRun) {
                    $this->applyImportedClientCreatedAt($clientId, $createdAt);
                }
                $this->bump('clients_skipped');

                continue;
            }

            $phoneKey = $phone !== '' ? $this->normalizePhoneKey($phone) : null;
            if ($phoneKey !== null && isset($this->usedPhones[$phoneKey])) {
                $phone = null;
                $this->bump('clients_phone_cleared');
            }

            $emailLower = $email !== '' ? mb_strtolower($email, 'UTF-8') : null;
            if ($emailLower !== null && isset($this->usedEmails[$emailLower])) {
                $email = null;
            }

            $gender = $this->mapGender($row['gender'] ?? '');
            $birthDate = $this->parseBirthDate(
                $row['birth_year'] ?? '',
                $row['birth_month'] ?? '',
                $row['birth_day'] ?? ''
            );
            $createdAt = $this->parseClientCreatedOn($row['createdon'] ?? '') ?? now();
            $nif = trim($row['vat_number'] ?? '') ?: null;

            if ($this->dryRun) {
                $fakeId = 100000 + $i;
                $this->pushClientNameIndex($this->normalizeName($name), $fakeId);
                $this->bump('clients_created');

                continue;
            }

            $client = $this->createClientRecord([
                'store_id' => $this->storeId,
                'name' => $name,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'nif' => $nif,
            ], $createdAt);

            if ($phone) {
                $this->usedPhones[$this->normalizePhoneKey($phone)] = true;
            }
            if ($email) {
                $this->usedEmails[mb_strtolower($email, 'UTF-8')] = true;
            }

            $this->pushClientNameIndex($this->normalizeName($name), (int) $client->id);
            $this->pushClientMatchKeyIndex($this->clientMatchKey($name), (int) $client->id);
            $this->saveRef(ZappyImportRef::TYPE_CLIENT, $refKey, (int) $client->id, [
                'name' => $name,
                'createdon' => $createdAt->toIso8601String(),
            ]);
            $this->bump('clients_created');
        }
    }

    private function loadClientCreatedOnIndex(): void
    {
        $this->clientCreatedOnByName = [];
        foreach ($this->reader->read($this->csvPath('clients')) as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $createdAt = $this->parseClientCreatedOn($row['createdon'] ?? '');
            if ($createdAt === null) {
                continue;
            }
            $key = $this->normalizeName($name);
            $existing = $this->clientCreatedOnByName[$key] ?? null;
            if ($existing === null || $createdAt->lt($existing)) {
                $this->clientCreatedOnByName[$key] = $createdAt;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createClientRecord(array $attributes, Carbon $createdAt): Client
    {
        return Client::withoutEvents(function () use ($attributes, $createdAt): Client {
            $client = new Client($attributes);
            $client->timestamps = false;
            $client->created_at = $createdAt;
            $client->updated_at = $createdAt;
            $client->save();

            return $client->fresh() ?? $client;
        });
    }

    /** Repõe created_at/updated_at com a data createdon do Zappy. Devolve true se alterou. */
    private function applyImportedClientCreatedAt(int $clientId, Carbon $createdAt): bool
    {
        $client = Client::query()->where('store_id', $this->storeId)->find($clientId);
        if ($client === null) {
            return false;
        }

        if ($client->created_at !== null && $client->created_at->equalTo($createdAt)) {
            return false;
        }

        if ($this->dryRun) {
            return true;
        }

        // created_at não está em $fillable — usar query builder para não ser ignorado.
        Client::withoutEvents(function () use ($clientId, $createdAt): void {
            Client::query()
                ->where('id', $clientId)
                ->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
        });

        return true;
    }

    private function resolveImportedClientIdFromCsvRow(array $row, int $rowIndex): ?int
    {
        $name = trim($row['name'] ?? '');
        $phone = trim($row['mobile'] ?? '');
        $email = trim($row['email'] ?? '');

        if ($phone !== '') {
            $clientId = $this->getRefLocalId(ZappyImportRef::TYPE_CLIENT, 'phone:'.$this->normalizePhoneKey($phone));
            if ($clientId !== null && $clientId > 0) {
                return (int) $clientId;
            }
        }

        if ($email !== '') {
            $clientId = $this->getRefLocalId(ZappyImportRef::TYPE_CLIENT, 'email:'.mb_strtolower($email, 'UTF-8'));
            if ($clientId !== null && $clientId > 0) {
                return (int) $clientId;
            }
        }

        if ($name !== '') {
            $clientId = $this->getRefLocalId(
                ZappyImportRef::TYPE_CLIENT,
                'name:'.$this->normalizeName($name).':'.$rowIndex
            );
            if ($clientId !== null && $clientId > 0) {
                return (int) $clientId;
            }

            $resolved = $this->resolveClientIdByName($name);

            return $resolved !== null && $resolved > 0 ? $resolved : null;
        }

        return null;
    }

    /**
     * Repõe created_at/updated_at dos clientes importados a partir de clientes.csv (coluna createdon).
     *
     * @return array<string, int>
     */
    public function repairImportedClientCreatedAt(): array
    {
        $this->loadExistingClientRefs();
        $this->loadClientCreatedOnIndex();

        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($this->reader->read($this->csvPath('clients')) as $i => $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $createdAt = $this->parseClientCreatedOn($row['createdon'] ?? '');
            if ($createdAt === null) {
                $skipped++;

                continue;
            }

            $clientId = $this->resolveImportedClientIdFromCsvRow($row, $i);
            if ($clientId === null) {
                $skipped++;

                continue;
            }

            $client = Client::query()->where('store_id', $this->storeId)->find($clientId);
            if ($client === null) {
                $skipped++;

                continue;
            }

            if ($client->created_at !== null && $client->created_at->equalTo($createdAt)) {
                $unchanged++;

                continue;
            }

            if ($this->dryRun) {
                $updated++;

                continue;
            }

            $this->applyImportedClientCreatedAt($clientId, $createdAt);
            $updated++;
        }

        $placeholderStats = $this->repairPlaceholderClientCreatedAt();

        return array_merge([
            'clients_dates_repaired' => $updated,
            'clients_dates_unchanged' => $unchanged,
            'clients_dates_skipped' => $skipped,
        ], $placeholderStats);
    }

    private function importAppointments(): void
    {
        $rows = $this->reader->read($this->csvPath('appointments'));
        $statusMap = config('zappy_import.appointment_status_map', []);

        foreach ($rows as $rowIndex => $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                $this->bump('appointments_ignored_agent');

                continue;
            }

            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }

            $statusLabel = trim($row['status'] ?? '');
            if ($statusLabel === 'Tempo pessoal' && $this->isZappyHolidayPersonalTime($row)) {
                $this->bump('appointments_ignored_holiday');

                continue;
            }

            [$eventType, $status] = $statusMap[$statusLabel] ?? ['marcacao', CalendarEvent::STATUS_AGENDADO];

            $userId = $provider !== '' ? ($this->agentUserMap[$provider] ?? null) : null;
            $clientName = trim($row['client_name'] ?? '');
            $itemName = trim($row['item_name'] ?? '');
            $notes = trim($row['notes'] ?? '');
            $cancelReason = trim($row['cancel_reason'] ?? '');
            $fingerprintKey = $itemName;
            if ($eventType === CalendarEvent::TYPE_TEMPO_PESSOAL) {
                $fingerprintKey = $notes !== '' ? $notes : 'tempo_pessoal';
            } elseif ($statusLabel === 'Cancelada' && $clientName === '' && $itemName === '') {
                $fingerprintKey = 'cancel:'.$cancelReason.':'.$notes;
            }

            $fingerprint = $this->appointmentFingerprint($startAt, $clientName, $fingerprintKey, (int) ($userId ?? 0));

            if (isset($this->appointmentIndex[$fingerprint]) || $this->refExists(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint)) {
                $this->bump('appointments_skipped');

                continue;
            }

            $clientNameForLink = $clientName;
            if ($clientNameForLink === '' && $eventType === CalendarEvent::TYPE_MARCACAO && $notes !== '') {
                $inferredClient = $this->inferClientNameFromAppointmentNotes($notes);
                if ($inferredClient !== null) {
                    $clientNameForLink = $inferredClient;
                    $this->bump('appointments_client_from_notes');
                }
            }

            $clientId = null;
            if ($eventType === CalendarEvent::TYPE_MARCACAO && $clientNameForLink !== '') {
                $clientId = $this->resolveClientIdByName($clientNameForLink);
                if ($clientId === null) {
                    $clientId = $this->createPlaceholderClient($clientNameForLink, $startAt);
                    $this->bump('appointments_no_client');
                } else {
                    $this->maybeBackdatePlaceholderClient($clientId, $startAt);
                }
            }

            $serviceId = null;
            $usedDefaultService = false;
            $duration = 30;
            if ($eventType === CalendarEvent::TYPE_MARCACAO && $itemName !== '') {
                [$serviceId, $usedDefaultService] = $this->resolveServiceIdForImport($itemName);
                if ($serviceId === null) {
                    $this->bump('appointments_no_service');
                } else {
                    if ($usedDefaultService) {
                        $this->bump('appointments_default_service');
                    }
                    $duration = $this->serviceDurations[$serviceId] ?? 30;
                }
            } elseif ($eventType === CalendarEvent::TYPE_TEMPO_PESSOAL) {
                $duration = 30;
            }

            $endAt = $startAt->copy()->addMinutes($duration);
            $priceBase = $this->parseDecimal($row['price_base'] ?? '0');
            $description = $this->buildAppointmentDescription($notes, '', $rowIndex);
            if ($usedDefaultService) {
                $description = $this->appendZappyOriginalServiceNote($description, $itemName);
            }

            $updatedAt = $this->parseAppointmentDate($row['updated_on'] ?? '') ?? $startAt;

            $chainKey = $this->mergeChainKey($clientName, $userId, $startAt);
            $chainHead = $this->mergeChainHeads[$chainKey] ?? null;
            if (
                $chainHead !== null
                && $eventType === CalendarEvent::TYPE_MARCACAO
                && $this->shouldMergeConsecutive($chainHead, $startAt, $statusLabel, $row)
            ) {
                $this->registerAppointmentFingerprint($fingerprint, $chainHead['event_id']);
                if (! $this->dryRun) {
                    $this->appendServiceToEvent(
                        $chainHead['event_id'],
                        $serviceId,
                        $duration,
                        $priceBase,
                        $endAt,
                        $clientName,
                        $itemName,
                        $updatedAt,
                    );
                    $this->saveRef(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint, $chainHead['event_id'], [
                        'client' => $clientName,
                        'item' => $itemName,
                        'provider' => $provider,
                    ]);
                }
                $mergedEnd = $endAt->gt($chainHead['end_at']) ? $endAt->copy() : $chainHead['end_at']->copy();
                $this->mergeChainHeads[$chainKey] = [
                    'event_id' => $chainHead['event_id'],
                    'start_at' => $chainHead['start_at'],
                    'end_at' => $mergedEnd,
                    'status_label' => $statusLabel,
                    'client_name' => $clientName,
                    'payment_key' => $chainHead['payment_key'] !== '' ? $chainHead['payment_key'] : $this->paymentMergeKey($row),
                ];
                $this->bump('appointments_merged');

                continue;
            }

            $title = $eventType === CalendarEvent::TYPE_TEMPO_PESSOAL
                ? ($notes !== '' ? $notes : 'Tempo pessoal')
                : match (true) {
                    $clientNameForLink !== '' && $itemName !== '' => $clientNameForLink.' — '.$itemName,
                    $clientNameForLink !== '' => $clientNameForLink,
                    $itemName !== '' => $itemName,
                    default => 'Marcação cancelada',
                };

            if ($this->dryRun) {
                $fakeId = -1000 - $rowIndex;
                $this->registerAppointmentFingerprint($fingerprint, $fakeId);
                if ($eventType === CalendarEvent::TYPE_MARCACAO && $clientName !== '') {
                    $this->mergeChainHeads[$chainKey] = [
                        'event_id' => $fakeId,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'status_label' => $statusLabel,
                        'client_name' => $clientName,
                        'payment_key' => $this->paymentMergeKey($row),
                    ];
                }
                $this->bump('appointments_created');

                continue;
            }

            $event = CalendarEvent::withoutEvents(function () use (
                $title, $startAt, $endAt, $description, $userId, $clientId, $serviceId,
                $eventType, $status, $cancelReason, $statusLabel, $updatedAt
            ) {
                return CalendarEvent::create([
                    'store_id' => $this->storeId,
                    'title' => $title,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'description' => $description,
                    'user_id' => $userId,
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'event_type' => $eventType,
                    'status' => $status,
                    'marcacao_source' => $eventType === CalendarEvent::TYPE_MARCACAO
                        ? \App\Support\ActivityLogMarcacaoOrigin::AGENDA
                        : null,
                    'cancellation_reason' => $statusLabel === 'Cancelada'
                        ? ($cancelReason !== '' ? $cancelReason : 'Importado Zappy')
                        : null,
                    'created_at' => $startAt,
                    'updated_at' => $updatedAt,
                ]);
            });

            if ($serviceId !== null) {
                $pivotPrice = $priceBase > 0 ? round($priceBase, 2) : null;
                $event->eventServices()->attach($serviceId, [
                    'duration' => $duration,
                    'price' => $pivotPrice,
                    'original_price' => $pivotPrice,
                    'sort_order' => 0,
                ]);
            }

            $eventId = (int) $event->id;
            $this->registerAppointmentFingerprint($fingerprint, $eventId);
            $this->saveRef(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint, $eventId, [
                'client' => $clientNameForLink !== '' ? $clientNameForLink : $clientName,
                'item' => $itemName,
                'provider' => $provider,
            ]);
            if ($eventType === CalendarEvent::TYPE_MARCACAO && $clientName !== '') {
                $this->mergeChainHeads[$chainKey] = [
                    'event_id' => $eventId,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status_label' => $statusLabel,
                    'client_name' => $clientName,
                    'payment_key' => $this->paymentMergeKey($row),
                ];
            }
            $this->bump('appointments_created');
        }
    }

    private function isZappyHolidayPersonalTime(array $row): bool
    {
        $notes = trim($row['notes'] ?? '');

        return $notes !== '' && (bool) preg_match('/^feriado\b/iu', $notes);
    }

    /**
     * Lista de espera Zappy: notas «espera Nome Cliente» sem client_name no CSV.
     */
    private function inferClientNameFromAppointmentNotes(string $notes): ?string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return null;
        }

        $firstLine = trim(strtok($notes, "\r\n"));
        if ($firstLine === '' || preg_match('/^espera\s+(.+)$/iu', $firstLine, $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1], " \t.,;:-");
        if ($name === '' || mb_strlen($name) < 2) {
            return null;
        }

        if (preg_match('/^\d+$/', $name)) {
            return null;
        }

        return $name;
    }

    private function parseAppointmentDate(string $value): ?Carbon
    {
        $value = trim(str_replace('"', '', $value));
        if ($value === '') {
            return null;
        }

        return $this->parseDateTimeIso($value) ?? $this->parseDateTimeDMY($value);
    }

    private function importSales(): void
    {
        $rows = $this->reader->read($this->csvPath('sales'));
        $grouped = [];

        foreach ($rows as $row) {
            $performer = trim($row['performer_name'] ?? '');
            if ($performer !== '' && $this->isIgnoredAgent($performer)) {
                $this->bump('sales_ignored_agent');

                continue;
            }

            $docId = trim($row['doc_id'] ?? '');
            if ($docId === '') {
                continue;
            }

            $grouped[$docId][] = $row;
        }

        foreach ($grouped as $docId => $lines) {
            if ($lines === []) {
                continue;
            }

            if ($this->refExists(ZappyImportRef::TYPE_SALE, $docId)) {
                $this->bump('sales_skipped');

                continue;
            }

            $first = $lines[0];
            $clientName = trim($first['client_name'] ?? '');
            $saleDateHint = $this->parseDateTimeIso($first['date'] ?? '');
            $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : null;

            if ($clientId === null && $clientName !== '') {
                $clientId = $this->createPlaceholderClient($clientName, $saleDateHint);
            } elseif ($clientId !== null) {
                $this->maybeBackdatePlaceholderClient($clientId, $saleDateHint);
            }

            $dataEmissao = $saleDateHint ?? now();
            $total = 0.0;
            $desconto = 0.0;
            foreach ($lines as $line) {
                $total += $this->parseDecimal($line['item_total_price'] ?? '0');
                $desconto += $this->parseDecimal($line['item_total_discount'] ?? '0');
            }
            $total = round($total, 2);
            $desconto = round($desconto, 2);

            $isCancelled = trim($first['cancelled_by_doc_id'] ?? '') !== ''
                || str_contains(mb_strtolower($first['status_value'] ?? '', 'UTF-8'), 'cancel');

            $calendarEventId = $this->resolveBestCalendarEventForSale($lines, $clientId, $total);

            if ($this->dryRun) {
                if ($calendarEventId !== null) {
                    $this->bump('sales_linked_event');
                }
                $this->bump('sales_created');

                continue;
            }

            $salesScope = (string) config('zappy_import.sales_scope', Sale::SCOPE_CAIXA_LIQUIDACAO);
            if (! in_array($salesScope, [Sale::SCOPE_REGULAR, Sale::SCOPE_CAIXA_LIQUIDACAO, Sale::SCOPE_BOOKING_RESERVA], true)) {
                $salesScope = Sale::SCOPE_CAIXA_LIQUIDACAO;
            }

            $sale = Sale::create([
                'store_id' => $this->storeId,
                'calendar_event_id' => $calendarEventId,
                'client_id' => $clientId,
                'numero_fatura' => Str::limit($docId, 64, ''),
                'data_emissao' => $dataEmissao->toDateString(),
                'total' => $total,
                'desconto' => $desconto > 0 ? $desconto : null,
                'valor_pago' => $isCancelled ? 0 : $total,
                'payment_method' => null,
                'scope' => $salesScope,
                'status' => $isCancelled ? Sale::STATUS_ANULADO : Sale::STATUS_PAGO,
                'issue_without_fiscal_id' => true,
                'created_at' => $dataEmissao,
                'updated_at' => $dataEmissao,
            ]);

            foreach ($lines as $idx => $line) {
                $itemName = trim($line['item_name'] ?? '');
                $qty = max(1, (int) ($line['item_quantity'] ?? 1));
                $subtotal = round($this->parseDecimal($line['item_total_price'] ?? '0'), 2);
                $precoUnit = $qty > 0 ? round($subtotal / $qty, 2) : $subtotal;
                $serviceId = $itemName !== ''
                    ? $this->resolveServiceIdForImport($itemName)[0]
                    : null;

                $cesId = null;
                if ($calendarEventId !== null && $serviceId !== null) {
                    $cesId = DB::table('calendar_event_services')
                        ->where('calendar_event_id', $calendarEventId)
                        ->where('service_id', $serviceId)
                        ->value('id');
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'tipo' => SaleItem::TIPO_SERVICO,
                    'calendar_event_service_id' => $cesId,
                    'service_id' => $serviceId,
                    'descricao' => $itemName !== '' ? $itemName : 'Item',
                    'quantidade' => $qty,
                    'preco_unitario' => $precoUnit,
                    'subtotal' => $subtotal,
                    'sort_order' => $idx,
                ]);

                $zappyApptId = trim($line['appointment_id'] ?? '');
                if ($zappyApptId !== '' && $calendarEventId !== null) {
                    $this->saveRef(
                        ZappyImportRef::TYPE_APPOINTMENT_ZAPPY,
                        $zappyApptId,
                        $calendarEventId,
                        ['doc_id' => $docId]
                    );
                }
            }

            if ($calendarEventId !== null) {
                $this->bump('sales_linked_event');
            }

            $this->saveRef(ZappyImportRef::TYPE_SALE, $docId, (int) $sale->id);
            $this->bump('sales_created');
        }
    }

    /**
     * Estados em que uma marcação pode receber ligação de venda (fatura).
     * Apenas «Pagou» no Zappy (completo no CRM); «Chegou», «Confirmada», etc. ficam sem fatura.
     *
     * @return list<string>
     */
    private function allowedMarcacaoStatusesForSaleLink(): array
    {
        return [
            CalendarEvent::STATUS_COMPLETO,
        ];
    }

    private function isMarcacaoLinkableForSale(CalendarEvent $event): bool
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return false;
        }

        return in_array($event->status ?? '', $this->allowedMarcacaoStatusesForSaleLink(), true);
    }

    /**
     * Linha do CSV marcacoes.csv que pode receber fatura (apenas Pagou / completo).
     *
     * @param  array<string, string>  $row
     */
    private function isMarcacaoRowLinkableForSale(array $row): bool
    {
        $statusLabel = trim($row['status'] ?? '');
        if ($statusLabel === '') {
            return false;
        }

        $statusMap = config('zappy_import.appointment_status_map', []);
        $mapped = $statusMap[$statusLabel] ?? ['marcacao', CalendarEvent::STATUS_AGENDADO];
        $crmStatus = is_array($mapped) ? ($mapped[1] ?? CalendarEvent::STATUS_AGENDADO) : CalendarEvent::STATUS_AGENDADO;

        return in_array($crmStatus, $this->allowedMarcacaoStatusesForSaleLink(), true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CalendarEvent>  $query
     * @return \Illuminate\Database\Eloquent\Builder<CalendarEvent>
     */
    private function applyLinkableMarcacaoScope($query)
    {
        return $query
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereIn('status', $this->allowedMarcacaoStatusesForSaleLink());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $events
     * @return \Illuminate\Support\Collection<int, CalendarEvent>
     */
    private function filterLinkableMarcacaoEvents($events)
    {
        return $events->filter(fn (CalendarEvent $e) => $this->isMarcacaoLinkableForSale($e))->values();
    }

    private function resolveSaleCalendarEventId(array $line, ?int $clientId): ?int
    {
        $zappyApptId = trim($line['appointment_id'] ?? '');
        if ($zappyApptId !== '') {
            $existing = $this->getRefLocalId(ZappyImportRef::TYPE_APPOINTMENT_ZAPPY, $zappyApptId);
            if ($existing !== null) {
                $event = CalendarEvent::query()->find($existing);
                if ($event !== null && $this->isMarcacaoLinkableForSale($event)) {
                    return (int) $existing;
                }
            }
        }

        $performer = trim($line['performer_name'] ?? '');
        $userId = (int) ($this->agentUserMap[$performer] ?? 0);
        $clientName = trim($line['client_name'] ?? '');
        $itemName = trim($line['item_name'] ?? '');
        $note = $line['item_note'] ?? '';

        $apptDate = $this->parseAppointmentDateFromNote($note);
        if ($apptDate === null) {
            $apptDate = $this->parseDateTimeIso($line['date'] ?? '');
        }

        if ($apptDate === null) {
            return null;
        }

        if ($apptDate->format('H:i') === '00:00' && ! str_contains($note, ':')) {
            return $this->matchAppointmentByDay($apptDate, $clientName, $itemName, $userId, $clientId);
        }

        $fingerprint = $this->appointmentFingerprint($apptDate, $clientName, $itemName, $userId);
        if (isset($this->appointmentIndex[$fingerprint])) {
            $localId = $this->appointmentIndex[$fingerprint];
            if ($localId > 0) {
                $event = CalendarEvent::query()->find($localId);
                if ($event !== null && $this->isMarcacaoLinkableForSale($event)) {
                    if ($zappyApptId !== '' && ! $this->dryRun) {
                        $this->saveRef(ZappyImportRef::TYPE_APPOINTMENT_ZAPPY, $zappyApptId, $localId, [
                            'fingerprint' => $fingerprint,
                        ]);
                    }

                    return $localId;
                }
            }
        }

        return $this->matchAppointmentByDay($apptDate, $clientName, $itemName, $userId, $clientId);
    }

    private function matchAppointmentByDay(
        Carbon $day,
        string $clientName,
        string $itemName,
        int $userId,
        ?int $clientId,
    ): ?int {
        if ($this->dryRun) {
            return null;
        }

        $query = $this->applyLinkableMarcacaoScope(
            CalendarEvent::query()->where('store_id', $this->storeId)
        )->whereDate('start_at', $day->toDateString());

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        } elseif ($clientName !== '') {
            $resolved = $this->resolveClientIdByName($clientName);
            if ($resolved !== null) {
                $query->where('client_id', $resolved);
            }
        }

        $events = $this->filterLinkableMarcacaoEvents($query->with('eventServices')->get());
        if ($events->isEmpty()) {
            return null;
        }

        if ($itemName !== '') {
            $serviceId = $this->resolveServiceIdForImport($itemName)[0];
            if ($serviceId === null) {
                return null;
            }

            $filtered = $events->filter(fn (CalendarEvent $e) => $e->eventServices->contains('id', $serviceId));

            return $this->pickBestEventForSaleFromCollection($filtered, $day);
        }

        return null;
    }

    /**
     * Preferir marcação paga/concluída; se várias, a mais próxima do dia indicado (meio-dia local).
     *
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $events
     */
    private function pickBestEventForSaleFromCollection($events, Carbon $day): ?int
    {
        if ($events->isEmpty()) {
            return null;
        }

        $linkable = $events->filter(fn (CalendarEvent $e) => $this->isMarcacaoLinkableForSale($e))->values();
        $pool = $linkable->isNotEmpty() ? $linkable : $events->values();

        if ($pool->count() === 1) {
            return (int) $pool->first()->id;
        }

        $target = $day->copy()->timezone($this->sourceTimezone())->setTime(12, 0);
        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($pool as $event) {
            $diff = abs((int) $event->start_at->diffInMinutes($target, false));
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (int) $event->id;
            }
        }

        return $best;
    }

    private function createPlaceholderClient(string $name, ?Carbon $firstSeenAt = null): ?int
    {
        $norm = $this->normalizeName($name);
        $existing = $this->resolveClientIdByName($name);
        if ($existing !== null) {
            $this->maybeBackdatePlaceholderClient($existing, $firstSeenAt);

            return $existing;
        }

        if ($this->dryRun) {
            $fakeId = 200000 + crc32($norm);
            $this->pushClientNameIndex($norm, $fakeId);

            return $fakeId;
        }

        $createdAt = $this->pickEarliestCarbon($firstSeenAt, $this->clientCreatedOnByName[$norm] ?? null) ?? now();

        $client = $this->createClientRecord([
            'store_id' => $this->storeId,
            'name' => $name,
            'email' => null,
            'phone' => null,
        ], $createdAt);

        $this->pushClientNameIndex($norm, (int) $client->id);
        $this->saveRef(ZappyImportRef::TYPE_CLIENT, 'placeholder:'.$norm, (int) $client->id);

        return (int) $client->id;
    }

    /**
     * Clientes placeholder (sem ficha em clientes.csv): created_at = 1.ª marcação ou 1.ª venda.
     *
     * @return array<string, int>
     */
    public function repairPlaceholderClientCreatedAt(): array
    {
        $placeholderClientIds = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
            ->where('zappy_key', 'like', 'placeholder:%')
            ->pluck('local_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $updated = 0;
        $unchanged = 0;

        foreach ($placeholderClientIds as $clientId) {
            $earliest = $this->resolveEarliestActivityDateForClient($clientId);
            if ($earliest === null) {
                continue;
            }

            $client = Client::query()->where('store_id', $this->storeId)->find($clientId);
            if ($client === null) {
                continue;
            }

            if ($client->created_at !== null && $client->created_at->equalTo($earliest)) {
                $unchanged++;

                continue;
            }

            if ($this->dryRun) {
                $updated++;

                continue;
            }

            $this->applyImportedClientCreatedAt($clientId, $earliest);
            $updated++;
        }

        return [
            'placeholder_clients_dates_repaired' => $updated,
            'placeholder_clients_dates_unchanged' => $unchanged,
        ];
    }

    private function isPlaceholderClientId(int $clientId): bool
    {
        return ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
            ->where('local_id', $clientId)
            ->where('zappy_key', 'like', 'placeholder:%')
            ->exists();
    }

    private function maybeBackdatePlaceholderClient(int $clientId, ?Carbon $candidate): void
    {
        if ($candidate === null || $this->dryRun || ! $this->isPlaceholderClientId($clientId)) {
            return;
        }

        $client = Client::query()->where('store_id', $this->storeId)->find($clientId);
        if ($client === null || $client->created_at === null) {
            return;
        }

        if ($candidate->gte($client->created_at)) {
            return;
        }

        $this->applyImportedClientCreatedAt($clientId, $candidate);
    }

    private function resolveEarliestActivityDateForClient(int $clientId): ?Carbon
    {
        $eventMin = CalendarEvent::query()
            ->where('store_id', $this->storeId)
            ->where('client_id', $clientId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->min('start_at');

        $saleMin = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('client_id', $clientId)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->min('data_emissao');

        $earliest = null;

        if ($eventMin !== null) {
            $earliest = Carbon::parse($eventMin);
        }

        if ($saleMin !== null) {
            $saleDay = Carbon::parse($saleMin)->startOfDay();
            if ($earliest === null) {
                $earliest = $saleDay;
            } elseif ($saleDay->toDateString() < $earliest->toDateString()) {
                $earliest = $saleDay;
            }
        }

        return $earliest;
    }

    /**
     * @param  Carbon|null  ...$candidates
     */
    private function pickEarliestCarbon(?Carbon ...$candidates): ?Carbon
    {
        $earliest = null;

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if ($earliest === null || $candidate->lt($earliest)) {
                $earliest = $candidate;
            }
        }

        return $earliest;
    }

    private function resolveClientIdByName(string $name): ?int
    {
        $norm = $this->normalizeName($name);
        $ids = $this->clientIdsByName[$norm] ?? [];
        if ($ids !== []) {
            return $ids[0];
        }

        $key = $this->clientMatchKey($name);
        $ids = $this->clientIdsByMatchKey[$key] ?? [];

        return $ids[0] ?? null;
    }

    private function clientMatchKey(string $name): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($name), 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;
    }

    private function pushClientNameIndex(string $normName, int $clientId): void
    {
        if (! in_array($clientId, $this->clientIdsByName[$normName] ?? [], true)) {
            $this->clientIdsByName[$normName][] = $clientId;
        }
    }

    private function pushClientMatchKeyIndex(string $matchKey, int $clientId): void
    {
        if (! in_array($clientId, $this->clientIdsByMatchKey[$matchKey] ?? [], true)) {
            $this->clientIdsByMatchKey[$matchKey][] = $clientId;
        }
    }

    private function buildAppointmentDescription(string $notes, string $cancelReason, int $rowIndex): ?string
    {
        $parts = ['[Importado Zappy]'];
        if ($notes !== '') {
            $parts[] = $notes;
        }
        if ($cancelReason !== '') {
            $parts[] = 'Motivo cancelamento: '.$cancelReason;
        }

        $text = implode("\n", $parts);

        return strlen($text) > 20 ? $text : '[Importado Zappy] #'.$rowIndex;
    }

    private function appointmentFingerprint(Carbon $startAt, string $clientName, string $itemName, int $userId): string
    {
        $local = $startAt->copy()->timezone($this->sourceTimezone());

        return sprintf(
            '%s|%s|%s|%d',
            $local->format('Y-m-d H:i'),
            $this->normalizeName($clientName),
            $this->normalizeKey($itemName),
            $userId
        );
    }

    private function parseFingerprintStartAt(string $fingerprint): ?Carbon
    {
        $datePart = explode('|', $fingerprint, 2)[0] ?? '';
        if ($datePart === '') {
            return null;
        }

        try {
            $local = Carbon::createFromFormat('Y-m-d H:i', $datePart, $this->sourceTimezone());

            return $this->toStorageTimezone($local);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAppointmentDateFromNote(string $note): ?Carbon
    {
        if (preg_match('/Data da marca[cç][aã]o:\s*(\d{1,2})\s+([A-Za-zÀ-ÿa-z]+)\s+(\d{4})/iu', $note, $m)) {
            $day = (int) $m[1];
            $monthWord = mb_strtolower($m[2], 'UTF-8');
            $year = (int) $m[3];
            $month = self::PT_MONTHS[$monthWord] ?? self::PT_MONTHS[substr($monthWord, 0, 3)] ?? null;
            if ($month === null) {
                return null;
            }

            return $this->toStorageTimezone(
                Carbon::createFromFormat(
                    'Y-m-d',
                    sprintf('%04d-%s-%02d', $year, $month, $day),
                    $this->sourceTimezone()
                )->startOfDay()
            );
        }

        return null;
    }

    /**
     * Horas Zappy (Europe/Lisbon) → APP_TIMEZONE (UTC) para a BD.
     */
    private function parseDateTimeDMY(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $sourceTz = $this->sourceTimezone();
        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value, $sourceTz);
                if ($format === 'd/m/Y') {
                    $dt = $dt->startOfDay();
                }

                return $this->toStorageTimezone($dt);
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Data de registo do cliente no Zappy (clientes.csv → createdon).
     * Aceita d/m/Y e Y-m-d H:i:s; ignora placeholders inválidos do export.
     */
    private function parseClientCreatedOn(string $value): ?Carbon
    {
        $value = trim(str_replace('"', '', $value));
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return $this->parseDateTimeIso($value);
    }

    private function parseDateTimeIso(string $value): ?Carbon
    {
        $value = trim(str_replace('"', '', $value));
        if ($value === '') {
            return null;
        }

        $dmy = $this->parseDateTimeDMY($value);
        if ($dmy !== null) {
            return $dmy;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd M Y H:i', 'd M Y'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value, $this->sourceTimezone());

                return $this->toStorageTimezone($dt);
            } catch (\Throwable) {
            }
        }

        try {
            $local = Carbon::parse($value, $this->sourceTimezone());

            return $this->toStorageTimezone($local);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sourceTimezone(): string
    {
        return (string) config('zappy_import.source_timezone', config('booking.business_timezone', 'Europe/Lisbon'));
    }

    private function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private function toStorageTimezone(Carbon $local): Carbon
    {
        return $local->copy()->timezone($this->storageTimezone());
    }

    /**
     * Separa eventos que fundiram marcações demasiado distantes no tempo (ex. pagamento único às 12:18).
     *
     * @return array<string, int>
     */
    public function repairSplitOverMergedAppointments(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingServiceRefs();
        $this->loadExistingClientRefs();

        $rowsByFingerprint = [];
        foreach ($this->reader->read($this->csvPath('appointments')) as $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }
            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }
            $clientName = trim($row['client_name'] ?? '');
            $itemName = trim($row['item_name'] ?? '');
            $userId = (int) ($this->agentUserMap[$provider] ?? 0);
            $fp = $this->appointmentFingerprint($startAt, $clientName, $itemName, $userId);
            $rowsByFingerprint[$fp] = $row;
        }

        $refs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
            ->get();

        $byEvent = [];
        foreach ($refs as $ref) {
            $byEvent[(int) $ref->local_id][] = $ref;
        }

        $split = 0;
        $skipped = 0;
        $windows = 0;

        foreach ($byEvent as $eventId => $eventRefs) {
            if (count($eventRefs) <= 1) {
                continue;
            }

            $slots = [];
            foreach ($eventRefs as $ref) {
                $startAt = $this->parseFingerprintStartAt((string) $ref->zappy_key);
                if ($startAt === null) {
                    continue;
                }
                $row = $rowsByFingerprint[(string) $ref->zappy_key] ?? null;
                $duration = 30;
                $itemName = trim((string) ($ref->meta['item'] ?? ''));
                if ($itemName !== '') {
                    [$serviceId] = $this->resolveServiceIdForImport($itemName);
                    if ($serviceId !== null) {
                        $duration = $this->serviceDurations[$serviceId] ?? 30;
                    }
                }
                $slots[] = [
                    'ref' => $ref,
                    'start_at' => $startAt,
                    'end_at' => $startAt->copy()->addMinutes($duration),
                    'row' => $row ?? [],
                    'item_name' => $itemName,
                ];
            }

            if ($slots === []) {
                continue;
            }

            usort($slots, fn (array $a, array $b): int => $a['start_at']->timestamp <=> $b['start_at']->timestamp);

            $clusters = [[$slots[0]]];
            for ($i = 1; $i < count($slots); $i++) {
                $cluster = &$clusters[count($clusters) - 1];
                $head = $cluster[count($cluster) - 1];
                $chainHead = [
                    'start_at' => $cluster[0]['start_at'],
                    'end_at' => $head['end_at'],
                    'status_label' => trim($head['row']['status'] ?? 'Pagou'),
                    'payment_key' => $this->paymentMergeKey($head['row']),
                ];
                if ($this->shouldMergeConsecutive($chainHead, $slots[$i]['start_at'], trim($slots[$i]['row']['status'] ?? 'Pagou'), $slots[$i]['row'])) {
                    $cluster[] = $slots[$i];
                } else {
                    $clusters[] = [$slots[$i]];
                }
            }
            unset($cluster);

            if (count($clusters) <= 1) {
                if (count($slots) > 1) {
                    if ($this->dryRun) {
                        $windows++;
                    } else {
                        $event = CalendarEvent::query()->find($eventId);
                        if ($event !== null) {
                            $this->applyClusterWindowToEvent($event, $slots);
                            $windows++;
                        }
                    }
                }
                $skipped++;

                continue;
            }

            if ($this->dryRun) {
                $split += count($clusters) - 1;

                continue;
            }

            $event = CalendarEvent::query()->with('eventServices')->find($eventId);
            if ($event === null) {
                $skipped++;

                continue;
            }

            $split += $this->applySplitClustersToEvent($event, $clusters);
        }

        return [
            'appointments_split' => $split,
            'appointments_split_skipped' => $skipped,
            'appointments_windows_repaired' => $windows,
        ];
    }

    /**
     * Ajusta início/fim de visitas com vários serviços ao intervalo Zappy (máx. de início+duração por linha CSV).
     *
     * @return array<string, int>
     */
    public function repairMergedAppointmentWindows(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingServiceRefs();

        $userIdToProvider = [];
        foreach ($this->agentUserMap as $name => $uid) {
            $userIdToProvider[(int) $uid] = $name;
        }

        $zappyEventIds = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
            ->distinct()
            ->pluck('local_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $repaired = 0;
        $skipped = 0;
        $unchanged = 0;

        foreach ($zappyEventIds as $eventId) {
            $event = CalendarEvent::query()
                ->with(['eventServices', 'client'])
                ->find($eventId);
            if ($event === null || $event->eventServices->count() < 2) {
                $skipped++;

                continue;
            }

            $slots = $this->buildCsvSlotsForMergedEvent($event, $userIdToProvider);
            if (count($slots) < 2) {
                $skipped++;

                continue;
            }

            $windowStart = $slots[0]['start_at'];
            $windowEnd = $slots[0]['end_at'];
            foreach ($slots as $slot) {
                if ($slot['start_at']->lt($windowStart)) {
                    $windowStart = $slot['start_at'];
                }
                if ($slot['end_at']->gt($windowEnd)) {
                    $windowEnd = $slot['end_at'];
                }
            }

            if ($event->start_at->equalTo($windowStart) && $event->end_at->equalTo($windowEnd)) {
                $unchanged++;

                continue;
            }

            if ($this->dryRun) {
                $repaired++;

                continue;
            }

            $this->applyClusterWindowToEvent($event, $slots);
            $repaired++;
        }

        return [
            'appointments_merged_windows_repaired' => $repaired,
            'appointments_merged_windows_unchanged' => $unchanged,
            'appointments_merged_windows_skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, string>  $userIdToProvider
     * @return list<array{start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>
     */
    private function buildCsvSlotsForMergedEvent(CalendarEvent $event, array $userIdToProvider): array
    {
        $clientName = trim((string) ($event->client?->name ?? ''));
        if ($clientName === '') {
            return [];
        }

        $eventDay = $event->start_at->copy()->timezone($this->sourceTimezone())->format('Y-m-d');
        $serviceIds = $event->eventServices->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $expectedProvider = $userIdToProvider[(int) ($event->user_id ?? 0)] ?? null;

        $slots = [];
        foreach ($this->reader->read($this->csvPath('appointments')) as $row) {
            $rowClient = trim($row['client_name'] ?? '');
            if ($this->normalizeName($rowClient) !== $this->normalizeName($clientName)) {
                continue;
            }

            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }
            if ($expectedProvider !== null && $provider !== '' && $provider !== $expectedProvider) {
                continue;
            }

            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }
            if ($startAt->copy()->timezone($this->sourceTimezone())->format('Y-m-d') !== $eventDay) {
                continue;
            }

            $itemName = trim($row['item_name'] ?? '');
            if ($itemName === '') {
                continue;
            }

            [$serviceId] = $this->resolveServiceIdForImport($itemName);
            if ($serviceId === null || ! in_array((int) $serviceId, $serviceIds, true)) {
                continue;
            }

            $duration = max(1, (int) ($this->serviceDurations[$serviceId] ?? 30));
            $slots[] = [
                'start_at' => $startAt,
                'end_at' => $startAt->copy()->addMinutes($duration),
                'row' => $row,
                'item_name' => $itemName,
            ];
        }

        usort($slots, fn (array $a, array $b): int => $a['start_at']->timestamp <=> $b['start_at']->timestamp);

        return $slots;
    }

    /**
     * @param  list<list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>>  $clusters
     */
    private function applySplitClustersToEvent(CalendarEvent $event, array $clusters): int
    {
        $created = 0;
        $event->load('eventServices');
        $pivotsByServiceId = [];
        foreach ($event->eventServices as $svc) {
            $pivotsByServiceId[(int) $svc->id] = $svc->pivot->getAttributes();
        }

        for ($c = 1; $c < count($clusters); $c++) {
            $cluster = $clusters[$c];
            $newEvent = $this->createEventFromClusterTemplate($event, $cluster);
            $this->attachClusterServicesToEvent($newEvent, $cluster, $pivotsByServiceId);
            $this->detachClusterServicesFromEvent($event, $cluster);
            $this->applyClusterWindowToEvent($newEvent, $cluster);
            foreach ($cluster as $slot) {
                ZappyImportRef::query()
                    ->where('store_id', $this->storeId)
                    ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
                    ->where('zappy_key', $slot['ref']->zappy_key)
                    ->update(['local_id' => $newEvent->id]);
            }
            $created++;
        }

        $keepCluster = $clusters[0];
        $this->retainOnlyClusterServicesOnEvent($event, $keepCluster);
        $this->applyClusterWindowToEvent($event, $keepCluster);

        return $created;
    }

    /**
     * @param  list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>  $cluster
     */
    private function detachClusterServicesFromEvent(CalendarEvent $event, array $cluster): void
    {
        foreach ($cluster as $slot) {
            if ($slot['item_name'] === '') {
                continue;
            }
            [$serviceId] = $this->resolveServiceIdForImport($slot['item_name']);
            if ($serviceId !== null) {
                $event->eventServices()->detach($serviceId);
            }
        }
    }

    /**
     * @param  list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>  $cluster
     */
    private function retainOnlyClusterServicesOnEvent(CalendarEvent $event, array $cluster): void
    {
        $keepServiceIds = [];
        foreach ($cluster as $slot) {
            if ($slot['item_name'] === '') {
                continue;
            }
            [$serviceId] = $this->resolveServiceIdForImport($slot['item_name']);
            if ($serviceId !== null) {
                $keepServiceIds[] = $serviceId;
            }
        }
        $keepServiceIds = array_values(array_unique($keepServiceIds));

        $event->load('eventServices');
        foreach ($event->eventServices as $svc) {
            if (! in_array((int) $svc->id, $keepServiceIds, true)) {
                $event->eventServices()->detach($svc->id);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $pivotsByServiceId
     * @param  list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>  $cluster
     */
    private function attachClusterServicesToEvent(CalendarEvent $event, array $cluster, array $pivotsByServiceId): void
    {
        $sort = 0;
        foreach ($cluster as $slot) {
            if ($slot['item_name'] === '') {
                continue;
            }
            [$serviceId] = $this->resolveServiceIdForImport($slot['item_name']);
            if ($serviceId === null) {
                continue;
            }
            $pivot = $pivotsByServiceId[$serviceId] ?? null;
            $priceBase = round($this->parseDecimal($slot['row']['price_base'] ?? '0'), 2);
            $pivotPrice = $pivot !== null
                ? ($pivot['price'] ?? null)
                : ($priceBase > 0 ? $priceBase : null);
            $duration = $pivot !== null
                ? (int) ($pivot['duration'] ?? 30)
                : ($this->serviceDurations[$serviceId] ?? 30);

            $event->eventServices()->attach($serviceId, [
                'duration' => $duration,
                'price' => $pivotPrice,
                'original_price' => $pivot['original_price'] ?? $pivotPrice,
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @param  list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>  $cluster
     */
    private function applyClusterWindowToEvent(CalendarEvent $event, array $cluster): void
    {
        $start = $cluster[0]['start_at'];
        $end = $cluster[0]['end_at'];
        foreach ($cluster as $slot) {
            if ($slot['end_at']->gt($end)) {
                $end = $slot['end_at'];
            }
        }
        $clientName = trim((string) ($cluster[0]['row']['client_name'] ?? $event->client?->name ?? ''));
        $event->load('eventServices');
        $title = $clientName !== ''
            ? $this->buildMergedEventTitle($event, $clientName)
            : $event->title;

        CalendarEvent::withoutEvents(function () use ($event, $start, $end, $title): void {
            $event->update([
                'start_at' => $start,
                'end_at' => $end,
                'title' => $title,
                'service_id' => $event->eventServices->first()?->id,
            ]);
        });
    }

    /**
     * @param  list<array{ref: ZappyImportRef, start_at: Carbon, end_at: Carbon, row: array<string, string>, item_name: string}>  $cluster
     */
    private function createEventFromClusterTemplate(CalendarEvent $template, array $cluster): CalendarEvent
    {
        $start = $cluster[0]['start_at'];
        $end = $cluster[0]['end_at'];
        foreach ($cluster as $slot) {
            if ($slot['end_at']->gt($end)) {
                $end = $slot['end_at'];
            }
        }
        $clientName = trim((string) ($cluster[0]['row']['client_name'] ?? ''));

        return CalendarEvent::withoutEvents(function () use ($template, $start, $end, $clientName, $cluster) {
            $event = CalendarEvent::create([
                'store_id' => $template->store_id,
                'title' => $clientName !== '' ? $clientName.' — Marcação' : $template->title,
                'start_at' => $start,
                'end_at' => $end,
                'description' => $template->description,
                'user_id' => $template->user_id,
                'client_id' => $template->client_id,
                'service_id' => null,
                'event_type' => $template->event_type,
                'status' => $template->status,
                'marcacao_source' => $template->marcacao_source,
                'cancellation_reason' => $template->cancellation_reason,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ]);

            return $event;
        });
    }

    /**
     * Corrige horas de marcações já importadas (CSV em hora local → UTC), via referência Zappy.
     *
     * @return array<string, int>
     */
    public function repairAppointmentTimes(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingClientRefs();
        $this->loadExistingServiceRefs();

        $rows = $this->reader->read($this->csvPath('appointments'));
        $updated = 0;
        $notFound = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }

            $correctStart = $this->parseAppointmentDate($row['date'] ?? '');
            if ($correctStart === null) {
                continue;
            }

            $clientName = trim($row['client_name'] ?? '');
            $itemName = trim($row['item_name'] ?? '');
            $userId = (int) ($this->agentUserMap[$provider] ?? 0);
            $fingerprint = $this->appointmentFingerprint($correctStart, $clientName, $itemName, $userId);
            $eventId = $this->getRefLocalId(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint);

            if ($eventId === null || $eventId <= 0) {
                $notFound++;

                continue;
            }

            $event = CalendarEvent::query()->find($eventId);
            if ($event === null) {
                $notFound++;

                continue;
            }

            [$serviceId] = $this->resolveServiceIdForImport($itemName);
            $duration = $serviceId !== null ? ($this->serviceDurations[$serviceId] ?? 30) : 30;
            $duration = max(1, $duration);
            $correctEnd = $correctStart->copy()->addMinutes($duration);

            $refsOnEvent = ZappyImportRef::query()
                ->where('store_id', $this->storeId)
                ->where('entity_type', ZappyImportRef::TYPE_APPOINTMENT)
                ->where('local_id', $eventId)
                ->count();

            $servicesOnEvent = $event->eventServices()->count();

            if ($refsOnEvent <= 1 && $servicesOnEvent <= 1) {
                if ($event->start_at->equalTo($correctStart) && $event->end_at->equalTo($correctEnd)) {
                    $unchanged++;

                    continue;
                }

                if ($this->dryRun) {
                    $updated++;

                    continue;
                }

                CalendarEvent::withoutEvents(function () use ($event, $correctStart, $correctEnd): void {
                    $event->update([
                        'start_at' => $correctStart,
                        'end_at' => $correctEnd,
                    ]);
                });
                $updated++;
            }
        }

        return [
            'appointments_times_repaired' => $updated,
            'appointments_times_not_matched' => $notFound,
            'appointments_times_unchanged' => $unchanged,
        ];
    }

    /**
     * Alinha scope das vendas importadas para não mostrar «Falta faturar» na agenda.
     *
     * @return array<string, int>
     */
    public function repairImportedSalesScope(): array
    {
        $scope = (string) config('zappy_import.sales_scope', Sale::SCOPE_CAIXA_LIQUIDACAO);
        $saleIds = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SALE)
            ->pluck('local_id');

        if ($saleIds->isEmpty()) {
            return ['sales_scope_updated' => 0];
        }

        if ($this->dryRun) {
            $count = Sale::query()
                ->whereIn('id', $saleIds)
                ->where('scope', '!=', $scope)
                ->count();

            return ['sales_scope_updated' => $count];
        }

        $updated = Sale::query()
            ->whereIn('id', $saleIds)
            ->where('scope', '!=', $scope)
            ->update(['scope' => $scope]);

        return ['sales_scope_updated' => $updated];
    }

    /**
     * Preenche desconto nas vendas importadas a partir de item_total_discount do CSV Zappy.
     *
     * @return array<string, int>
     */
    public function repairImportedSaleDiscounts(): array
    {
        $discountByDoc = [];
        foreach ($this->reader->read($this->csvPath('sales')) as $row) {
            $docId = trim($row['doc_id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $discountByDoc[$docId] = round(
                ($discountByDoc[$docId] ?? 0.0) + $this->parseDecimal($row['item_total_discount'] ?? '0'),
                2
            );
        }

        $refs = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SALE)
            ->get(['zappy_key', 'local_id']);

        if ($refs->isEmpty()) {
            return ['sales_discount_updated' => 0, 'sales_discount_skipped' => 0];
        }

        $updated = 0;
        $skipped = 0;

        foreach ($refs as $ref) {
            $docId = (string) $ref->zappy_key;
            $desconto = $discountByDoc[$docId] ?? 0.0;
            $newValue = $desconto > 0 ? $desconto : null;

            $sale = Sale::query()->whereKey($ref->local_id)->first();
            if ($sale === null) {
                $skipped++;

                continue;
            }

            $current = $sale->desconto !== null ? round((float) $sale->desconto, 2) : null;
            if ($current === $newValue) {
                $skipped++;

                continue;
            }

            if ($this->dryRun) {
                $updated++;

                continue;
            }

            $sale->update(['desconto' => $newValue]);
            $updated++;
        }

        return ['sales_discount_updated' => $updated, 'sales_discount_skipped' => $skipped];
    }

    /**
     * Corrige data_emissao das vendas importadas a partir da coluna date do vendas.csv (formato d/m/Y).
     *
     * @return array<string, int>
     */
    public function repairImportedSaleDates(): array
    {
        $dateByDoc = [];
        foreach ($this->reader->read($this->csvPath('sales')) as $row) {
            $docId = trim($row['doc_id'] ?? '');
            if ($docId === '' || isset($dateByDoc[$docId])) {
                continue;
            }

            $parsed = $this->parseDateTimeIso($row['date'] ?? '');
            if ($parsed !== null) {
                $dateByDoc[$docId] = $parsed;
            }
        }

        $updated = 0;
        $skipped = 0;

        $sales = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('numero_fatura', 'not like', 'ZAPPY-%')
            ->get(['id', 'numero_fatura', 'data_emissao', 'created_at', 'updated_at']);

        foreach ($sales as $sale) {
            $baseDoc = $this->baseInvoiceNumber((string) $sale->numero_fatura);
            $parsed = $dateByDoc[$baseDoc] ?? null;
            if ($parsed === null) {
                $skipped++;

                continue;
            }

            $newDate = $parsed->toDateString();
            if ((string) $sale->data_emissao === $newDate) {
                $skipped++;

                continue;
            }

            if ($this->dryRun) {
                $updated++;

                continue;
            }

            $sale->update([
                'data_emissao' => $newDate,
                'created_at' => $parsed,
                'updated_at' => $parsed,
            ]);
            $updated++;
        }

        return ['sales_dates_updated' => $updated, 'sales_dates_skipped' => $skipped];
    }

    /**
     * Marcações importadas sem linha em calendar_event_services: associa serviço (fallback se necessário) com preço Zappy.
     *
     * @return array<string, int>
     */
    public function repairMissingAppointmentServices(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingServiceRefs();
        $this->loadExistingClientRefs();

        if ($this->defaultServiceId() === null) {
            return ['appointments_services_repaired' => 0, 'appointments_services_skipped' => 0];
        }

        $rows = $this->reader->read($this->csvPath('appointments'));
        $repaired = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }

            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }

            $itemName = trim($row['item_name'] ?? '');
            if ($itemName === '') {
                continue;
            }

            [$serviceId, $usedDefault] = $this->resolveServiceIdForImport($itemName);
            if ($serviceId === null) {
                $skipped++;

                continue;
            }

            $userId = $provider !== '' ? ($this->agentUserMap[$provider] ?? null) : null;
            $clientName = trim($row['client_name'] ?? '');
            $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : null;
            $fingerprint = $this->appointmentFingerprint($startAt, $clientName, $itemName, (int) ($userId ?? 0));
            $eventId = $this->getRefLocalId(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint)
                ?? $this->findImportedEventForRow($startAt, $clientId, $userId, $itemName);

            if ($eventId === null || $eventId <= 0) {
                $skipped++;

                continue;
            }

            $event = CalendarEvent::query()->with('eventServices')->find($eventId);
            if ($event === null) {
                $skipped++;

                continue;
            }

            if ($event->eventServices->isNotEmpty()) {
                $skipped++;

                continue;
            }

            if ($this->dryRun) {
                $repaired++;

                continue;
            }

            $priceBase = round($this->parseDecimal($row['price_base'] ?? '0'), 2);
            $duration = $this->serviceDurations[$serviceId] ?? 30;
            $pivotPrice = $priceBase > 0 ? $priceBase : null;

            $event->eventServices()->attach($serviceId, [
                'duration' => $duration,
                'price' => $pivotPrice,
                'original_price' => $pivotPrice,
                'sort_order' => 0,
            ]);

            $updates = [
                'service_id' => $serviceId,
                'updated_at' => $this->parseAppointmentDate($row['updated_on'] ?? '') ?? $event->updated_at,
            ];
            if ($usedDefault) {
                $updates['description'] = $this->appendZappyOriginalServiceNote(
                    (string) ($event->description ?? ''),
                    $itemName
                );
            }
            $event->update($updates);

            $repaired++;
        }

        return [
            'appointments_services_repaired' => $repaired,
            'appointments_services_skipped' => $skipped,
        ];
    }

    /**
     * Funde marcações consecutivas já importadas (mesmo cliente/técnica/dia) num único evento.
     *
     * @return array<string, int>
     */
    public function repairMergeConsecutiveAppointments(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingClientRefs();
        $this->loadExistingServiceRefs();

        $rows = $this->reader->read($this->csvPath('appointments'));
        $merged = $this->repairMergeByPaymentDateGroups($rows);

        return ['appointments_merged' => $merged];
    }

    /**
     * Funde eventos do mesmo checkout Zappy (mesmo payment_date no CSV).
     *
     * @param  list<array<string, string>>  $rows
     */
    private function repairMergeByPaymentDateGroups(array $rows): int
    {
        if (! config('zappy_import.merge_same_payment_date', true)) {
            return 0;
        }

        $groups = [];
        foreach ($rows as $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }

            $paymentKey = $this->paymentMergeKey($row);
            if ($paymentKey === '') {
                continue;
            }

            $statusLabel = trim($row['status'] ?? '');
            if (! in_array($statusLabel, config('zappy_import.merge_statuses', ['Pagou']), true)) {
                continue;
            }

            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }

            $clientName = trim($row['client_name'] ?? '');
            $userId = $provider !== '' ? ($this->agentUserMap[$provider] ?? null) : null;
            $groupKey = $this->normalizeName($clientName).'|'.(int) ($userId ?? 0).'|'.$startAt->format('Y-m-d').'|'.$paymentKey;
            $groups[$groupKey][] = $row;
        }

        $merged = 0;
        foreach ($groups as $groupRows) {
            if (count($groupRows) < 2) {
                continue;
            }

            $eventIds = [];
            foreach ($groupRows as $row) {
                $startAt = $this->parseAppointmentDate($row['date'] ?? '');
                if ($startAt === null) {
                    continue;
                }
                $clientName = trim($row['client_name'] ?? '');
                $provider = trim($row['service_provider'] ?? '');
                $userId = $provider !== '' ? ($this->agentUserMap[$provider] ?? null) : null;
                $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : null;
                $eventId = $this->findImportedEventForRow($startAt, $clientId, $userId, trim($row['item_name'] ?? ''));
                if ($eventId !== null) {
                    $eventIds[] = $eventId;
                }
            }

            $eventIds = array_values(array_unique($eventIds));
            if (count($eventIds) < 2) {
                continue;
            }

            sort($eventIds);
            $keepId = $eventIds[0];
            foreach (array_slice($eventIds, 1) as $absorbId) {
                if (! $this->dryRun) {
                    $this->absorbEventInto($keepId, $absorbId);
                }
                $merged++;
            }
        }

        return $merged;
    }

    /**
     * Reparte linhas de faturas Zappy por evento (após separar visitas fundidas) e sincroniza refs appointment_zappy.
     *
     * @return array<string, int>
     */
    public function repairDistributeSalesToEvents(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->ignoredAgents = config('zappy_import.ignored_agent_names', []);
        $this->loadExistingServiceRefs();
        $this->loadExistingClientRefs();
        $this->loadExistingAppointmentIndex();

        $marcacaoRowsByKey = $this->buildMarcacaoRowsIndex();
        $salesRows = $this->reader->read($this->csvPath('sales'));
        $grouped = [];
        foreach ($salesRows as $row) {
            $docId = trim($row['doc_id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $grouped[$docId][] = $row;
        }

        $distributed = 0;
        $syntheticRemoved = 0;
        $zappyRefsUpdated = 0;

        foreach ($grouped as $docId => $lines) {
            $saleId = $this->getRefLocalId(ZappyImportRef::TYPE_SALE, $docId)
                ?? Sale::query()
                    ->where('store_id', $this->storeId)
                    ->where('numero_fatura', $docId)
                    ->value('id');

            if ($saleId === null) {
                continue;
            }

            $sale = Sale::query()->with('items')->find($saleId);
            if ($sale === null) {
                continue;
            }

            $first = $lines[0];
            $clientName = trim($first['client_name'] ?? '');
            $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : (int) ($sale->client_id ?? 0);

            $usedMarcacaoKeys = [];
            $eventGroups = [];

            foreach ($lines as $line) {
                $eventId = $this->resolveEventIdForSaleLine($line, $clientId, $marcacaoRowsByKey, $usedMarcacaoKeys);
                if ($eventId === null || $eventId <= 0) {
                    continue;
                }

                $zappyApptId = trim($line['appointment_id'] ?? '');
                if ($zappyApptId !== '' && ! $this->dryRun) {
                    $this->saveRef(ZappyImportRef::TYPE_APPOINTMENT_ZAPPY, $zappyApptId, $eventId, [
                        'doc_id' => $docId,
                    ]);
                    $zappyRefsUpdated++;
                }

                $eventGroups[$eventId][] = $line;
            }

            $eventGroups = $this->expandEventGroupsWithOrphanLines($lines, $eventGroups);

            if ($eventGroups === []) {
                continue;
            }

            uksort($eventGroups, function (int $a, int $b): int {
                $ea = CalendarEvent::query()->find($a);
                $eb = CalendarEvent::query()->find($b);

                return ($ea?->start_at?->timestamp ?? 0) <=> ($eb?->start_at?->timestamp ?? 0);
            });

            if (count($eventGroups) === 1) {
                $onlyEventId = (int) array_key_first($eventGroups);
                if ((int) $sale->calendar_event_id !== $onlyEventId) {
                    if ($this->dryRun) {
                        $distributed++;
                    } else {
                        $this->reshapeSaleForEvent($sale, $onlyEventId, $eventGroups[$onlyEventId]);
                        $this->pruneRedundantSplitSalesForDocument($docId, $eventGroups, (int) $sale->id);
                        $distributed++;
                    }
                } else {
                    if (! $this->dryRun) {
                        $this->reshapeSaleForEvent($sale, $onlyEventId, $eventGroups[$onlyEventId]);
                        $this->pruneRedundantSplitSalesForDocument($docId, $eventGroups, (int) $sale->id);
                    }
                }

                continue;
            }

            if ($this->dryRun) {
                $distributed += count($eventGroups);

                continue;
            }

            $isFirst = true;
            foreach ($eventGroups as $eventId => $groupLines) {
                if ($isFirst) {
                    $this->reshapeSaleForEvent($sale, $eventId, $groupLines);
                    $isFirst = false;
                } else {
                    $this->createOrUpdateSplitSaleFromParent($sale, $docId, $eventId, $groupLines);
                }

                $this->removeSyntheticSaleForEventIfCovered($eventId, $syntheticRemoved);
            }

            if (! $this->dryRun) {
                $this->pruneRedundantSplitSalesForDocument($docId, $eventGroups, (int) $sale->id);
            }

            $distributed++;
        }

        $consolidateStats = $this->repairConsolidateDuplicateEventSales();

        return array_merge([
            'sales_distributed' => $distributed,
            'sales_zappy_refs_updated' => $zappyRefsUpdated,
            'sales_synthetic_removed' => $syntheticRemoved,
        ], $consolidateStats);
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function buildMarcacaoRowsIndex(): array
    {
        $index = [];
        foreach ($this->reader->read($this->csvPath('appointments')) as $row) {
            $provider = trim($row['service_provider'] ?? '');
            if ($provider !== '' && $this->isIgnoredAgent($provider)) {
                continue;
            }
            $startAt = $this->parseAppointmentDate($row['date'] ?? '');
            if ($startAt === null) {
                continue;
            }
            if (! $this->isMarcacaoRowLinkableForSale($row)) {
                continue;
            }

            $clientName = trim($row['client_name'] ?? '');
            $itemName = trim($row['item_name'] ?? '');
            $key = $this->normalizeName($clientName).'|'.$startAt->timezone($this->sourceTimezone())->format('Y-m-d').'|'.$this->normalizeKey($itemName);
            $index[$key][] = $row;
        }

        foreach ($index as &$rows) {
            usort($rows, function (array $a, array $b): int {
                $ta = $this->parseAppointmentDate($a['date'] ?? '')?->timestamp ?? 0;
                $tb = $this->parseAppointmentDate($b['date'] ?? '')?->timestamp ?? 0;

                return $ta <=> $tb;
            });
        }

        return $index;
    }

    /**
     * @param  array<string, list<array<string, string>>>  $marcacaoRowsByKey
     * @param  array<string, true>  $usedMarcacaoKeys
     */
    private function resolveEventIdForSaleLine(
        array $line,
        ?int $clientId,
        array $marcacaoRowsByKey,
        array &$usedMarcacaoKeys,
    ): ?int {
        $clientName = trim($line['client_name'] ?? '');
        $itemName = trim($line['item_name'] ?? '');
        $day = $this->parseAppointmentDateFromNote($line['item_note'] ?? '')
            ?? $this->parseDateTimeIso($line['date'] ?? '');

        if ($day !== null && $clientName !== '' && $itemName !== '') {
            $key = $this->normalizeName($clientName).'|'.$day->timezone($this->sourceTimezone())->format('Y-m-d').'|'.$this->normalizeKey($itemName);
            $candidates = $marcacaoRowsByKey[$key] ?? [];
            $salePerformer = trim($line['performer_name'] ?? '');
            $saleUserId = (int) ($this->agentUserMap[$salePerformer] ?? 0);
            $saleAt = $this->parseDateTimeIso($line['date'] ?? '');

            $bestEventId = null;
            $bestDiff = PHP_INT_MAX;

            foreach ($candidates as $idx => $marcacaoRow) {
                if (! $this->isMarcacaoRowLinkableForSale($marcacaoRow)) {
                    continue;
                }

                $slotKey = $key.'#'.$idx;
                if (isset($usedMarcacaoKeys[$slotKey])) {
                    continue;
                }

                $startAt = $this->parseDateTimeDMY($marcacaoRow['date'] ?? '');
                if ($startAt === null) {
                    continue;
                }

                $rowUserId = (int) ($this->agentUserMap[trim($marcacaoRow['service_provider'] ?? '')] ?? 0);
                if ($saleUserId > 0 && $rowUserId > 0 && $rowUserId !== $saleUserId) {
                    continue;
                }

                $fingerprint = $this->appointmentFingerprint($startAt, $clientName, $itemName, $rowUserId);
                $eventId = $this->getRefLocalId(ZappyImportRef::TYPE_APPOINTMENT, $fingerprint)
                    ?? ($this->appointmentIndex[$fingerprint] ?? null);

                if ($eventId === null || $eventId <= 0) {
                    continue;
                }

                $diff = $saleAt !== null
                    ? abs((int) $startAt->diffInMinutes($saleAt, false))
                    : 0;

                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestEventId = (int) $eventId;
                }
            }

            if ($bestEventId !== null) {
                foreach ($candidates as $idx => $marcacaoRow) {
                    if (! $this->isMarcacaoRowLinkableForSale($marcacaoRow)) {
                        continue;
                    }
                    $startAt = $this->parseDateTimeDMY($marcacaoRow['date'] ?? '');
                    if ($startAt === null) {
                        continue;
                    }
                    $rowUserId = (int) ($this->agentUserMap[trim($marcacaoRow['service_provider'] ?? '')] ?? 0);
                    $fp = $this->appointmentFingerprint($startAt, $clientName, $itemName, $rowUserId);
                    $eid = $this->getRefLocalId(ZappyImportRef::TYPE_APPOINTMENT, $fp)
                        ?? ($this->appointmentIndex[$fp] ?? null);
                    if ($eid === $bestEventId) {
                        $usedMarcacaoKeys[$key.'#'.$idx] = true;
                        break;
                    }
                }

                return $bestEventId;
            }

            $fallback = $this->matchAppointmentByDay(
                $day->copy()->timezone($this->sourceTimezone())->startOfDay(),
                $clientName,
                $itemName,
                $saleUserId,
                $clientId,
            );
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return $this->resolveSaleCalendarEventId($line, $clientId);
    }

    /**
     * Quando visitas foram fundidas num evento, linhas extra da mesma fatura podem falhar o fingerprint
     * mas o serviço já está no evento — incluir na repartição para o total bater certo.
     *
     * @param  list<array<string, string>>  $lines
     * @param  array<int, list<array<string, string>>>  $eventGroups
     * @return array<int, list<array<string, string>>>
     */
    private function expandEventGroupsWithOrphanLines(array $lines, array $eventGroups): array
    {
        if ($eventGroups === [] || count($eventGroups) !== 1) {
            return $eventGroups;
        }

        $onlyEventId = (int) array_key_first($eventGroups);
        $assigned = $eventGroups[$onlyEventId];
        if (count($assigned) >= count($lines)) {
            return $eventGroups;
        }

        $assignedKeys = array_map(fn (array $line): string => $this->saleLineIdentityKey($line), $assigned);
        foreach ($lines as $line) {
            if (in_array($this->saleLineIdentityKey($line), $assignedKeys, true)) {
                continue;
            }
            if ($this->saleLineServiceExistsOnEvent($line, $onlyEventId)) {
                $eventGroups[$onlyEventId][] = $line;
                $assignedKeys[] = $this->saleLineIdentityKey($line);
            }
        }

        return $eventGroups;
    }

    /**
     * @param  array<string, string>  $line
     */
    private function saleLineIdentityKey(array $line): string
    {
        return implode('|', [
            trim($line['item_name'] ?? ''),
            trim($line['appointment_id'] ?? ''),
            trim($line['item_total_price'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, string>  $line
     */
    private function saleLineServiceExistsOnEvent(array $line, int $eventId): bool
    {
        $itemName = trim($line['item_name'] ?? '');
        if ($itemName === '') {
            return false;
        }

        $serviceId = $this->resolveServiceIdForImport($itemName)[0];
        if ($serviceId === null || $serviceId <= 0) {
            return false;
        }

        return DB::table('calendar_event_services')
            ->where('calendar_event_id', $eventId)
            ->where('service_id', $serviceId)
            ->exists();
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    private function reshapeSaleForEvent(Sale $sale, int $eventId, array $lines): void
    {
        $total = 0.0;
        $desconto = 0.0;
        foreach ($lines as $line) {
            $total += $this->parseDecimal($line['item_total_price'] ?? '0');
            $desconto += $this->parseDecimal($line['item_total_discount'] ?? '0');
        }
        $total = round($total, 2);
        $desconto = round($desconto, 2);

        $sale->items()->delete();
        $sort = 0;
        foreach ($lines as $line) {
            $itemName = trim($line['item_name'] ?? '');
            $qty = max(1, (int) ($line['item_quantity'] ?? 1));
            $subtotal = round($this->parseDecimal($line['item_total_price'] ?? '0'), 2);
            $precoUnit = $qty > 0 ? round($subtotal / $qty, 2) : $subtotal;
            $serviceId = $itemName !== '' ? $this->resolveServiceIdForImport($itemName)[0] : null;
            $cesId = null;
            if ($serviceId !== null) {
                $cesId = DB::table('calendar_event_services')
                    ->where('calendar_event_id', $eventId)
                    ->where('service_id', $serviceId)
                    ->value('id');
            }

            SaleItem::create([
                'sale_id' => $sale->id,
                'tipo' => SaleItem::TIPO_SERVICO,
                'calendar_event_service_id' => $cesId,
                'service_id' => $serviceId,
                'descricao' => $itemName !== '' ? $itemName : 'Item',
                'quantidade' => $qty,
                'preco_unitario' => $precoUnit,
                'subtotal' => $subtotal,
                'sort_order' => $sort++,
            ]);
        }

        $sale->update([
            'calendar_event_id' => $eventId,
            'total' => $total,
            'desconto' => $desconto > 0 ? $desconto : null,
            'valor_pago' => $sale->status === Sale::STATUS_ANULADO ? 0 : $total,
            'scope' => (string) config('zappy_import.sales_scope', Sale::SCOPE_CAIXA_LIQUIDACAO),
        ]);
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    private function createOrUpdateSplitSaleFromParent(Sale $parent, string $docId, int $eventId, array $lines): void
    {
        $numero = Str::limit($docId.'@'.$eventId, 64, '');
        $total = 0.0;
        $desconto = 0.0;
        foreach ($lines as $line) {
            $total += $this->parseDecimal($line['item_total_price'] ?? '0');
            $desconto += $this->parseDecimal($line['item_total_discount'] ?? '0');
        }
        $total = round($total, 2);
        $desconto = round($desconto, 2);

        $sale = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('numero_fatura', $numero)
            ->first();

        if ($sale === null) {
            $sale = Sale::create([
                'store_id' => $this->storeId,
                'calendar_event_id' => $eventId,
                'client_id' => $parent->client_id,
                'numero_fatura' => $numero,
                'data_emissao' => $parent->data_emissao,
                'total' => $total,
                'desconto' => $desconto > 0 ? $desconto : null,
                'valor_pago' => $total,
                'payment_method' => $parent->payment_method,
                'scope' => (string) config('zappy_import.sales_scope', Sale::SCOPE_CAIXA_LIQUIDACAO),
                'status' => Sale::STATUS_PAGO,
                'issue_without_fiscal_id' => true,
                'created_at' => $parent->created_at,
                'updated_at' => $parent->updated_at,
            ]);
            $this->saveRef(ZappyImportRef::TYPE_SALE, $numero, (int) $sale->id, ['split_from' => $docId]);
        } else {
            $sale->update([
                'calendar_event_id' => $eventId,
                'client_id' => $parent->client_id,
                'total' => $total,
                'desconto' => $desconto > 0 ? $desconto : null,
                'valor_pago' => $total,
            ]);
            $sale->items()->delete();
        }

        $sort = 0;
        foreach ($lines as $line) {
            $itemName = trim($line['item_name'] ?? '');
            $qty = max(1, (int) ($line['item_quantity'] ?? 1));
            $subtotal = round($this->parseDecimal($line['item_total_price'] ?? '0'), 2);
            $precoUnit = $qty > 0 ? round($subtotal / $qty, 2) : $subtotal;
            $serviceId = $itemName !== '' ? $this->resolveServiceIdForImport($itemName)[0] : null;
            $cesId = null;
            if ($serviceId !== null) {
                $cesId = DB::table('calendar_event_services')
                    ->where('calendar_event_id', $eventId)
                    ->where('service_id', $serviceId)
                    ->value('id');
            }

            SaleItem::create([
                'sale_id' => $sale->id,
                'tipo' => SaleItem::TIPO_SERVICO,
                'calendar_event_service_id' => $cesId,
                'service_id' => $serviceId,
                'descricao' => $itemName !== '' ? $itemName : 'Item',
                'quantidade' => $qty,
                'preco_unitario' => $precoUnit,
                'subtotal' => $subtotal,
                'sort_order' => $sort++,
            ]);
        }
    }

    private function removeSyntheticSaleForEventIfCovered(int $eventId, int &$removedCounter): void
    {
        $synthetic = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('calendar_event_id', $eventId)
            ->where('numero_fatura', 'like', 'ZAPPY-%')
            ->first();

        if ($synthetic === null) {
            return;
        }

        $hasReal = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('calendar_event_id', $eventId)
            ->where('numero_fatura', 'not like', 'ZAPPY-%')
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->exists();

        if (! $hasReal) {
            return;
        }

        if ($this->dryRun) {
            $removedCounter++;

            return;
        }

        $synthetic->items()->delete();
        $synthetic->delete();
        $removedCounter++;
    }

    /**
     * Remove vendas repartidas obsoletas quando a fatura passa a pertencer a um único evento.
     *
     * @param  array<int, list<array<string, string>>>  $eventGroups
     */
    private function pruneRedundantSplitSalesForDocument(string $docId, array $eventGroups, int $parentSaleId): void
    {
        if ($this->dryRun || $eventGroups === []) {
            return;
        }

        $eventIds = array_map('intval', array_keys($eventGroups));

        if (count($eventGroups) === 1) {
            $splits = Sale::query()
                ->where('store_id', $this->storeId)
                ->where('id', '!=', $parentSaleId)
                ->where('numero_fatura', 'like', $docId.'@%')
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->get();

            foreach ($splits as $split) {
                $this->deleteImportedSale($split);
            }

            return;
        }

        $firstEventId = (int) array_key_first($eventGroups);
        $redundantOnFirst = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('numero_fatura', Str::limit($docId.'@'.$firstEventId, 64, ''))
            ->where('id', '!=', $parentSaleId)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->get();

        foreach ($redundantOnFirst as $split) {
            $this->deleteImportedSale($split);
        }

        $stale = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('numero_fatura', 'like', $docId.'@%')
            ->whereNotIn('calendar_event_id', $eventIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->get();

        foreach ($stale as $split) {
            $this->deleteImportedSale($split);
        }
    }

    /**
     * Elimina vendas em duplicado no mesmo evento quando a soma excede o subtotal da visita.
     *
     * @return array<string, int>
     */
    public function repairConsolidateDuplicateEventSales(): array
    {
        $removed = 0;

        $eventIds = Sale::query()
            ->where('store_id', $this->storeId)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->whereNotNull('calendar_event_id')
            ->select('calendar_event_id')
            ->groupBy('calendar_event_id')
            ->havingRaw('count(*) > 1')
            ->pluck('calendar_event_id');

        foreach ($eventIds as $eventId) {
            $event = CalendarEvent::query()
                ->with('eventServices')
                ->find((int) $eventId);

            if ($event === null || $event->event_type !== CalendarEvent::TYPE_MARCACAO || $event->status !== CalendarEvent::STATUS_COMPLETO) {
                continue;
            }

            $subtotal = round((float) $event->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
            if ($subtotal <= 0) {
                continue;
            }

            $sales = Sale::query()
                ->where('store_id', $this->storeId)
                ->where('calendar_event_id', $event->id)
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->orderBy('id')
                ->get();

            if ($sales->count() < 2) {
                continue;
            }

            if (round((float) $sales->sum('total'), 2) <= $subtotal + 0.05) {
                continue;
            }

            $byDoc = [];
            foreach ($sales as $sale) {
                $byDoc[$this->baseInvoiceNumber((string) $sale->numero_fatura)][] = $sale;
            }

            foreach ($byDoc as $docSales) {
                if (count($docSales) < 2) {
                    continue;
                }

                $keep = $this->pickBestSaleForEvent($docSales, (int) $event->id, $subtotal);
                foreach ($docSales as $sale) {
                    if ((int) $sale->id === (int) $keep->id) {
                        continue;
                    }

                    if ($this->dryRun) {
                        $removed++;

                        continue;
                    }

                    $this->deleteImportedSale($sale);
                    $removed++;
                }
            }

            $sales = Sale::query()
                ->where('store_id', $this->storeId)
                ->where('calendar_event_id', $event->id)
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->orderBy('id')
                ->get();

            if ($sales->count() < 2 || round((float) $sales->sum('total'), 2) <= $subtotal + 0.05) {
                continue;
            }

            $keep = $this->pickBestSaleForEvent($sales->all(), (int) $event->id, $subtotal);
            foreach ($sales as $sale) {
                if ((int) $sale->id === (int) $keep->id) {
                    continue;
                }

                if ($this->dryRun) {
                    $removed++;

                    continue;
                }

                $this->deleteImportedSale($sale);
                $removed++;
            }
        }

        return ['sales_duplicates_removed' => $removed];
    }

    private function baseInvoiceNumber(string $numero): string
    {
        if (str_contains($numero, '@')) {
            return explode('@', $numero, 2)[0];
        }

        return $numero;
    }

    /**
     * @param  list<Sale>  $sales
     */
    private function pickBestSaleForEvent(array $sales, int $eventId, float $subtotal): Sale
    {
        $best = $sales[0];
        $bestScore = PHP_INT_MAX;

        foreach ($sales as $sale) {
            $total = round((float) $sale->total, 2);
            $score = abs($total - $subtotal);

            if (str_ends_with((string) $sale->numero_fatura, '@'.$eventId)) {
                $score -= 0.01;
            }

            if ($this->saleHasImportRef((int) $sale->id)) {
                $score -= 0.02;
            }

            if ($total > $subtotal + 0.05) {
                $score += 100;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $sale;
            }
        }

        return $best;
    }

    private function saleHasImportRef(int $saleId): bool
    {
        return ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SALE)
            ->where('local_id', $saleId)
            ->exists();
    }

    private function deleteImportedSale(Sale $sale): void
    {
        ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SALE)
            ->where('local_id', $sale->id)
            ->delete();

        $sale->items()->delete();
        $sale->delete();
    }

    /**
     * Move vendas ligadas a marcações canceladas/anuladas/faltou para a marcação correta (CSV).
     *
     * @return array<string, int>
     */
    public function repairRelinkSalesOffCancelledEvents(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->loadExistingClientRefs();
        $this->loadExistingServiceRefs();
        $this->loadExistingAppointmentIndex();

        $marcacaoRowsByKey = $this->buildMarcacaoRowsIndex();
        $salesRows = $this->reader->read($this->csvPath('sales'));
        $grouped = [];
        foreach ($salesRows as $row) {
            $docId = trim($row['doc_id'] ?? '');
            if ($docId !== '') {
                $grouped[$docId][] = $row;
            }
        }

        $moved = 0;
        $cleared = 0;

        $badSales = Sale::query()
            ->where('store_id', $this->storeId)
            ->whereNotNull('calendar_event_id')
            ->whereHas('calendarEvent', function ($q): void {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->whereIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_ANULADO,
                        CalendarEvent::STATUS_FALTOU,
                    ]);
            })
            ->get();

        foreach ($badSales as $sale) {
            $docId = $sale->numero_fatura;
            if (str_contains($docId, '@')) {
                $docId = explode('@', $docId, 2)[0];
            }

            $lines = $grouped[$docId] ?? [];
            if ($lines === []) {
                if ($this->dryRun) {
                    $cleared++;
                } else {
                    $sale->update(['calendar_event_id' => null]);
                    $cleared++;
                }

                continue;
            }

            $clientName = trim($lines[0]['client_name'] ?? '');
            $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : (int) ($sale->client_id ?? 0);
            $usedKeys = [];
            $bestEventId = null;
            $bestLines = [];

            foreach ($lines as $line) {
                $eventId = $this->resolveEventIdForSaleLine($line, $clientId ?: null, $marcacaoRowsByKey, $usedKeys);
                if ($eventId !== null && $eventId > 0) {
                    $bestEventId = $eventId;
                    $bestLines = [$line];
                    break;
                }
            }

            if ($bestEventId === null) {
                continue;
            }

            if ((int) $sale->calendar_event_id === $bestEventId) {
                continue;
            }

            if ($this->dryRun) {
                $moved++;

                continue;
            }

            $this->reshapeSaleForEvent($sale, $bestEventId, $bestLines !== [] ? $bestLines : $lines);
            $moved++;
        }

        return [
            'sales_moved_off_cancelled' => $moved,
            'sales_unlinked_from_cancelled' => $cleared,
        ];
    }

    /**
     * Religa vendas importadas ao evento que contém todos os serviços da fatura.
     *
     * @return array<string, int>
     */
    public function repairSalesEventLinks(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->loadExistingClientRefs();
        $this->loadExistingServiceRefs();
        $this->loadExistingAppointmentIndex();

        $rows = $this->reader->read($this->csvPath('sales'));
        $grouped = [];
        foreach ($rows as $row) {
            $docId = trim($row['doc_id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $grouped[$docId][] = $row;
        }

        $updated = 0;
        foreach ($grouped as $docId => $lines) {
            $saleId = $this->getRefLocalId(ZappyImportRef::TYPE_SALE, $docId)
                ?? Sale::query()
                    ->where('store_id', $this->storeId)
                    ->where('numero_fatura', $docId)
                    ->value('id');

            if ($saleId === null) {
                continue;
            }

            $first = $lines[0];
            $clientName = trim($first['client_name'] ?? '');
            $clientId = $clientName !== '' ? $this->resolveClientIdByName($clientName) : null;
            $sale = Sale::query()->find($saleId);
            if ($sale === null) {
                continue;
            }

            if ($clientId !== null && (int) $sale->client_id !== $clientId) {
                if (! $this->dryRun) {
                    $sale->update(['client_id' => $clientId]);
                }
            }

            $bestEventId = $this->resolveBestCalendarEventForSale($lines, $clientId, (float) $sale->total);

            if ($bestEventId === null) {
                continue;
            }

            $currentEventId = (int) $sale->calendar_event_id;
            $currentEvent = $currentEventId > 0 ? CalendarEvent::query()->find($currentEventId) : null;
            $currentIsBad = $currentEvent !== null && ! $this->isMarcacaoLinkableForSale($currentEvent);

            if ($currentEventId === $bestEventId && ! $currentIsBad) {
                continue;
            }

            if ($this->dryRun) {
                $updated++;

                continue;
            }

            $sale->update(['calendar_event_id' => $bestEventId]);

            foreach ($lines as $idx => $line) {
                $itemName = trim($line['item_name'] ?? '');
                $serviceId = $itemName !== ''
                    ? $this->resolveServiceIdForImport($itemName)[0]
                    : null;
                if ($serviceId === null) {
                    continue;
                }
                $cesId = DB::table('calendar_event_services')
                    ->where('calendar_event_id', $bestEventId)
                    ->where('service_id', $serviceId)
                    ->value('id');
                if ($cesId === null) {
                    continue;
                }
                SaleItem::query()
                    ->where('sale_id', $saleId)
                    ->where('service_id', $serviceId)
                    ->update(['calendar_event_service_id' => $cesId]);
            }

            $updated++;
        }

        return ['sales_relinked' => $updated];
    }

    /**
     * Marcações pagas importadas sem venda: religa fatura existente ou cria venda sintética (histórico Zappy).
     *
     * @return array<string, int>
     */
    public function repairOrphanPaidAppointments(): array
    {
        $this->agentUserMap = config('zappy_import.agent_user_map', []);
        $this->loadExistingClientRefs();
        $this->loadExistingServiceRefs();

        $relinked = 0;
        $synthetic = 0;

        $orphans = CalendarEvent::query()
            ->where('store_id', $this->storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', CalendarEvent::STATUS_COMPLETO)
            ->where('description', 'like', '[Importado Zappy]%')
            ->whereDoesntHave('sales', fn ($q) => $q->where('status', '!=', Sale::STATUS_ANULADO))
            ->with('eventServices')
            ->get();

        foreach ($orphans as $event) {
            $subtotal = round((float) $event->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
            if ($subtotal <= 0) {
                continue;
            }

            $sale = Sale::query()
                ->where('store_id', $this->storeId)
                ->where('client_id', $event->client_id)
                ->where('status', '!=', Sale::STATUS_ANULADO)
                ->whereDate('data_emissao', $event->start_at->toDateString())
                ->whereBetween('total', [$subtotal - 0.02, $subtotal + 0.02])
                ->orderByDesc('id')
                ->get()
                ->first(function (Sale $candidate) use ($event): bool {
                    if ((int) $candidate->calendar_event_id === (int) $event->id) {
                        return false;
                    }
                    if ($candidate->calendar_event_id === null) {
                        return true;
                    }
                    $linked = CalendarEvent::query()->find($candidate->calendar_event_id);

                    return $linked === null || ! $this->isMarcacaoLinkableForSale($linked);
                });

            if ($sale !== null) {
                if (! $this->dryRun) {
                    $sale->update(['calendar_event_id' => $event->id]);
                    $this->syncSaleItemsToEvent((int) $sale->id, (int) $event->id);
                }
                $relinked++;

                continue;
            }

            if (! config('zappy_import.create_synthetic_sale_when_no_invoice', true)) {
                continue;
            }

            if ($this->dryRun) {
                $synthetic++;

                continue;
            }

            $this->createSyntheticSaleForEvent($event, $subtotal);
            $synthetic++;
        }

        return [
            'orphan_sales_relinked' => $relinked,
            'orphan_synthetic_sales_created' => $synthetic,
        ];
    }

    private function createSyntheticSaleForEvent(CalendarEvent $event, float $total): void
    {
        $scope = (string) config('zappy_import.sales_scope', Sale::SCOPE_CAIXA_LIQUIDACAO);
        $numero = 'ZAPPY-'.$event->id.'-'.$event->start_at->format('Ymd');

        if (Sale::query()->where('store_id', $this->storeId)->where('numero_fatura', $numero)->exists()) {
            return;
        }

        $sale = Sale::create([
            'store_id' => $this->storeId,
            'calendar_event_id' => $event->id,
            'client_id' => $event->client_id,
            'numero_fatura' => $numero,
            'data_emissao' => $event->start_at->toDateString(),
            'total' => $total,
            'valor_pago' => $total,
            'payment_method' => null,
            'scope' => $scope,
            'status' => Sale::STATUS_PAGO,
            'issue_without_fiscal_id' => true,
        ]);

        foreach ($event->eventServices as $idx => $svc) {
            $sub = round((float) ($svc->pivot->price ?? 0), 2);
            SaleItem::create([
                'sale_id' => $sale->id,
                'tipo' => SaleItem::TIPO_SERVICO,
                'calendar_event_service_id' => $svc->pivot->id,
                'service_id' => $svc->id,
                'descricao' => $svc->name,
                'quantidade' => 1,
                'preco_unitario' => $sub,
                'subtotal' => $sub,
                'sort_order' => $idx,
            ]);
        }

        $this->saveRef(ZappyImportRef::TYPE_SALE, $numero, (int) $sale->id, ['synthetic' => true]);
    }

    private function syncSaleItemsToEvent(int $saleId, int $eventId): void
    {
        $items = SaleItem::query()->where('sale_id', $saleId)->get();
        foreach ($items as $item) {
            if ($item->service_id === null) {
                continue;
            }
            $cesId = DB::table('calendar_event_services')
                ->where('calendar_event_id', $eventId)
                ->where('service_id', $item->service_id)
                ->value('id');
            if ($cesId !== null) {
                $item->update(['calendar_event_service_id' => $cesId]);
            }
        }
    }

    private function mergeChainKey(string $clientName, ?int $userId, Carbon $startAt): string
    {
        return $this->normalizeName($clientName).'|'.(int) ($userId ?? 0).'|'.$startAt->format('Y-m-d');
    }

    private function paymentMergeKey(array $row): string
    {
        $paidAt = $this->parseAppointmentDate($row['payment_date'] ?? '');

        return $paidAt?->format('Y-m-d H:i') ?? '';
    }

    private function shouldMergeConsecutive(array $chainHead, Carbon $nextStart, string $nextStatusLabel, array $nextRow): bool
    {
        if (! config('zappy_import.merge_consecutive_appointments', true)) {
            return false;
        }

        $mergeStatuses = config('zappy_import.merge_statuses', ['Pagou']);
        if (! in_array($chainHead['status_label'], $mergeStatuses, true)
            || ! in_array($nextStatusLabel, $mergeStatuses, true)) {
            return false;
        }

        $maxGap = max(0, (int) config('zappy_import.merge_max_gap_minutes', 60));
        $gapFromEnd = (int) $chainHead['end_at']->diffInMinutes($nextStart, false);
        if ($gapFromEnd >= -5 && $gapFromEnd <= $maxGap) {
            return true;
        }

        $maxSpan = max(0, (int) config('zappy_import.merge_max_start_span_minutes', 120));
        $chainStart = $chainHead['start_at'] ?? $chainHead['end_at'];
        $gapFromStart = (int) $chainStart->diffInMinutes($nextStart, false);
        if ($gapFromStart >= 0 && $gapFromStart <= $maxSpan) {
            return true;
        }

        if (config('zappy_import.merge_same_payment_date', true)) {
            $prevPay = $chainHead['payment_key'] ?? '';
            $nextPay = $this->paymentMergeKey($nextRow);
            if ($prevPay !== '' && $nextPay !== '' && $prevPay === $nextPay) {
                return $gapFromEnd >= -5 && $gapFromEnd <= $maxGap;
            }
        }

        return false;
    }

    private function registerAppointmentFingerprint(string $fingerprint, int $eventId): void
    {
        $this->appointmentIndex[$fingerprint] = $eventId;
    }

    private function appendServiceToEvent(
        int $eventId,
        ?int $serviceId,
        int $duration,
        float $priceBase,
        Carbon $newEndAt,
        string $clientName,
        string $itemName,
        Carbon $updatedAt,
    ): void {
        $event = CalendarEvent::query()->with('eventServices')->find($eventId);
        if ($event === null) {
            return;
        }

        if ($serviceId !== null && ! $event->eventServices->contains('id', $serviceId)) {
            $pivotPrice = $priceBase > 0 ? round($priceBase, 2) : null;
            $event->eventServices()->attach($serviceId, [
                'duration' => $duration,
                'price' => $pivotPrice,
                'original_price' => $pivotPrice,
                'sort_order' => $event->eventServices->count(),
            ]);
            $event->load('eventServices');
        }

        $endAt = ($event->end_at !== null && $event->end_at->gt($newEndAt))
            ? $event->end_at->copy()
            : $newEndAt;

        $event->update([
            'end_at' => $endAt,
            'title' => $this->buildMergedEventTitle($event, $clientName),
            'updated_at' => $updatedAt,
        ]);
    }

    private function buildMergedEventTitle(CalendarEvent $event, string $clientName): string
    {
        $event->loadMissing('eventServices');
        $names = $event->eventServices
            ->map(fn ($s) => $s->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return $event->title;
        }

        $label = $clientName !== '' ? $clientName : ($event->client?->name ?? 'Marcação');

        return $label.' — '.implode(', ', $names);
    }

    private function absorbEventInto(int $keepEventId, int $absorbEventId): void
    {
        if ($keepEventId === $absorbEventId) {
            return;
        }

        $keep = CalendarEvent::query()->with('eventServices')->find($keepEventId);
        $absorb = CalendarEvent::query()->with('eventServices')->find($absorbEventId);
        if ($keep === null || $absorb === null) {
            return;
        }

        foreach ($absorb->eventServices as $svc) {
            $serviceId = (int) $svc->id;
            if ($keep->eventServices->contains('id', $serviceId)) {
                continue;
            }
            $keep->eventServices()->attach($serviceId, [
                'duration' => $svc->pivot->duration,
                'price' => $svc->pivot->price,
                'original_price' => $svc->pivot->original_price,
                'sort_order' => $keep->eventServices->count(),
            ]);
        }

        $newEnd = $keep->end_at->gt($absorb->end_at) ? $keep->end_at : $absorb->end_at;
        $keep->update([
            'end_at' => $newEnd,
            'title' => $this->buildMergedEventTitle($keep->fresh(['eventServices']), $keep->client?->name ?? ''),
        ]);

        Sale::query()->where('calendar_event_id', $absorbEventId)->update(['calendar_event_id' => $keepEventId]);

        ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('local_id', $absorbEventId)
            ->whereIn('entity_type', [ZappyImportRef::TYPE_APPOINTMENT, ZappyImportRef::TYPE_APPOINTMENT_ZAPPY])
            ->update(['local_id' => $keepEventId]);

        CalendarEvent::withoutEvents(fn () => $absorb->delete());
    }

    private function findImportedEventForRow(Carbon $startAt, ?int $clientId, ?int $userId, string $itemName): ?int
    {
        $query = $this->applyLinkableMarcacaoScope(
            CalendarEvent::query()
                ->where('store_id', $this->storeId)
                ->where('description', 'like', '[Importado Zappy]%')
        )->whereDate('start_at', $startAt->toDateString());

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $events = $this->filterLinkableMarcacaoEvents($query->with('eventServices')->get());
        if ($events->isEmpty()) {
            return null;
        }

        if ($itemName !== '') {
            $serviceId = $this->resolveServiceIdForImport($itemName)[0];
            if ($serviceId !== null) {
                $byService = $events->filter(fn (CalendarEvent $e) => $e->eventServices->contains('id', $serviceId));
                if ($byService->count() === 1) {
                    return (int) $byService->first()->id;
                }
                if ($byService->isNotEmpty()) {
                    return $this->closestEventByStart($byService, $startAt);
                }
            }
        }

        if ($events->count() === 1) {
            return (int) $events->first()->id;
        }

        return $this->closestEventByStart($events, $startAt);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $events
     */
    private function closestEventByStart($events, Carbon $target): ?int
    {
        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($events as $event) {
            $diff = abs((int) $event->start_at->diffInMinutes($target, false));
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (int) $event->id;
            }
        }

        return $bestDiff <= 180 ? $best : null;
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    private function resolveBestCalendarEventForSale(array $lines, ?int $clientId, ?float $saleTotal = null): ?int
    {
        $candidateIds = [];
        foreach ($lines as $line) {
            $id = $this->resolveSaleCalendarEventId($line, $clientId);
            if ($id !== null && $id > 0) {
                $candidateIds[] = $id;
            }
        }
        $candidateIds = array_values(array_unique(array_filter(
            $candidateIds,
            function (int $eventId): bool {
                $event = CalendarEvent::query()->find($eventId);

                return $event !== null && $this->isMarcacaoLinkableForSale($event);
            }
        )));

        $neededServiceIds = [];
        $performer = '';
        $clientName = '';
        $day = null;

        foreach ($lines as $line) {
            $itemName = trim($line['item_name'] ?? '');
            if ($itemName !== '') {
                $sid = $this->resolveServiceIdForImport($itemName)[0];
                if ($sid !== null) {
                    $neededServiceIds[] = $sid;
                }
            }
            if ($performer === '') {
                $performer = trim($line['performer_name'] ?? '');
            }
            if ($clientName === '') {
                $clientName = trim($line['client_name'] ?? '');
            }
            if ($day === null) {
                $day = $this->parseAppointmentDateFromNote($line['item_note'] ?? '')
                    ?? $this->parseDateTimeIso($line['date'] ?? '');
            }
        }
        $neededServiceIds = array_values(array_unique($neededServiceIds));

        if ($day !== null && $neededServiceIds !== []) {
            $userId = (int) ($this->agentUserMap[$performer] ?? 0);
            $match = $this->findEventContainingServices($day, $clientId, $clientName, $userId, $neededServiceIds, $saleTotal);
            if ($match !== null) {
                return $match;
            }
        }

        if (count($candidateIds) === 1) {
            return $candidateIds[0];
        }

        if (count($candidateIds) > 1 && $neededServiceIds !== []) {
            $best = null;
            $bestScore = -1;
            foreach ($candidateIds as $eventId) {
                $event = CalendarEvent::query()->find($eventId);
                if ($event === null || ! $this->isMarcacaoLinkableForSale($event)) {
                    continue;
                }
                $attached = DB::table('calendar_event_services')
                    ->where('calendar_event_id', $eventId)
                    ->whereIn('service_id', $neededServiceIds)
                    ->pluck('service_id')
                    ->unique()
                    ->count();
                if ($attached > $bestScore) {
                    $bestScore = $attached;
                    $best = $eventId;
                }
            }
            if ($best !== null && $bestScore === count($neededServiceIds)) {
                return $best;
            }
        }

        return $candidateIds[0] ?? null;
    }

    /**
     * @param  list<int>  $serviceIds
     */
    private function findEventContainingServices(
        Carbon $day,
        ?int $clientId,
        string $clientName,
        int $userId,
        array $serviceIds,
        ?float $saleTotal = null,
    ): ?int {
        if ($serviceIds === []) {
            return null;
        }

        $query = $this->applyLinkableMarcacaoScope(
            CalendarEvent::query()
                ->where('store_id', $this->storeId)
                ->where('description', 'like', '[Importado Zappy]%')
        )->whereDate('start_at', $day->toDateString());

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        } elseif ($clientName !== '') {
            $resolved = $this->resolveClientIdByName($clientName);
            if ($resolved !== null) {
                $query->where('client_id', $resolved);
            }
        }

        $events = $this->filterLinkableMarcacaoEvents($query->with('eventServices')->get());
        $required = count($serviceIds);
        $best = null;
        $bestScore = 0;
        $bestTotalDiff = PHP_FLOAT_MAX;

        foreach ($events as $event) {
            $attached = (int) DB::table('calendar_event_services')
                ->where('calendar_event_id', $event->id)
                ->whereIn('service_id', $serviceIds)
                ->distinct()
                ->count('service_id');
            if ($attached < $bestScore) {
                continue;
            }

            $eventSubtotal = round((float) $event->eventServices->sum(fn ($s) => (float) ($s->pivot->price ?? 0)), 2);
            $totalDiff = $saleTotal !== null ? abs($eventSubtotal - $saleTotal) : 0.0;

            if ($attached > $bestScore || ($attached === $bestScore && $totalDiff < $bestTotalDiff)) {
                $bestScore = $attached;
                $bestTotalDiff = $totalDiff;
                $best = (int) $event->id;
            }
        }

        if ($bestScore >= $required) {
            return $best;
        }

        if ($saleTotal !== null && $best !== null && $bestTotalDiff <= 0.05) {
            return $best;
        }

        return $bestScore > 0 ? $best : null;
    }

    private function parseBirthDate(string $year, string $month, string $day): ?string
    {
        $y = (int) $year;
        $m = (int) $month;
        $d = (int) $day;
        if ($y <= 0 || $m <= 0 || $d <= 0) {
            return null;
        }

        if (! checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private function parseDecimal(string $value): float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    private function mapGender(string $raw): ?string
    {
        return match (mb_strtolower(trim($raw), 'UTF-8')) {
            'f', 'female', 'feminino' => 'F',
            'm', 'male', 'masculino' => 'M',
            default => null,
        };
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($name, 'UTF-8');
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '', 'UTF-8');
    }

    private function normalizePhoneKey(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    private function normalizeAgentName(string $name): string
    {
        return $this->normalizeName($name);
    }

    private function isIgnoredAgent(string $name): bool
    {
        $norm = $this->normalizeAgentName($name);
        foreach ($this->ignoredAgents as $ignored) {
            if ($this->normalizeAgentName($ignored) === $norm) {
                return true;
            }
        }

        return false;
    }

    private function refExists(string $type, string $key): bool
    {
        if ($this->fresh) {
            return false;
        }

        return ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', $type)
            ->where('zappy_key', $key)
            ->exists();
    }

    private function getRefLocalId(string $type, string $key): ?int
    {
        $id = ZappyImportRef::query()
            ->where('store_id', $this->storeId)
            ->where('entity_type', $type)
            ->where('zappy_key', $key)
            ->value('local_id');

        return $id !== null ? (int) $id : null;
    }

    private function ensureServiceDuration(int $serviceId): void
    {
        if (isset($this->serviceDurations[$serviceId])) {
            return;
        }

        $duration = Service::query()->whereKey($serviceId)->value('duration');
        $this->serviceDurations[$serviceId] = (int) ($duration ?? 30);
    }

    private function saveRef(string $type, string $key, int $localId, ?array $meta = null): void
    {
        if ($this->dryRun) {
            return;
        }

        ZappyImportRef::query()->updateOrCreate(
            [
                'store_id' => $this->storeId,
                'entity_type' => $type,
                'zappy_key' => $key,
            ],
            [
                'local_id' => $localId,
                'meta' => $meta,
            ]
        );
    }

    /**
     * Remove marcações, vendas, reservas (bookings), clientes e referências importadas do Zappy.
     * Serviços do catálogo mantêm-se. Clientes do CRM sem ref Zappy não são apagados.
     *
     * @return array<string, int>
     */
    public function purgeImportedData(
        int $storeId,
        bool $dryRun,
        bool $purgeClients = true,
        bool $purgeCatalog = false,
    ): array {
        if (! Store::query()->whereKey($storeId)->exists()) {
            throw new \InvalidArgumentException("Loja #{$storeId} não existe.");
        }

        $this->storeId = $storeId;
        $this->dryRun = $dryRun;

        $eventIds = $this->collectImportedCalendarEventIds($storeId);
        $saleIds = $this->collectImportedSaleIds($storeId, $eventIds);
        $clientIds = $purgeClients ? $this->collectImportedClientIds($storeId) : [];
        $deletableClientIds = $purgeClients
            ? $this->filterDeletableImportedClientIds($storeId, $clientIds, $eventIds, $saleIds)
            : [];
        $bookingIds = $this->collectImportedBookingIds($storeId, $eventIds, $clientIds);

        $importedServiceIds = $purgeCatalog ? $this->collectImportedServiceIds($storeId) : [];
        $catalogStats = $purgeCatalog
            ? $this->countCatalogPurgeTargets($storeId, $importedServiceIds)
            : ['services' => 0, 'extras' => 0, 'fees' => 0];

        $stats = [
            'calendar_events' => count($eventIds),
            'sales' => count($saleIds),
            'sale_items' => $saleIds !== []
                ? (int) SaleItem::query()->whereIn('sale_id', $saleIds)->count()
                : 0,
            'clients_candidates' => count($clientIds),
            'clients' => count($deletableClientIds),
            'clients_skipped' => max(0, count($clientIds) - count($deletableClientIds)),
            'bookings_deleted' => count($bookingIds),
            'wallet_tx_unlinked' => 0,
            'wallet_tx_deleted' => $deletableClientIds !== []
                ? (int) ClientWalletTransaction::query()->whereIn('client_id', $deletableClientIds)->count()
                : 0,
            'zappy_refs' => (int) ZappyImportRef::query()->where('store_id', $storeId)->count(),
            'services' => $catalogStats['services'],
            'extras' => $catalogStats['extras'],
            'fees' => $catalogStats['fees'],
        ];

        if ($dryRun) {
            return $stats;
        }

        DB::transaction(function () use ($storeId, $eventIds, $saleIds, $deletableClientIds, $bookingIds, $purgeCatalog, $importedServiceIds, &$stats): void {
            if ($bookingIds !== []) {
                Booking::query()
                    ->where('store_id', $storeId)
                    ->whereIn('id', $bookingIds)
                    ->delete();
            }

            if ($eventIds !== []) {
                $stats['wallet_tx_unlinked'] = ClientWalletTransaction::query()
                    ->whereIn('calendar_event_id', $eventIds)
                    ->update(['calendar_event_id' => null]);
            }

            if ($saleIds !== []) {
                SaleItem::query()->whereIn('sale_id', $saleIds)->delete();
                Sale::query()
                    ->where('store_id', $storeId)
                    ->whereIn('id', $saleIds)
                    ->delete();
            }

            if ($eventIds !== []) {
                CalendarEvent::withoutEvents(function () use ($eventIds): void {
                    CalendarEvent::query()->whereIn('id', $eventIds)->delete();
                });
            }

            if ($deletableClientIds !== []) {
                DB::table('users')
                    ->whereIn('client_id', $deletableClientIds)
                    ->update(['client_id' => null]);

                ClientWalletTransaction::query()
                    ->whereIn('client_id', $deletableClientIds)
                    ->delete();

                Client::withoutEvents(function () use ($storeId, $deletableClientIds): void {
                    Client::query()
                        ->where('store_id', $storeId)
                        ->whereIn('id', $deletableClientIds)
                        ->delete();
                });
            }

            if ($purgeCatalog) {
                $this->purgeCatalogForStore($storeId, $importedServiceIds);
            }

            ZappyImportRef::query()->where('store_id', $storeId)->delete();
        });

        return $stats;
    }

    /**
     * Serviços criados pelo import Zappy (ref TYPE_SERVICE). Serviços manuais sem ref mantêm-se.
     *
     * @return list<int>
     */
    private function collectImportedServiceIds(int $storeId): array
    {
        return ZappyImportRef::query()
            ->where('store_id', $storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SERVICE)
            ->pluck('local_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $importedServiceIds
     * @return array{services: int, extras: int, fees: int}
     */
    private function countCatalogPurgeTargets(int $storeId, array $importedServiceIds): array
    {
        $extraCategoryIds = DB::table('extra_categories')
            ->where('store_id', $storeId)
            ->pluck('id');

        return [
            'services' => count($importedServiceIds),
            'extras' => $extraCategoryIds->isEmpty()
                ? 0
                : (int) DB::table('extras')->whereIn('extra_category_id', $extraCategoryIds)->count(),
            'fees' => (int) DB::table('fees')->where('store_id', $storeId)->count(),
        ];
    }

    /**
     * @param  list<int>  $importedServiceIds
     */
    private function purgeCatalogForStore(int $storeId, array $importedServiceIds): void
    {
        if ($importedServiceIds !== []) {
            DB::table('agent_service')->whereIn('service_id', $importedServiceIds)->delete();
            if (Schema::hasTable('service_fee')) {
                DB::table('service_fee')->whereIn('service_id', $importedServiceIds)->delete();
            }
            if (Schema::hasTable('service_options')) {
                DB::table('service_options')->whereIn('service_id', $importedServiceIds)->delete();
            }
            DB::table('service_extra')->whereIn('service_id', $importedServiceIds)->delete();
            Service::withoutEvents(fn () => Service::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $importedServiceIds)
                ->delete());
        }

        $extraCategoryIds = DB::table('extra_categories')
            ->where('store_id', $storeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($extraCategoryIds !== []) {
            DB::table('extras')->whereIn('extra_category_id', $extraCategoryIds)->delete();
            DB::table('extra_categories')->where('store_id', $storeId)->delete();
        }

        DB::table('fees')->where('store_id', $storeId)->delete();
    }

    /**
     * @return list<int>
     */
    private function collectImportedCalendarEventIds(int $storeId): array
    {
        $fromRefs = ZappyImportRef::query()
            ->where('store_id', $storeId)
            ->whereIn('entity_type', [
                ZappyImportRef::TYPE_APPOINTMENT,
                ZappyImportRef::TYPE_APPOINTMENT_ZAPPY,
            ])
            ->pluck('local_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        $fromMarker = CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('description', 'like', '[Importado Zappy]%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($fromRefs, $fromMarker)));
    }

    /**
     * @param  list<int>  $eventIds
     * @return list<int>
     */
    private function collectImportedSaleIds(int $storeId, array $eventIds): array
    {
        $ids = ZappyImportRef::query()
            ->where('store_id', $storeId)
            ->where('entity_type', ZappyImportRef::TYPE_SALE)
            ->pluck('local_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        if ($eventIds !== []) {
            $ids = array_merge(
                $ids,
                Sale::query()
                    ->where('store_id', $storeId)
                    ->whereIn('calendar_event_id', $eventIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
        }

        $ids = array_merge(
            $ids,
            Sale::query()
                ->where('store_id', $storeId)
                ->where('numero_fatura', 'like', 'ZAPPY-%')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        $ids = array_merge(
            $ids,
            Sale::query()
                ->where('store_id', $storeId)
                ->where('issue_without_fiscal_id', true)
                ->where('numero_fatura', 'like', '%@%')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /**
     * Clientes criados ou registados na importação Zappy (ref TYPE_CLIENT), incluindo placeholders.
     *
     * @return list<int>
     */
    private function collectImportedClientIds(int $storeId): array
    {
        return ZappyImportRef::query()
            ->where('store_id', $storeId)
            ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
            ->pluck('local_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Reservas (sinal receção / checkout) ligadas a eventos ou clientes do import Zappy.
     *
     * @param  list<int>  $eventIds
     * @param  list<int>  $clientIds
     * @return list<int>
     */
    private function collectImportedBookingIds(int $storeId, array $eventIds, array $clientIds): array
    {
        if ($eventIds === [] && $clientIds === []) {
            return [];
        }

        $query = Booking::query()->where('store_id', $storeId);

        $query->where(function ($q) use ($eventIds, $clientIds): void {
            $added = false;
            if ($eventIds !== []) {
                $q->whereIn('calendar_event_id', $eventIds);
                $added = true;
            }
            if ($clientIds !== []) {
                if ($added) {
                    $q->orWhereIn('client_id', $clientIds);
                } else {
                    $q->whereIn('client_id', $clientIds);
                }
            }
        });

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Só apaga clientes importados do Zappy sem marcações/vendas fora do âmbito do purge.
     *
     * @param  list<int>  $clientIds
     * @param  list<int>  $eventIds
     * @param  list<int>  $saleIds
     * @return list<int>
     */
    private function filterDeletableImportedClientIds(
        int $storeId,
        array $clientIds,
        array $eventIds,
        array $saleIds,
    ): array {
        $deletable = [];

        foreach ($clientIds as $clientId) {
            $hasOtherEvents = CalendarEvent::query()
                ->where('store_id', $storeId)
                ->where('client_id', $clientId)
                ->when($eventIds !== [], fn ($q) => $q->whereNotIn('id', $eventIds))
                ->exists();

            if ($hasOtherEvents) {
                continue;
            }

            $hasOtherSales = Sale::query()
                ->where('store_id', $storeId)
                ->where('client_id', $clientId)
                ->when($saleIds !== [], fn ($q) => $q->whereNotIn('id', $saleIds))
                ->exists();

            if ($hasOtherSales) {
                continue;
            }

            $deletable[] = $clientId;
        }

        return $deletable;
    }
}
