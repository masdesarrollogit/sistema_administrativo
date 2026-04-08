<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas_autonomas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('accion_formativa_id')->constrained('acciones_formativas')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutores')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->string('moodle_username')->nullable();
            $table->enum('estado', ['pendiente', 'matriculado', 'error'])->default('pendiente');
            $table->unsignedTinyInteger('intentos_moodle')->default(0);
            $table->text('ultimo_error_moodle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas_autonomas');
    }
};
