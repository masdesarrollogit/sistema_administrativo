<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descarte soft y reversible de contratos de encomienda: oculta de la UI y el
 * sync deja de resucitarlos. Pensado para depurar contratos de prueba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomienda_contratos', function (Blueprint $table) {
            $table->timestamp('descartado_en')->nullable()->after('sincronizado_en')->index();
            $table->foreignId('descartado_por')->nullable()->after('descartado_en')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encomienda_contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('descartado_por');
            $table->dropColumn('descartado_en');
        });
    }
};
