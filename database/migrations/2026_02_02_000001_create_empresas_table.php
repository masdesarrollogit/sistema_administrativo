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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('expediente', 50)->nullable();
            $table->string('cif', 20)->unique();
            $table->string('razon_social', 255);
            $table->integer('plantilla_media')->default(0);
            $table->string('reserva', 50)->nullable();
            $table->decimal('importe_reserva_2023', 15, 2)->default(0);
            $table->decimal('importe_reserva_2024', 15, 2)->default(0);
            $table->decimal('credito_asignado', 15, 2)->default(0);
            $table->decimal('credito_dispuesto', 15, 2)->default(0);
            $table->decimal('credito_disponible', 15, 2)->default(0);
            $table->decimal('tgss', 15, 2)->default(0);
            $table->decimal('cofinanciacion_privada_exigido', 8, 2)->default(0);
            $table->decimal('cofinanciacion_privada_cumplido', 8, 2)->default(0);
            $table->string('cnae', 255)->nullable();
            $table->text('convenio')->nullable();
            $table->enum('pyme', ['SI', 'NO'])->default('NO');
            $table->enum('nueva_creacion', ['SI', 'NO'])->default('NO');
            $table->string('poblacion', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->enum('anulada', ['SI', 'NO'])->default('NO');
            $table->enum('bloqueada', ['SI', 'NO'])->default('NO');
            $table->boolean('nuevo')->default(true)->comment('Flag para identificar registros nuevos en la última importación');
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('actualizacion')->nullable()->useCurrentOnUpdate();
            $table->timestamps();

            // Índices para búsquedas frecuentes
            $table->index('razon_social');
            $table->index('pyme');
            $table->index('bloqueada');
            $table->index('nueva_creacion');
            $table->index('fecha_creacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
