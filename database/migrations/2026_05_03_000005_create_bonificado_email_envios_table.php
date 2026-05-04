<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonificado_email_envios', function (Blueprint $table) {
            $table->id();
            $table->string('nif', 20);
            $table->string('cif', 20);
            $table->string('email_destinatario', 191);
            $table->string('email_cc_empresa', 191)->nullable();
            $table->string('email_cc_admin', 191)->nullable();
            $table->string('nombre_participante', 150)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->decimal('saldo_enviado', 15, 2)->default(0);
            $table->enum('metodo', ['cron', 'manual'])->default('cron');
            $table->boolean('modo_prueba')->default(false);
            $table->timestamp('enviado_at')->useCurrent();
            $table->timestamps();

            $table->index(['nif', 'cif', 'enviado_at'], 'bee_nif_cif_enviado_idx');
            $table->index('enviado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonificado_email_envios');
    }
};
