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
        Schema::create('admin_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');

            // Valores de pago (Multiplicadores)
            $table->integer('fixed');    // Pago por cada $1 en Fijo
            $table->integer('hundred'); // Pago por cada $1 en Centena
            $table->integer('parlet'); // Pago por cada $1 en Parlet
            $table->integer('runner1');  // Pago por cada $1 en Corrida 1
            $table->integer('runner2');  // Pago por cada $1 en Corrida 2
            $table->integer('triplet'); //
            // Porcentaje de comisión por defecto para sus usuarios
            $table->decimal('default_commission', 5, 2)->default(25.00);

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_configs');
    }
};
