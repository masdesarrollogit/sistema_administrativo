<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_calificaciones_moodle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_progreso_moodle_id')->constrained('alumno_progreso_moodle')->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_grade_item_id')->nullable();
            $table->string('itemname');
            $table->string('itemtype', 30)->nullable();   // mod | course | category | manual
            $table->string('itemmodule', 30)->nullable(); // quiz | assign | scorm ...
            $table->decimal('grade', 7, 2)->nullable();
            $table->decimal('grademax', 7, 2)->nullable();
            $table->decimal('grademin', 7, 2)->nullable();
            $table->string('percentageformatted', 20)->nullable();
            $table->string('lettergrade', 10)->nullable();
            $table->boolean('is_course_total')->default(false);
            $table->boolean('is_final_quiz')->default(false);
            $table->unsignedInteger('graded_at')->nullable();  // timestamp Unix de cuando se calificó
            $table->timestamps();

            $table->index(['alumno_progreso_moodle_id', 'is_course_total'], 'apm_total_idx');
            $table->index(['alumno_progreso_moodle_id', 'is_final_quiz'], 'apm_quiz_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_calificaciones_moodle');
    }
};
