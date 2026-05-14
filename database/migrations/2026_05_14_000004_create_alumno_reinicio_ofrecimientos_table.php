<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_reinicio_ofrecimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_no_apto_id')->constrained('alumno_no_aptos')->cascadeOnDelete();
            $table->unsignedTinyInteger('num_ofrecimiento'); // 1, 2, 3, 4
            $table->timestamp('fecha_envio')->useCurrent();
            $table->string('canal', 20)->default('email');
            $table->string('destinatario_email');
            $table->boolean('exitoso')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['alumno_no_apto_id', 'fecha_envio'], 'reinicio_ofrec_no_apto_envio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_reinicio_ofrecimientos');
    }
};
