<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->boolean('pre_cierre')->default(false)->after('riesgo_critico');
            $table->index('pre_cierre');
        });
    }

    public function down(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropIndex(['pre_cierre']);
            $table->dropColumn('pre_cierre');
        });
    }
};
