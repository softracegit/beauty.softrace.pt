<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garante `clients.email` nullable em servidores onde a migração
     * `2026_03_27_120000_make_clients_email_nullable` já consta como executada
     * mas o ficheiro foi alterado depois (Laravel não volta a correr migrações antigas).
     */
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'email')) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $row = $connection->selectOne(
                'select IS_NULLABLE from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ? limit 1',
                [$database, 'clients', 'email']
            );
            if ($row && strtoupper((string) $row->IS_NULLABLE) === 'NO') {
                DB::statement('ALTER TABLE `clients` MODIFY `email` VARCHAR(255) NULL');
            }

            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Não força NOT NULL (pode haver NULLs); revert manual se necessário.
     */
    public function down(): void {}
};
