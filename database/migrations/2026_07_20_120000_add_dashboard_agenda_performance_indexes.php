<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes para Dashboard Resumo + Agenda:
 * - calendar_events filtrados por loja + tipo + intervalo de datas
 * - sales pagas por loja
 * - bookings por evento + payment_status (ApplicableFees / pipeline)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_events')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                if (! Schema::hasIndex('calendar_events', 'calendar_events_store_type_start_idx')) {
                    $table->index(
                        ['store_id', 'event_type', 'start_at'],
                        'calendar_events_store_type_start_idx'
                    );
                }
                if (! Schema::hasIndex('calendar_events', 'calendar_events_store_end_start_idx')) {
                    $table->index(
                        ['store_id', 'end_at', 'start_at'],
                        'calendar_events_store_end_start_idx'
                    );
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table): void {
                if (! Schema::hasIndex('sales', 'sales_store_status_idx')) {
                    $table->index(['store_id', 'status'], 'sales_store_status_idx');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (! Schema::hasIndex('bookings', 'bookings_event_payment_status_idx')) {
                    $table->index(
                        ['calendar_event_id', 'payment_status'],
                        'bookings_event_payment_status_idx'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('calendar_events')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                if (Schema::hasIndex('calendar_events', 'calendar_events_store_type_start_idx')) {
                    $table->dropIndex('calendar_events_store_type_start_idx');
                }
                if (Schema::hasIndex('calendar_events', 'calendar_events_store_end_start_idx')) {
                    $table->dropIndex('calendar_events_store_end_start_idx');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table): void {
                if (Schema::hasIndex('sales', 'sales_store_status_idx')) {
                    $table->dropIndex('sales_store_status_idx');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (Schema::hasIndex('bookings', 'bookings_event_payment_status_idx')) {
                    $table->dropIndex('bookings_event_payment_status_idx');
                }
            });
        }
    }
};
