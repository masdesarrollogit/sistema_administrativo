<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización del alumno para publicar su reseña, recogida en el cuestionario
 * del aula (plugin mod_calidadfundae). Por ahora solo se guarda: la publicación
 * (p. ej. en Trustpilot) se hará más adelante y usará estos datos para saber a
 * quién se puede.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->boolean('resena_autorizada')->default(false)->index()->after('oportunidad_en');
            // Ya compuesto según lo que eligió el alumno: "Ana García" / "Ana G." / "Anónimo"
            $table->string('resena_nombre_publico', 120)->nullable()->after('resena_autorizada');
            // Prueba del consentimiento: cuándo se dio (o retiró) y qué texto se aceptó
            $table->timestamp('resena_consentimiento_en')->nullable()->after('resena_nombre_publico');
            $table->string('resena_consentimiento_version', 20)->nullable()->after('resena_consentimiento_en');
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            $table->dropIndex(['resena_autorizada']);
            $table->dropColumn(['resena_autorizada', 'resena_nombre_publico', 'resena_consentimiento_en', 'resena_consentimiento_version']);
        });
    }
};
