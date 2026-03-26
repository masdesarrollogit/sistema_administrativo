<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido1');
            $table->string('apellido2')->nullable();
            $table->string('nif', 15)->unique();
            $table->string('email');
            $table->string('telefono', 20);
            $table->enum('tramo_horario', ['tramo_1', 'tramo_2']);
            $table->enum('tipo', ['interno', 'externo']);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutores');
    }
};
