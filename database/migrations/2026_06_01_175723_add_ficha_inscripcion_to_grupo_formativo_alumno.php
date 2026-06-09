<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupo_formativo_alumno', function (Blueprint $table) {
            $table->string('ficha_inscripcion_path')->nullable()->after('ultimo_error_moodle');
            $table->string('ficha_inscripcion_tipo', 20)->nullable()->after('ficha_inscripcion_path');
            $table->timestamp('ficha_inscripcion_subida_en')->nullable()->after('ficha_inscripcion_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('grupo_formativo_alumno', function (Blueprint $table) {
            $table->dropColumn(['ficha_inscripcion_path', 'ficha_inscripcion_tipo', 'ficha_inscripcion_subida_en']);
        });
    }
};
