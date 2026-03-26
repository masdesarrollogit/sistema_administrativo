<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar tabla matriculas (concepto incorrecto)
        Schema::dropIfExists('matriculas');

        // 2. Quitar tramo_horario de tutores (pertenece al grupo, no al tutor)
        Schema::table('tutores', function (Blueprint $table) {
            $table->dropColumn('tramo_horario');
        });

        // 3. Crear tabla grupos_formativos (entidad central)
        Schema::create('grupos_formativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('accion_formativa_id')->constrained('acciones_formativas')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutores')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->enum('tramo_horario', ['tramo_1', 'tramo_2']);
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('id_grupo_fundae')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedTinyInteger('jornada_laboral')->default(1);
            $table->enum('estado', [
                'abierto',
                'comunicado',
                'en_curso',
                'completado',
                'cancelado',
            ])->default('abierto');
            $table->timestamps();

            $table->index(['accion_formativa_id', 'id_grupo_fundae']);
        });

        // 4. Crear tabla pivot grupo_formativo_alumno (participantes del grupo)
        Schema::create('grupo_formativo_alumno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_formativo_id')->constrained('grupos_formativos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->string('moodle_username')->nullable();
            $table->enum('estado_moodle', ['pendiente', 'creado', 'matriculado', 'error'])->default('pendiente');
            $table->unsignedTinyInteger('intentos_moodle')->default(0);
            $table->text('ultimo_error_moodle')->nullable();
            $table->timestamps();

            $table->unique(['grupo_formativo_id', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_formativo_alumno');
        Schema::dropIfExists('grupos_formativos');

        Schema::table('tutores', function (Blueprint $table) {
            $table->enum('tramo_horario', ['tramo_1', 'tramo_2'])->after('telefono')->default('tramo_2');
        });

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
            $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->string('moodle_username')->nullable();
            $table->unsignedTinyInteger('intentos_moodle')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
        });
    }
};
