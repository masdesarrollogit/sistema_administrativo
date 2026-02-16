<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('candidatos', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('estatus');
            $table->integer('frecuencia_envio')->default(3)->after('fecha_inicio')->comment('Días entre envíos');
            $table->text('descripcion_personalizada')->nullable()->after('frecuencia_envio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidatos', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'frecuencia_envio', 'descripcion_personalizada']);
        });
    }
};
