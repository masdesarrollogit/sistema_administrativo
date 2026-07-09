<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror local de `contratos_encomienda` del sistema externo + estado de
 * procesamiento en el Panel. Una fila por contrato remoto (source_id único).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encomienda_contratos', function (Blueprint $table) {
            $table->id();

            // Identidad remota
            $table->unsignedInteger('source_id')->unique(); // id remoto de contratos_encomienda
            $table->string('referencia_aceptacion', 60)->nullable()->index();

            // Datos empresa / firmante (copiados del origen)
            $table->string('empresa_cif', 20)->nullable()->index();
            $table->string('empresa_razon_social', 255)->nullable();
            $table->string('empresa_domicilio', 255)->nullable();
            $table->string('empresa_localidad', 120)->nullable();
            $table->string('firmante_nombre', 150)->nullable();
            $table->string('firmante_nif', 20)->nullable();
            $table->string('firmante_cargo', 120)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('saldo_fundae', 120)->nullable();
            $table->string('tiene_rlt', 10)->nullable();

            // VARCHAR flexible (NO enum): tolera valores nuevos del origen
            // (ratificación: estado "Pendiente ratificación", origen PDF/-digital-anulado).
            $table->string('origen_externo', 40)->nullable();
            $table->string('estado_externo', 60)->nullable();

            // PDF firmado (se resuelve el vigente en el endpoint remoto)
            $table->string('pdf_path', 255)->nullable();
            $table->string('pdf_hash', 64)->nullable();
            $table->timestamp('aceptado_en')->nullable();

            // Lado Panel
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('candidato_id')->nullable()->constrained('candidatos')->nullOnDelete();
            $table->enum('estado_procesamiento', ['pendiente_empresa', 'candidato_creado', 'error'])
                ->default('pendiente_empresa')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sincronizado_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encomienda_contratos');
    }
};
