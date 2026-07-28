<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `matriculas_autonomas` pasa a cubrir toda matrícula individual (sin grupo FUNDAE):
     *  - autonomo_2x1: curso gratis que acompaña a un bonificado
     *  - particular:   el alumno paga el curso; no tiene empresa asociada
     *
     * Se usa string en lugar de enum para no depender de MODIFY COLUMN ENUM,
     * que no es portable a SQLite (CI).
     */
    public function up(): void
    {
        Schema::table('matriculas_autonomas', function (Blueprint $table) {
            $table->string('modalidad', 20)
                ->default('autonomo_2x1')
                ->after('candidato_id')
                ->comment('autonomo_2x1 | particular');

            $table->unsignedBigInteger('empresa_id')->nullable()->change();

            $table->index('modalidad');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas_autonomas', function (Blueprint $table) {
            $table->dropIndex(['modalidad']);
            $table->dropColumn('modalidad');
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });
    }
};
