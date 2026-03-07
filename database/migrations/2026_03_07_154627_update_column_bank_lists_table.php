<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregamos la columna para el log del error si no la tienes
        Schema::table('bank_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_lists', 'error_log')) {
                $table->json('error_log')->nullable()->after('processed_text');
            }
            $table->enum('status', ['pending', 'approved', 'denied', 'error'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('bank_lists', function (Blueprint $table) {
            // Volvemos al estado original si se hace rollback
            $table->enum('status', ['pending', 'approved', 'denied'])
                ->default('pending')
                ->change();
            $table->dropColumn('error_log');
        });
    }
};
