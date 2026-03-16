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
        Schema::table('bank_lists', function (Blueprint $table) {
            $table->json('manual_results')->nullable()->after('error_log');
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_lists', function (Blueprint $table) {
            $table->dropColumn('manual_results');
            $table->dropColumn('validated_by');
            $table->dropColumn('validated_at');
        });
    }
};
