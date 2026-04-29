<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dupEmail = DB::table('clients')
            ->selectRaw('LOWER(TRIM(email)) as e, COUNT(*) as total')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('e')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($dupEmail) {
            throw new RuntimeException('Existem emails duplicados em clients. Limpe os dados antes de aplicar índice único.');
        }

        $dupPhone = DB::table('clients')
            ->selectRaw('TRIM(phone) as p, COUNT(*) as total')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('p')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($dupPhone) {
            throw new RuntimeException('Existem telemóveis duplicados em clients. Limpe os dados antes de aplicar índice único.');
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique('email', 'clients_email_unique');
            $table->unique('phone', 'clients_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_email_unique');
            $table->dropUnique('clients_phone_unique');
        });
    }
};
