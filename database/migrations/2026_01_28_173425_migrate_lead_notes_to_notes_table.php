<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrar dados de lead_notes para notes
        if (Schema::hasTable('lead_notes')) {
            $leadNotes = DB::table('lead_notes')->get();
            
            foreach ($leadNotes as $leadNote) {
                DB::table('notes')->insert([
                    'notable_type' => \App\Models\Lead::class,
                    'notable_id' => $leadNote->lead_id,
                    'user_id' => $leadNote->user_id,
                    'type' => 'geral', // Todas as notas antigas são do tipo geral
                    'note' => $leadNote->note,
                    'reminder_at' => null,
                    'reminder_advance_minutes' => null,
                    'reminder_sent' => false,
                    'created_at' => $leadNote->created_at,
                    'updated_at' => $leadNote->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter migração - mover notas de leads de volta para lead_notes
        if (Schema::hasTable('notes')) {
            $notes = DB::table('notes')
                ->where('notable_type', \App\Models\Lead::class)
                ->get();
            
            foreach ($notes as $note) {
                DB::table('lead_notes')->insert([
                    'lead_id' => $note->notable_id,
                    'user_id' => $note->user_id,
                    'note' => $note->note,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                ]);
            }
        }
    }
};
