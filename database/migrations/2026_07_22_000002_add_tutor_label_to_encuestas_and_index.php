<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación de encuestas por tutor a partir del profesor real del curso en
 * Moodle. Como Álvaro Pino y Raquel García COMPARTEN aula, a nivel de curso solo
 * se pueden separar "David Guerra" (aula propia) del bucket conjunto
 * "Álvaro Pino / Raquel García". Por eso es una etiqueta de texto, no un tutor_id
 * (que apunta a un único tutor y no representa el aula compartida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->string('tutor_label', 80)->nullable()->after('tutor_id')->index();
        });

        Schema::table('moodle_matricula_index', function (Blueprint $table) {
            $table->string('tutor_label', 80)->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->dropColumn('tutor_label');
        });
        Schema::table('moodle_matricula_index', function (Blueprint $table) {
            $table->dropColumn('tutor_label');
        });
    }
};
