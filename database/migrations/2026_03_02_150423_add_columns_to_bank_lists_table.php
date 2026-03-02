<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_lists', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('id');
            $table->timestamp('client_created_at')->nullable()->after('created_at');
            $table->unique(['user_id', 'client_uuid'], 'user_client_list_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_lists', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn(['client_uuid', 'client_created_at']);
        });
    }
};
