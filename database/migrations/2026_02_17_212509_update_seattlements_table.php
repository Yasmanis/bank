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
        // Bloque 1: Limpieza de lo anterior
        Schema::table('settlements', function (Blueprint $table) {
            // 1. Eliminamos la llave foránea (Laravel por defecto la nombra: tabla_columna_foreign)
            $table->dropForeign(['user_id']);

            // 2. Ahora que user_id no tiene FK activa, podemos borrar el índice
            $table->dropUnique(['user_id', 'date', 'hourly']);
        });

        // Bloque 2: Nuevas columnas e índices
        Schema::table('settlements', function (Blueprint $table) {
            // 3. Agregamos bank_id (lo pongo nullable por si ya tienes datos en la tabla)
            $table->foreignId('bank_id')->after('user_id')->constrained()->onDelete('cascade');

            // 4. Volvemos a crear la relación foránea de user_id
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // 5. Creamos el nuevo índice único que ahora incluye el banco
            $table->unique(['user_id', 'bank_id', 'date', 'hourly'], 'user_bank_settlement_unique');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique('user_bank_settlement_unique');
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');

            // Restaurar el estado original si haces rollback
            $table->unique(['user_id', 'date', 'hourly']);
        });
    }
};
