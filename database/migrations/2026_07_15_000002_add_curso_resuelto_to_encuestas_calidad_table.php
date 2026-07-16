<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curso al que pertenece la calificación, resuelto por:
 *  - Nº Acción/Grupo del formulario (cuando viene), o
 *  - la fecha de cumplimentación contra las fechas de los cursos del alumno
 *    (inicio <= fecha <= fin), desempatando por fin más cercano + texto.
 *
 * Se guarda el nombre/tipo/fechas del curso resuelto para poder mostrarlo en el
 * listado aunque no exista un GrupoFormativo (casos legacy/bonificado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->string('curso_resuelto', 255)->nullable()->after('denominacion_accion');
            $table->string('curso_tipo', 20)->nullable()->after('curso_resuelto');       // fundae|autonomo|bonificado|legacy
            $table->date('curso_fecha_inicio')->nullable()->after('curso_tipo');
            $table->date('curso_fecha_fin')->nullable()->after('curso_fecha_inicio');
            $table->string('curso_origen', 20)->nullable()->after('curso_fecha_fin');     // numero_accion|fecha_alumno|ambiguo
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->dropColumn(['curso_resuelto', 'curso_tipo', 'curso_fecha_inicio', 'curso_fecha_fin', 'curso_origen']);
        });
    }
};
