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
        Schema::create('notificaciones_log', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('candidato_id')
                  ->constrained('candidatos')
                  ->cascadeOnDelete();
            
            $table->json('requisitos_faltantes')
                  ->comment('Array de requisitos pendientes al momento del envío');
            
            $table->string('tipo_notificacion', 50)->default('recordatorio');
            $table->string('canal', 20)->default('email');
            $table->string('destinatario_email', 255);
            
            $table->timestamp('enviado_at');
            $table->boolean('exitoso')->default(false);
            $table->text('error_message')->nullable();
            
            $table->index('enviado_at');
            $table->index(['candidato_id', 'enviado_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_log');
    }
};
