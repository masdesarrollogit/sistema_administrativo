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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('grupo_id', 50)->nullable()->comment('ID del grupo en el sistema origen');
            $table->string('codigo_grupo', 100)->nullable();
            $table->string('codigo_grupo_accion_formativa', 100)->nullable();
            $table->string('tipo_accion_formativa', 100)->nullable();
            $table->text('denominacion')->nullable()->comment('Contiene CIF + nombre del curso');
            $table->string('cif', 20)->nullable()->index()->comment('CIF extraído de la denominación');
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->date('not_inicio')->nullable()->comment('Fecha notificación inicio');
            $table->date('not_final')->nullable()->comment('Fecha notificación final');
            $table->string('modalidad', 50)->nullable()->comment('Teleformación, Presencial, etc.');
            $table->integer('duracion')->default(0)->comment('Duración en horas');
            $table->string('estado', 50)->nullable()->comment('Finalizado, Válido, Modificado');
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
        Schema::dropIfExists('grupos');
    }
};
