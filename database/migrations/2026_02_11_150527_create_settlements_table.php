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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('daily_number_id')->constrained();
            $table->date('date');
            $table->enum('hourly', ['am', 'pm']);

            // Totales
            $table->decimal('total_sales', 12, 2);
            $table->decimal('commission_amt', 12, 2);
            $table->decimal('prizes_amt', 12, 2);
            $table->decimal('net_result', 12, 2); // (Sales - Commission) - Prizes

            // Snapshot de lo que se usó (por si cambian los precios mañana)
            $table->json('applied_rates');
            $table->json('prizes_breakdown');

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Seguridad: No se puede liquidar dos veces el mismo turno para el mismo usuario
            $table->unique(['user_id', 'date', 'hourly']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');

    }
};
