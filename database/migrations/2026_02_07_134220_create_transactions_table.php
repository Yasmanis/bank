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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // 1. Relaciones de Usuario y Auditoría Inicial
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users'); // Quién creó el registro (Admin o Usuario)

            // 2. Datos Financieros
            $table->decimal('amount', 12, 2);
            // income = Dinero que entra al Admin (Recoger)
            // outcome = Dinero que sale del Admin (Dar/Pagar premios)
            $table->enum('type', ['income', 'outcome']);
            $table->string('description');
            $table->date('date');

            // 3. Sistema de Flujo y Validación (Para el crecimiento de la App)
            // Por ahora, como el Admin las crea, el default puede ser 'approved'
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');

            // Quién dio el "visto bueno" final no se va a usar de momento pero en el futuro se puede usar para el crecimiento de la App
            $table->foreignId('actioned_by')->nullable()->constrained('users');
            $table->timestamp('actioned_at')->nullable();

            // Por si el Admin rechaza una solicitud del usuario, se implementará en el futuro
            $table->text('rejection_reason')->nullable();

            // 4. Seguridad y Auditoría Final
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
