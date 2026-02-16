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
        Schema::create('moodle_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('moodle_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moodle_categoria_id')->constrained('moodle_categorias')->onDelete('cascade');
            $table->string('titulo');
            $table->decimal('precio', 10, 2);
            $table->integer('horas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moodle_cursos');
        Schema::dropIfExists('moodle_categorias');
    }
};
