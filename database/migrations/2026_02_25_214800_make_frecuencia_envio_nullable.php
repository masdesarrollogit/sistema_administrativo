<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * frecuencia_envio:
     *   NULL = sin frecuencia personalizada → recordatorio semanal (lunes)
     *   NOT NULL = frecuencia personalizada en días
     */
    public function up(): void
    {
        // Primero, convertir los que tienen el default antiguo (3) a null
        DB::table('candidatos')
            ->where('frecuencia_envio', 3)
            ->update(['frecuencia_envio' => null]);

        // Hacer la columna nullable con default null
        Schema::table('candidatos', function (Blueprint $table) {
            $table->integer('frecuencia_envio')
                  ->nullable()
                  ->default(null)
                  ->comment('Días entre envíos. NULL = semanal (lunes)')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidatos', function (Blueprint $table) {
            $table->integer('frecuencia_envio')
                  ->default(3)
                  ->comment('Días entre envíos')
                  ->change();
        });

        // Restaurar nulls al default anterior
        DB::table('candidatos')
            ->whereNull('frecuencia_envio')
            ->update(['frecuencia_envio' => 3]);
    }
};
