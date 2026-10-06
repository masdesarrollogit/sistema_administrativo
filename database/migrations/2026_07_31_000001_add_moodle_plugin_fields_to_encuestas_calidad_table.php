<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tercera vía de entrada de encuestas: el plugin mod_calidadfundae de Moodle.
 *
 * El alumno responde dentro del aula el cuestionario oficial FUNDAE ÍNTEGRO (24
 * ítems), mientras que el Microsoft Form solo cubría 19. Esta migración añade
 * hueco para lo que faltaba, más la trazabilidad hacia Moodle.
 *
 * Es puramente aditiva y todo nullable: ni un UPDATE sobre las filas existentes.
 * Las respuestas del Form quedan intactas y sus medias no se mueven, porque
 * AVG() ignora los NULL de las columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            // ── Ítems de escala 1-4 que el Form no preguntaba ─────────────────
            // Se sigue la numeración item_NN a propósito: toda la maquinaria de
            // medias del Panel agrega por AVG(item_NN) y config('...bloques')
            // indexa por ese nombre de columna.
            $table->unsignedTinyInteger('item_20')->nullable()->after('item_19');  // 2.2
            $table->unsignedTinyInteger('item_21')->nullable()->after('item_20');  // 4.1 columna TUTORES
            $table->unsignedTinyInteger('item_22')->nullable()->after('item_21');  // 4.2 columna TUTORES

            // ── Ítems dicotómicos: 1 = Sí, 2 = No ────────────────────────────
            // El prefijo `sino_` es una barrera deliberada. Si se llamasen
            // item_23..25 alguien acabaría metiéndolos en un bloque y un "No"
            // (=2) se promediaría como si fuese un "regular", corrompiendo la
            // media en silencio. El nombre impide el error.
            $table->unsignedTinyInteger('sino_pruebas_evaluacion')->nullable()->after('item_22');   // 8.1
            $table->unsignedTinyInteger('sino_acreditacion')->nullable()->after('sino_pruebas_evaluacion'); // 8.2
            $table->unsignedTinyInteger('sino_recomendaria')->nullable()->index()->after('sino_acreditacion'); // 10.1
            // sino_recomendaria va indexado porque alimenta un KPI de cabecera.

            // ── Trazabilidad Moodle ──────────────────────────────────────────
            $table->unsignedBigInteger('moodle_response_id')->nullable()->index()->after('imported_at');
            // Mismo nombre que en moodle_matricula_index, para que el cruce que
            // resuelve el tutor sea obvio y no haya dos nombres para lo mismo.
            $table->unsignedBigInteger('moodle_course_id')->nullable()->index()->after('moodle_response_id');
            $table->unsignedBigInteger('moodle_user_id')->nullable()->after('moodle_course_id');
            $table->unsignedBigInteger('moodle_cmid')->nullable()->after('moodle_user_id');
            // Timestamp Unix crudo, no datetime: es el valor que se compara con el
            // parámetro `since` del webservice. Convertirlo y reconvertirlo solo
            // añadiría riesgo de deriva por zona horaria en el cursor.
            $table->unsignedInteger('moodle_timemodified')->nullable()->index()->after('moodle_cmid');
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->dropColumn([
                'item_20', 'item_21', 'item_22',
                'sino_pruebas_evaluacion', 'sino_acreditacion', 'sino_recomendaria',
                'moodle_response_id', 'moodle_course_id', 'moodle_user_id',
                'moodle_cmid', 'moodle_timemodified',
            ]);
        });
    }
};
