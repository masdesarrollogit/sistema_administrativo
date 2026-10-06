<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento comercial de la oportunidad "ofrecer el curso siguiente" que nace
 * de una encuesta bien valorada. Vive en la propia encuesta porque la
 * oportunidad es por (alumno, curso hecho), que es justo lo que es una encuesta.
 * NULL en oportunidad_estado = pendiente (aún nadie la ha tocado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->string('oportunidad_estado', 20)->nullable()->index()->after('moodle_timemodified');
            $table->text('oportunidad_nota')->nullable()->after('oportunidad_estado');
            $table->foreignId('oportunidad_por')->nullable()->after('oportunidad_nota')->constrained('users')->nullOnDelete();
            $table->timestamp('oportunidad_en')->nullable()->after('oportunidad_por');
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oportunidad_por');
            $table->dropColumn(['oportunidad_estado', 'oportunidad_nota', 'oportunidad_en']);
        });
    }
};
