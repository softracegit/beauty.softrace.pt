<?php

use App\Support\PhoneDisplay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'organization_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('client_tags', 'organization_id')) {
            Schema::table('client_tags', function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->restrictOnDelete();
            });
        }

        DB::table('clients')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $orgId = DB::table('stores')->where('id', $row->store_id)->value('organization_id');
                    if ($orgId) {
                        DB::table('clients')->where('id', $row->id)->update(['organization_id' => $orgId]);
                    }
                }
            });

        DB::table('client_tags')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $orgId = DB::table('stores')->where('id', $row->store_id)->value('organization_id');
                    if ($orgId) {
                        DB::table('client_tags')->where('id', $row->id)->update(['organization_id' => $orgId]);
                    }
                }
            });

        $this->mergeDuplicateClients();
        $this->mergeDuplicateClientTags();

        // Índice dedicado em store_id — o unique composto (store_id, phone) sustentava a FK.
        if (! $this->indexExists('clients', 'clients_store_id_index')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->index('store_id', 'clients_store_id_index');
            });
        }

        if ($this->indexExists('clients', 'clients_store_email_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_store_email_unique');
            });
        }

        if ($this->indexExists('clients', 'clients_store_phone_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_store_phone_unique');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE clients MODIFY store_id BIGINT UNSIGNED NULL');
        }

        if (! $this->indexExists('clients', 'clients_organization_email_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['organization_id', 'email'], 'clients_organization_email_unique');
            });
        }

        if (! $this->indexExists('clients', 'clients_organization_phone_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['organization_id', 'phone'], 'clients_organization_phone_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('clients', 'clients_organization_email_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_organization_email_unique');
            });
        }
        if ($this->indexExists('clients', 'clients_organization_phone_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_organization_phone_unique');
            });
        }

        if (Schema::hasColumn('clients', 'organization_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        if (Schema::hasColumn('client_tags', 'organization_id')) {
            Schema::table('client_tags', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select('PRAGMA index_list('.$table.')');
            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $indexName]
        );

        return $row !== null;
    }

    private function mergeDuplicateClients(): void
    {
        $orgIds = DB::table('clients')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $clients = DB::table('clients')->where('organization_id', $orgId)->orderBy('id')->get();
            $groups = [];

            foreach ($clients as $client) {
                $keys = [];
                $phone = trim((string) ($client->phone ?? ''));
                if ($phone !== '') {
                    $e164 = PhoneDisplay::toE164($phone) ?? $phone;
                    $keys[] = 'p:'.mb_strtolower($e164);
                }
                $email = trim((string) ($client->email ?? ''));
                if ($email !== '') {
                    $keys[] = 'e:'.mb_strtolower($email);
                }
                if ($keys === []) {
                    continue;
                }
                $canonical = $keys[0];
                foreach ($keys as $k) {
                    if (isset($groups[$k])) {
                        $canonical = $groups[$k];
                        break;
                    }
                }
                foreach ($keys as $k) {
                    $groups[$k] = $canonical;
                }
            }

            $buckets = [];
            foreach ($clients as $client) {
                $phone = trim((string) ($client->phone ?? ''));
                $email = trim((string) ($client->email ?? ''));
                $key = null;
                if ($phone !== '') {
                    $e164 = PhoneDisplay::toE164($phone) ?? $phone;
                    $key = $groups['p:'.mb_strtolower($e164)] ?? null;
                }
                if ($key === null && $email !== '') {
                    $key = $groups['e:'.mb_strtolower($email)] ?? null;
                }
                if ($key === null) {
                    continue;
                }
                $buckets[$key][] = $client;
            }

            foreach ($buckets as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                usort($bucket, function ($a, $b) {
                    $score = function ($c) {
                        $events = DB::table('calendar_events')->where('client_id', $c->id)->count();
                        $filled = 0;
                        foreach (['name', 'email', 'phone', 'nif', 'address'] as $f) {
                            if (trim((string) ($c->{$f} ?? '')) !== '') {
                                $filled++;
                            }
                        }

                        return ($events * 1000) + $filled;
                    };

                    return $score($b) <=> $score($a) ?: $a->id <=> $b->id;
                });

                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    $this->repointClientId((int) $dup->id, (int) $survivor->id);
                    DB::table('clients')->where('id', $dup->id)->delete();
                }
            }
        }
    }

    private function repointClientId(int $fromId, int $toId): void
    {
        $tables = [
            'calendar_events',
            'sales',
            'bookings',
            'sms_messages',
            'client_wallet_transactions',
            'booking_saved_cards',
            'opportunities',
            'deals',
            'users',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            DB::table($table)->where('client_id', $fromId)->update(['client_id' => $toId]);
        }

        if (Schema::hasTable('client_client_tag')) {
            $tagIds = DB::table('client_client_tag')->where('client_id', $fromId)->pluck('client_tag_id');
            foreach ($tagIds as $tagId) {
                $exists = DB::table('client_client_tag')
                    ->where('client_id', $toId)
                    ->where('client_tag_id', $tagId)
                    ->exists();
                if (! $exists) {
                    DB::table('client_client_tag')->insert([
                        'client_id' => $toId,
                        'client_tag_id' => $tagId,
                    ]);
                }
            }
            DB::table('client_client_tag')->where('client_id', $fromId)->delete();
        }

        if (Schema::hasTable('notes')) {
            DB::table('notes')
                ->where('notable_type', 'App\\Models\\Client')
                ->where('notable_id', $fromId)
                ->update(['notable_id' => $toId]);
        }
    }

    private function mergeDuplicateClientTags(): void
    {
        $orgIds = DB::table('client_tags')->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            $tags = DB::table('client_tags')->where('organization_id', $orgId)->orderBy('id')->get();
            $byName = [];
            foreach ($tags as $tag) {
                $key = mb_strtolower(trim((string) $tag->name));
                $byName[$key][] = $tag;
            }
            foreach ($byName as $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                $survivor = $bucket[0];
                foreach (array_slice($bucket, 1) as $dup) {
                    $clientIds = DB::table('client_client_tag')->where('client_tag_id', $dup->id)->pluck('client_id');
                    foreach ($clientIds as $clientId) {
                        $exists = DB::table('client_client_tag')
                            ->where('client_id', $clientId)
                            ->where('client_tag_id', $survivor->id)
                            ->exists();
                        if (! $exists) {
                            DB::table('client_client_tag')->insert([
                                'client_id' => $clientId,
                                'client_tag_id' => $survivor->id,
                            ]);
                        }
                    }
                    DB::table('client_client_tag')->where('client_tag_id', $dup->id)->delete();
                    DB::table('client_tags')->where('id', $dup->id)->delete();
                }
            }
        }
    }
};
