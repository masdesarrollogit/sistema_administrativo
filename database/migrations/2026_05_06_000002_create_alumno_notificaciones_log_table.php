<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_notificaciones_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('tutores')->nullOnDelete();
            $table->foreignId('grupo_formativo_id')->nullable()->constrained('grupos_formativos')->nullOnDelete();
            $table->string('tipo', 50);
            $table->unsignedTinyInteger('fase')->default(1);
            $table->string('canal', 20)->default('email');
            $table->string('destinatario_email');
            $table->json('payload')->nullable();
            $table->boolean('exitoso')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('enviado_at');

            $table->index(['alumno_id', 'tipo', 'enviado_at']);
            $table->index(['grupo_formativo_id', 'tipo']);
            $table->index(['tutor_id', 'tipo', 'enviado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_notificaciones_log');
    }
};
