<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->boolean('apto_sin_examen')->default(false)->after('pre_cierre');
            $table->index('apto_sin_examen');
        });
    }

    public function down(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropIndex(['apto_sin_examen']);
            $table->dropColumn('apto_sin_examen');
        });
    }
};
