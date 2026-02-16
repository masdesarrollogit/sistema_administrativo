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
        Schema::create('tipos_requisito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_candidato_id')
                  ->constrained('tipos_candidato')
                  ->cascadeOnDelete();
            $table->string('codigo', 50)->comment('contrato_enviado, contrato_firmado, datos_alumno, etc.');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('obligatorio')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Un tipo de candidato no puede tener dos requisitos con el mismo código
            $table->unique(['tipo_candidato_id', 'codigo']);
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_requisito');
    }
};
