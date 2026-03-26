<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('accion_formativa_id')->constrained('acciones_formativas')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutores')->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->unsignedInteger('id_grupo_fundae')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedInteger('horas_totales');
            $table->enum('estado', [
                'pendiente',
                'creando_usuario',
                'matriculando',
                'en_curso',
                'completado',
                'cancelado',
                'error',
            ])->default('pendiente');
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->string('moodle_username')->nullable();
            $table->unsignedTinyInteger('intentos_moodle')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->index(['alumno_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
