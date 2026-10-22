<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excepción por grupo a la regla "un alumno no puede estar en dos grupos con
 * fechas solapadas". Se marca a mano en casos puntuales (ej. Drivania, Claude
 * Code Avanzado Agentes 24/10–31/12/2026). Por defecto false: los grupos
 * existentes siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->boolean('permite_solapamiento')->default(false)->after('gestion_externa');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->dropColumn('permite_solapamiento');
        });
    }
};
