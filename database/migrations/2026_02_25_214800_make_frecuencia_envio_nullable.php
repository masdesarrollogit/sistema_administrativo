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
        // 1. Primero hacer la columna nullable
        Schema::table('candidatos', function (Blueprint $table) {
            $table->integer('frecuencia_envio')
                  ->nullable()
                  ->default(null)
                  ->comment('Días entre envíos. NULL = semanal (lunes)')
                  ->change();
        });

        // 2. Luego convertir los que tienen el default antiguo (3) a null
        // (Ahora que la columna ya acepta nulos)
        DB::table('candidatos')
            ->where('frecuencia_envio', 3)
            ->update(['frecuencia_envio' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Primero restaurar nulls al default anterior (3)
        // (Antes de quitarle el permiso de ser nulo a la columna)
        DB::table('candidatos')
            ->whereNull('frecuencia_envio')
            ->update(['frecuencia_envio' => 3]);

        // 2. Luego volver a hacer la columna NOT NULL (default 3)
        Schema::table('candidatos', function (Blueprint $table) {
            $table->integer('frecuencia_envio')
                  ->default(3)
                  ->nullable(false) // Volver a NOT NULL
                  ->comment('Días entre envíos')
                  ->change();
        });
    }
};
