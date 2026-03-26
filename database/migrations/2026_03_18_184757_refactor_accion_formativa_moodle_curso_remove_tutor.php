<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el enum para incluir 'activa' junto a 'tutor'
        DB::statement("ALTER TABLE accion_formativa_moodle_curso MODIFY tipo ENUM('plantilla','tutor','activa','repaso','desactualizado') NOT NULL");

        // 2. Renombrar tipo 'tutor' → 'activa' en los datos existentes
        DB::table('accion_formativa_moodle_curso')
            ->where('tipo', 'tutor')
            ->update(['tipo' => 'activa']);

        // 3. Quitar columnas obsoletas
        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->dropColumn('tutor_id');
            $table->dropColumn('idnumber_moodle');
        });

        // 4. Redefinir el enum final sin 'tutor'
        DB::statement("ALTER TABLE accion_formativa_moodle_curso MODIFY tipo ENUM('plantilla','activa','repaso','desactualizado') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accion_formativa_moodle_curso MODIFY tipo ENUM('plantilla','tutor','repaso','desactualizado') NOT NULL");

        Schema::table('accion_formativa_moodle_curso', function (Blueprint $table) {
            $table->foreignId('tutor_id')->nullable()->constrained('tutores')->nullOnDelete();
            $table->string('idnumber_moodle')->nullable();
        });

        DB::table('accion_formativa_moodle_curso')
            ->where('tipo', 'activa')
            ->update(['tipo' => 'tutor']);
    }
};
