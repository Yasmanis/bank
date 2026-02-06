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
        Schema::create('daily_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('hundred', 1);
            $table->string('fixed', 2);
            $table->string('runner1', 2);
            $table->string('runner2', 2);

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->enum('hourly', ['am', 'pm']); // Usando el estándar que definimos
            $table->date('date');

            // Evita duplicados: No puede haber dos registros para la misma fecha y hora
            $table->unique(['date', 'hourly']);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_numbers');
    }
};
