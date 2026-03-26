<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('apellido1');
            $table->string('apellido2')->nullable();
            $table->string('nif', 15);
            $table->string('email')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('niss', 12)->nullable();
            $table->string('ccc', 11)->nullable();
            $table->string('grupo_cotizacion_tgss', 5)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['H', 'M'])->nullable();
            $table->unsignedTinyInteger('nivel_estudios')->nullable();
            $table->unsignedTinyInteger('categoria_profesional')->nullable();
            $table->unsignedTinyInteger('jornada_laboral')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nif', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
