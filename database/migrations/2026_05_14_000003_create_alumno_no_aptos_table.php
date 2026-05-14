<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_no_aptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('grupo_formativo_id')->constrained('grupos_formativos')->cascadeOnDelete();
            $table->foreignId('grupo_formativo_alumno_id')->nullable()->constrained('grupo_formativo_alumno')->nullOnDelete();
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->decimal('nota_total', 7, 2)->nullable();
            $table->decimal('nota_max', 7, 2)->nullable();
            $table->date('fecha_fin_curso');
            $table->timestamp('fecha_deteccion')->useCurrent();
            // Estado del ciclo de reinicio
            $table->enum('reinicio_estado', [
                'pendiente',    // detectado pero aún no se ofreció
                'ofrecido',     // se le envió al menos 1 email
                'aceptado',     // admin recibió el email del alumno y lo registró
                'reiniciado',   // admin creó el nuevo grupo
                'caducado',     // pasaron 30 días sin aceptar
                'rechazado',    // admin marca como no aplicable
            ])->default('pendiente');
            $table->timestamp('reinicio_aceptado_at')->nullable();
            $table->timestamp('reinicio_cerrado_at')->nullable();
            $table->foreignId('reinicio_cerrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['alumno_id', 'grupo_formativo_id'], 'alumno_no_apto_unique');
            $table->index('reinicio_estado');
            $table->index('fecha_fin_curso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_no_aptos');
    }
};
