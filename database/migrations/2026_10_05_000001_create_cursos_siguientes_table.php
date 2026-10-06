<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Curso siguiente": qué curso del catálogo ofrecer a quien terminó (y valoró
 * bien) un curso. Lo mantiene la usuaria a mano, con propuestas automáticas por
 * nombre que debe confirmar.
 *
 * El origen se guarda como CLAVE de nombre normalizada (no como id) porque es lo
 * único que comparten todas las fuentes de encuestas: Form, aula, legacy y las
 * copias del curso por tutor en Moodle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_siguientes', function (Blueprint $table) {
            $table->id();
            $table->string('curso_origen_clave', 191)->index();
            $table->string('curso_origen_nombre');
            $table->foreignId('moodle_curso_id')->constrained('moodle_cursos')->cascadeOnDelete();
            $table->unsignedTinyInteger('prioridad')->default(1);
            $table->boolean('activo')->default(true);
            $table->boolean('sugerido_auto')->default(false);
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->unique(['curso_origen_clave', 'moodle_curso_id']);
        });

        // Enlace a la ficha del curso en webcurso.es/courses, para que el equipo
        // pueda mandarlo al alumno. Se rellena a mano desde la pantalla.
        Schema::table('moodle_cursos', function (Blueprint $table) {
            $table->string('url')->nullable()->after('horas');
        });
    }

    public function down(): void
    {
        Schema::table('moodle_cursos', function (Blueprint $table) {
            $table->dropColumn('url');
        });
        Schema::dropIfExists('cursos_siguientes');
    }
};
