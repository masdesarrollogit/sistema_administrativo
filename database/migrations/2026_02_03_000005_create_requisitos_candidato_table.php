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
        Schema::create('requisitos_candidato', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('candidato_id')
                  ->constrained('candidatos')
                  ->cascadeOnDelete();
            
            $table->foreignId('tipo_requisito_id')
                  ->constrained('tipos_requisito')
                  ->cascadeOnDelete();
            
            $table->enum('estado', ['pendiente', 'en_proceso', 'completado'])
                  ->default('pendiente');
            
            $table->timestamp('fecha_completado')->nullable();
            $table->text('notas')->nullable();
            $table->string('documento_path')->nullable()
                  ->comment('Ruta al documento adjunto si aplica');
            
            $table->timestamps();
            
            // Un candidato tiene un registro por tipo de requisito
            $table->unique(['candidato_id', 'tipo_requisito_id']);
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisitos_candidato');
    }
};
