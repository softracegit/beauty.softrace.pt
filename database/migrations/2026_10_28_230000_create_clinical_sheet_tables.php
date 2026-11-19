<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_clinical_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->string('input_type', 32); // text|textarea|select|multiselect|number|bool
            $table->json('options')->nullable();
            $table->string('applies_to', 32)->default('both'); // session|preference|both
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
            $table->index(['category_id', 'is_active', 'sort_order']);
        });

        Schema::create('client_clinical_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'category_id', 'field_key'], 'client_clinical_prefs_unique');
            $table->index(['client_id', 'category_id']);
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->json('clinical_sheet')->nullable()->after('technical_notes');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('clinical_sheet');
        });
        Schema::dropIfExists('client_clinical_preferences');
        Schema::dropIfExists('category_clinical_fields');
    }
};
