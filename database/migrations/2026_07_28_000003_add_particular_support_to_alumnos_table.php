<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un alumno particular (paga el curso de su bolsillo) no pertenece a ninguna
     * empresa cliente. `empresa_id` pasa a opcional y se guarda el nombre de su
     * empresa como texto libre, solo para control interno: NO se crea fila en `empresas`.
     */
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();

            $table->string('empresa_texto', 255)
                ->nullable()
                ->after('empresa_id')
                ->comment('Empresa declarada por un alumno particular. Solo informativo, sin FK.');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropColumn('empresa_texto');
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });
    }
};
