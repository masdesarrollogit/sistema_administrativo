<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tipos_requisito')
            ->where('nombre', 'Contrato enviado')
            ->update(['nombre' => 'Enviar Contrato rellenado']);

        DB::table('tipos_requisito')
            ->where('nombre', 'Contrato Firmado')
            ->update(['nombre' => 'Firmar contrato']);

        DB::table('tipos_requisito')
            ->where('nombre', 'Datos de alumno')
            ->update(['nombre' => 'Enviar Datos de Alumno/s']);

        DB::table('tipos_requisito')
            ->where('nombre', 'Curso seleccionado')
            ->update(['nombre' => 'Seleccionar Curso']);

        DB::table('tipos_requisito')
            ->where('nombre', 'Confirmación de fecha de Inicio')
            ->update(['nombre' => 'Confirmar Fecha de Inicio']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database', function (Blueprint $table) {
            //
        });
    }
};
