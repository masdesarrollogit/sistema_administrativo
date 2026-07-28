<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grupos formativos cuya bonificacion FUNDAE la gestiona la propia empresa cliente.
     * Nosotros solo impartimos el curso en Moodle; ellos comunican accion y grupo en su
     * aplicativo FUNDAE y nos trasladan el codigo resultante como texto libre.
     */
    public function up(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->boolean('gestion_externa')
                ->default(false)
                ->after('empresa_id')
                ->comment('true = la bonificacion la gestiona la empresa cliente en su propia FUNDAE');

            $table->string('codigo_grupo_externo', 50)
                ->nullable()
                ->after('gestion_externa')
                ->comment('Accion/grupo FUNDAE que nos indica la empresa externa (ej: 241/3)');

            $table->index('gestion_externa');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->dropIndex(['gestion_externa']);
            $table->dropColumn(['gestion_externa', 'codigo_grupo_externo']);
        });
    }
};
