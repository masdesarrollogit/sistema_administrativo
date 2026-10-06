<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El curso siguiente puede ser una ACCIÓN FORMATIVA FUNDAE (los cursos que se
 * imparten de verdad, importados del XLS) o un curso del catálogo web
 * (`moodle_cursos`, CSV de webcurso.es). Exactamente uno de los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos_siguientes', function (Blueprint $table) {
            $table->foreignId('accion_formativa_id')->nullable()->after('moodle_curso_id')
                ->constrained('acciones_formativas')->cascadeOnDelete();
            $table->unique(['curso_origen_clave', 'accion_formativa_id']);
        });

        Schema::table('cursos_siguientes', function (Blueprint $table) {
            $table->unsignedBigInteger('moodle_curso_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cursos_siguientes', function (Blueprint $table) {
            $table->dropUnique(['curso_origen_clave', 'accion_formativa_id']);
            $table->dropConstrainedForeignId('accion_formativa_id');
        });
    }
};
