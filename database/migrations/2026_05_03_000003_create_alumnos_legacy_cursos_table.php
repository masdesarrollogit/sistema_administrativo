<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos_legacy_cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nif', 20)->index();
            $table->unsignedInteger('course_id')->nullable();
            $table->string('curso_titulo', 255)->nullable();
            $table->string('curso_short_name', 100)->nullable();
            $table->unsignedSmallInteger('curso_horas')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado_curso', 20)->nullable();
            $table->string('resultado', 30)->nullable();
            $table->string('formation_group_alpha', 20)->nullable();
            $table->string('formation_group_number', 20)->nullable();
            $table->string('legacy_company_text', 255)->nullable();
            $table->string('legacy_cif_resuelto', 20)->nullable();
            $table->unsignedInteger('source_mc_id')->nullable();
            $table->unsignedInteger('source_mem_id')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            $table->unique(['nif', 'source_mc_id'], 'alumnos_legacy_cursos_nif_mc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos_legacy_cursos');
    }
};
