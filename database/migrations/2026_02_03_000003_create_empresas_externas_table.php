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
        Schema::create('empresas_externas', function (Blueprint $table) {
            $table->id();
            $table->string('cif', 20)->unique();
            $table->string('razon_social', 255);
            $table->string('email', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('poblacion', 100)->nullable();
            $table->string('contacto_nombre', 255)->nullable()->comment('Nombre del contacto principal');
            $table->text('notas')->nullable();
            $table->timestamps();
            
            $table->index('razon_social');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas_externas');
    }
};
