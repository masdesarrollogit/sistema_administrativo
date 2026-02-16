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
        Schema::create('grupos_anterior', function (Blueprint $table) {
            $table->id();
            $table->string('grupo_id', 50)->nullable();
            $table->string('codigo_grupo', 100)->nullable();
            $table->string('codigo_grupo_accion_formativa', 100)->nullable();
            $table->string('tipo_accion_formativa', 100)->nullable();
            $table->text('denominacion')->nullable();
            $table->string('cif', 20)->nullable()->index();
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->date('not_inicio')->nullable();
            $table->date('not_final')->nullable();
            $table->string('modalidad', 50)->nullable();
            $table->integer('duracion')->default(0);
            $table->string('estado', 50)->nullable();
            $table->text('incidencia')->nullable();
            $table->string('medios_formacion', 255)->nullable();
            $table->integer('numero_participantes')->default(0);
            $table->string('centro_formacion', 255)->nullable();
            $table->string('centro_imparticion', 255)->nullable();
            $table->string('centro_gestor_plataforma', 255)->nullable();
            $table->timestamps();

            // Índices
            $table->index('estado');
            $table->index('modalidad');
            $table->index('inicio');
            $table->index('fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos_anterior');
    }
};
