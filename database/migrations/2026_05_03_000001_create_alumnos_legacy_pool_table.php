<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos_legacy_pool', function (Blueprint $table) {
            $table->id();
            $table->string('nif', 20)->unique();
            $table->string('nombre', 150)->nullable();
            $table->string('apellido1', 150)->nullable();
            $table->string('apellido2', 150)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('niss', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedTinyInteger('nivel_estudios')->nullable();
            $table->unsignedTinyInteger('categoria_profesional')->nullable();
            $table->string('grupo_cotizacion_tgss', 5)->nullable();
            $table->string('legacy_nid', 50)->nullable();
            $table->string('legacy_company_text', 255)->nullable();
            $table->string('legacy_cif_resuelto', 20)->nullable()->index();
            $table->unsignedInteger('source_mem_id')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos_legacy_pool');
    }
};
