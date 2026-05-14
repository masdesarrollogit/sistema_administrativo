<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->boolean('aprobado')->default(false)->after('completed');
            $table->boolean('cuestionario_final_realizado')->default(false)->after('aprobado');
        });
    }

    public function down(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropColumn(['aprobado', 'cuestionario_final_realizado']);
        });
    }
};
