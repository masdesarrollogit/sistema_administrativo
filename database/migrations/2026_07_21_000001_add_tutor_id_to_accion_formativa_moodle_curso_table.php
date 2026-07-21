<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devuelve tutor_id al pivot para saber a que tutor pertenece cada aula de Moodle.
 *
 * La columna existio en la migracion original y la elimino
 * 2026_03_18_184757_refactor_accion_formativa_moodle_curso_remove_tutor, pero
 * ningun codigo llego a usarla. Sin ella, la unica forma de resolver "que aula
 * es de este tutor" era consultar Moodle en vivo, que falla cuando la matricula
 * del tutor caduca.
 *
 * Se deja nullable y sin backfill: la deteccion por rol de profesor la rellena
 * la primera vez que se matricula (ver MatriculacionPanel::resolverAulaMoodle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->foreignId('tutor_id')->nullable()->after('accion_formativa_id')
                ->constrained('tutores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->dropColumn('tutor_id');
        });
    }
};
