<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice inverso "email → cursos de Moodle en los que estuvo el usuario".
 *
 * Se puebla con `encuestas-calidad:snapshot-moodle` recorriendo los cursos de
 * Moodle con `core_enrol_get_enrolled_users(..., onlyactive=0)`, que —a diferencia
 * de `core_enrol_get_users_courses`— SÍ incluye las matrículas caducadas (alumnos
 * que ya terminaron el curso). Es la única vía por webservice, sin tocar la BD de
 * Moodle, para resolver el curso de las encuestas de calidad de alumnos que no
 * tienen ficha/curso en el Panel.
 *
 * La tabla se reemplaza entera en cada snapshot (truncate + insert por lotes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moodle_matricula_index', function (Blueprint $table) {
            $table->id();

            $table->string('email', 191)->index();          // = username del alumno en Moodle
            $table->unsignedBigInteger('moodle_course_id');
            $table->string('curso_fullname', 255);
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->date('curso_startdate')->nullable();
            $table->date('curso_enddate')->nullable();
            $table->date('ultimo_acceso')->nullable(); // lastcourseaccess del alumno a ESE curso (señal fechada precisa)
            $table->timestamp('capturado_en')->nullable();

            $table->index(['email', 'moodle_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moodle_matricula_index');
    }
};
