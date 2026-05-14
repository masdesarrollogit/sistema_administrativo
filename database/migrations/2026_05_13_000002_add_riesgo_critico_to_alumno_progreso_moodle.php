<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->boolean('riesgo_critico')->default(false)->after('inactivo');
            $table->decimal('pct_tiempo_transcurrido', 5, 2)->nullable()->after('riesgo_critico');

            $table->index('riesgo_critico');
        });
    }

    public function down(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropIndex(['riesgo_critico']);
            $table->dropColumn(['riesgo_critico', 'pct_tiempo_transcurrido']);
        });
    }
};
