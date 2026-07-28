<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca las filas espejo creadas a partir de `empresas_externas` para poder
     * matricular a sus alumnos (alumnos.empresa_id y grupos_formativos.empresa_id
     * apuntan a `empresas`). No tienen datos de credito FUNDAE nuestro, asi que se
     * excluyen de las estadisticas de credito y del listado de empresas sin grupos.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('bonificacion_externa')
                ->default(false)
                ->after('razon_social')
                ->comment('true = espejo de una empresa externa que gestiona su propia bonificacion');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('bonificacion_externa');
        });
    }
};
