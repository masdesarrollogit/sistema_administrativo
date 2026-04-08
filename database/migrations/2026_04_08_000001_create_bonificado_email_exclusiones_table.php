<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonificado_email_exclusiones', function (Blueprint $table) {
            $table->id();
            $table->string('nif', 20)->unique();
            $table->string('nombre', 150)->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('excluido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonificado_email_exclusiones');
    }
};
