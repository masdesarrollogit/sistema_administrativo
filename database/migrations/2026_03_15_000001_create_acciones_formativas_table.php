<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acciones_formativas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero_accion')->unique();
            $table->string('denominacion');
            $table->string('modalidad')->default('Teleformación');
            $table->string('tipo')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedInteger('horas');
            $table->string('nivel_formacion')->nullable();
            $table->string('nif_proveedor_plataforma', 9)->nullable();
            $table->string('url_plataforma')->nullable();
            $table->string('clave_acceso')->nullable();
            $table->string('usuario_supervision')->nullable();
            $table->string('area_profesional')->nullable();
            $table->string('codigo_actividad', 10)->nullable();
            $table->string('cod_grupo_accion', 10)->nullable();
            $table->text('objetivos')->nullable();
            $table->text('contenidos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acciones_formativas');
    }
};
