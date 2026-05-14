<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_moodle_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('notificaciones_activas')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // En producción arranca activo; en dev/test arranca desactivado para que
        // ningún cron envíe correo a destinatarios reales hasta activarlo a mano.
        $activoInicial = app()->environment('production');

        DB::table('reportes_moodle_settings')->insert([
            'id'                     => 1,
            'notificaciones_activas' => $activoInicial,
            'updated_by'             => null,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_moodle_settings');
    }
};
