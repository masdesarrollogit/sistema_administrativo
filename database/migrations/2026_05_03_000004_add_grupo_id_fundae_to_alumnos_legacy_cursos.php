<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos_legacy_cursos', function (Blueprint $table) {
            $table->string('grupo_id_fundae', 20)->nullable()->after('formation_group_number');
            $table->string('origen_enriquecimiento', 30)->nullable()->after('grupo_id_fundae');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos_legacy_cursos', function (Blueprint $table) {
            $table->dropColumn(['grupo_id_fundae', 'origen_enriquecimiento']);
        });
    }
};
