<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging de alumnos provenientes de `encomienda_alumnos` del sistema externo.
 * Espeja los campos que van a `alumnos` (ya mapeados a códigos FUNDAE) + campos
 * de referencia. Se materializan como Alumno real al crear el Grupo Formativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encomienda_alumnos_staging', function (Blueprint $table) {
            $table->id();

            // Identidad remota + vínculo con el contrato local
            $table->unsignedInteger('source_id')->unique(); // id remoto de encomienda_alumnos
            $table->foreignId('encomienda_contrato_id')->constrained('encomienda_contratos')->cascadeOnDelete();
            $table->unsignedInteger('contrato_source_id')->index(); // id remoto del contrato
            $table->string('referencia_contrato', 60)->nullable();

            // Campos que van a `alumnos` (ya mapeados)
            $table->string('nombre', 255)->nullable();
            $table->string('apellido1', 255)->nullable();
            $table->string('apellido2', 255)->nullable();
            $table->string('nif', 20)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('niss', 20)->nullable();
            $table->string('grupo_cotizacion_tgss', 5)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->unsignedTinyInteger('nivel_estudios')->nullable();
            $table->unsignedTinyInteger('categoria_profesional')->nullable();

            // Referencia (NO van a `alumnos`)
            $table->string('cargo', 150)->nullable();
            $table->string('curso_interes', 255)->nullable();
            $table->string('horas', 10)->nullable();
            $table->string('fecha_prevista_inicio', 50)->nullable();
            $table->text('comentarios')->nullable();

            // Lado Panel
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'materializado', 'descartado'])
                ->default('pendiente')->index();
            $table->timestamp('sincronizado_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encomienda_alumnos_staging');
    }
};
