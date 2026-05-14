<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_progreso_moodle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('grupo_formativo_alumno_id')->constrained('grupo_formativo_alumno')->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->unsignedInteger('lastaccess_global')->nullable();
            $table->unsignedInteger('lastaccess_curso')->nullable();
            $table->boolean('nunca_entro_sitio')->default(false);
            $table->boolean('nunca_entro_curso')->default(false);
            $table->decimal('progress', 5, 2)->nullable();
            $table->boolean('completed')->nullable();
            $table->date('fecha_snapshot');
            $table->timestamp('ultimo_refresh_at')->nullable();
            $table->enum('fuente', ['cron', 'manual'])->default('cron');
            $table->timestamps();

            $table->unique(['grupo_formativo_alumno_id', 'fecha_snapshot'], 'alumno_progreso_pivot_fecha_unique');
            $table->index('fecha_snapshot');
            $table->index(['alumno_id', 'fecha_snapshot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_progreso_moodle');
    }
};
