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
        Schema::create('candidatos', function (Blueprint $table) {
            $table->id();
            
            // Tipo de candidato
            $table->foreignId('tipo_candidato_id')
                  ->constrained('tipos_candidato');
            
            // Referencias opcionales según el tipo
            $table->foreignId('empresa_id')->nullable()
                  ->constrained('empresas')->nullOnDelete()
                  ->comment('Para tipo empresa_organizadora');
            $table->foreignId('empresa_externa_id')->nullable()
                  ->constrained('empresas_externas')->nullOnDelete()
                  ->comment('Para tipo empresa_externa');
            
            // Datos del contacto principal
            $table->string('nombre_contacto', 255);
            $table->string('email', 255);
            $table->string('telefono', 50)->nullable();
            
            // Gestión administrativa
            $table->enum('estatus', ['pendiente', 'completo', 'cancelado', 'pausado'])
                  ->default('pendiente');
            
            // Control de recordatorios
            $table->timestamp('ultimo_recordatorio')->nullable();
            $table->integer('recordatorios_enviados')->default(0);
            
            // Curso seleccionado (referencia externa al sistema de matriculación)
            $table->string('curso_referencia', 100)->nullable()
                  ->comment('ID o código del curso en el sistema externo');
            $table->string('curso_nombre', 255)->nullable();
            
            // Metadatos
            $table->text('notas')->nullable();
            $table->timestamps();
            
            // Índices para optimizar el cron y búsquedas
            $table->index('estatus');
            $table->index('email');
            $table->index('ultimo_recordatorio');
            $table->index(['estatus', 'ultimo_recordatorio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidatos');
    }
};
