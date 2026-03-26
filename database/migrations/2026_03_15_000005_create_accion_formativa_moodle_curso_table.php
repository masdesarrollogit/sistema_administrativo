<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accion_formativa_id')->constrained('acciones_formativas')->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_course_id');
            $table->string('moodle_fullname');
            $table->enum('tipo', ['plantilla', 'tutor', 'repaso', 'desactualizado']);
            $table->foreignId('tutor_id')->nullable()->constrained('tutores')->nullOnDelete();
            $table->string('idnumber_moodle')->nullable();
            $table->timestamps();

            $table->unique(['accion_formativa_id', 'moodle_course_id'], 'af_moodle_curso_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accion_formativa_moodle_curso');
    }
};
