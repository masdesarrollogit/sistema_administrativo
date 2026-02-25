<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participantes_bonificados', function (Blueprint $table) {
            $table->id();
            $table->string('nif_participante', 20)->nullable();
            $table->string('niss', 30)->nullable();
            $table->string('nombre', 150)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('cif', 20)->nullable()->index();
            $table->string('id_codigo_grupo', 50)->nullable();
            $table->string('codigo_pif', 50)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado_grupo', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes_bonificados');
    }
};
