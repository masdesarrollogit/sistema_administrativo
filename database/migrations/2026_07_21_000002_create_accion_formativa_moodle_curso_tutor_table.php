<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un aula de Moodle la pueden impartir varios tutores.
 *
 * La columna tutor_id anterior solo permitia uno, pero en la practica 7 de las
 * aulas vinculadas estan compartidas (p.ej. "Power Bi Inicial 40 horas" la dan
 * Raquel y Alvaro). Con una sola columna, el segundo tutor resolvia bien pero
 * consultando Moodle cada vez, y la pantalla mostraba un unico tutor.
 *
 * Se sustituye por una tabla de relacion y se conservan los tutor_id ya
 * detectados como primera fila de cada aula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accion_formativa_moodle_curso_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accion_formativa_moodle_curso_id')
                ->constrained('accion_formativa_moodle_curso', indexName: 'afmc_tutor_vinculo_fk')
                ->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['accion_formativa_moodle_curso_id', 'tutor_id'], 'afmc_tutor_unico');
        });

        // Conservar lo ya detectado antes de eliminar la columna.
        $vinculos = DB::table('accion_formativa_moodle_curso')
            ->whereNotNull('tutor_id')
            ->get(['id', 'tutor_id']);

        foreach ($vinculos as $v) {
            DB::table('accion_formativa_moodle_curso_tutor')->insert([
                'accion_formativa_moodle_curso_id' => $v->id,
                'tutor_id'                         => $v->tutor_id,
                'created_at'                       => now(),
                'updated_at'                       => now(),
            ]);
        }

        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->dropColumn('tutor_id');
        });
    }

    public function down(): void
    {
        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->foreignId('tutor_id')->nullable()->after('accion_formativa_id')
                ->constrained('tutores')->nullOnDelete();
        });

        // Al volver a una sola columna solo cabe un tutor por aula: se queda el primero.
        $filas = DB::table('accion_formativa_moodle_curso_tutor')
            ->orderBy('id')
            ->get(['accion_formativa_moodle_curso_id', 'tutor_id']);

        foreach ($filas as $f) {
            DB::table('accion_formativa_moodle_curso')
                ->where('id', $f->accion_formativa_moodle_curso_id)
                ->whereNull('tutor_id')
                ->update(['tutor_id' => $f->tutor_id]);
        }

        Schema::dropIfExists('accion_formativa_moodle_curso_tutor');
    }
};
