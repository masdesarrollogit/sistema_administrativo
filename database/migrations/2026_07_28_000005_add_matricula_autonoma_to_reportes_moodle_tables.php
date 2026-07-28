<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reportes Moodle deja de estar atado a los grupos formativos: una fila de
     * seguimiento puede venir de un pivot `grupo_formativo_alumno` (bonificado) o de
     * una `matricula_autonoma` (autónomo 2x1 / particular).
     *
     * En `alumno_no_aptos` no basta con poner las dos FK nullable: un UNIQUE con
     * columnas NULL deja de proteger en MySQL. Por eso se añade `origen_clave`
     * ("grupo:37" / "autonoma:12") como discriminador sin nulos.
     *
     * Cada paso está guardado por hasColumn/índice porque el ALTER de MySQL no es
     * transaccional: una migración interrumpida debe poder retomarse.
     */
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_formativo_alumno_id')->nullable()->change();

            if (!Schema::hasColumn('alumno_progreso_moodle', 'matricula_autonoma_id')) {
                $table->foreignId('matricula_autonoma_id')
                    ->nullable()
                    ->after('grupo_formativo_alumno_id')
                    ->constrained('matriculas_autonomas')
                    ->cascadeOnDelete();

                $table->unique(['matricula_autonoma_id', 'fecha_snapshot'], 'alumno_progreso_matricula_fecha_unique');
            }
        });

        Schema::table('alumno_notificaciones_log', function (Blueprint $table) {
            if (!Schema::hasColumn('alumno_notificaciones_log', 'matricula_autonoma_id')) {
                $table->foreignId('matricula_autonoma_id')
                    ->nullable()
                    ->after('grupo_formativo_id')
                    ->constrained('matriculas_autonomas')
                    ->nullOnDelete();

                $table->index(['matricula_autonoma_id', 'tipo']);
            }
        });

        Schema::table('alumno_no_aptos', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_formativo_id')->nullable()->change();

            if (!Schema::hasColumn('alumno_no_aptos', 'matricula_autonoma_id')) {
                $table->foreignId('matricula_autonoma_id')
                    ->nullable()
                    ->after('grupo_formativo_id')
                    ->constrained('matriculas_autonomas')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('alumno_no_aptos', 'origen_clave')) {
                $table->string('origen_clave', 40)
                    ->nullable()
                    ->after('matricula_autonoma_id')
                    ->comment('grupo:{id} | autonoma:{id} — discriminador sin nulos para el UNIQUE');
            }
        });

        // Backfill: todo lo detectado hasta ahora viene de un grupo formativo.
        DB::table('alumno_no_aptos')
            ->whereNull('origen_clave')
            ->whereNotNull('grupo_formativo_id')
            ->update(['origen_clave' => DB::raw("CONCAT('grupo:', grupo_formativo_id)")]);

        $indices = collect(Schema::getIndexes('alumno_no_aptos'))->pluck('name');

        // El índice nuevo va PRIMERO: MySQL usa el viejo (alumno_id, grupo_formativo_id)
        // para respaldar la FK de alumno_id y no deja borrarlo si queda descubierta.
        // El nuevo también empieza por alumno_id, así que puede tomar el relevo.
        if (!$indices->contains('alumno_no_apto_origen_unique')) {
            Schema::table('alumno_no_aptos', fn (Blueprint $t) => $t->unique(['alumno_id', 'origen_clave'], 'alumno_no_apto_origen_unique'));
        }

        if ($indices->contains('alumno_no_apto_unique')) {
            Schema::table('alumno_no_aptos', fn (Blueprint $t) => $t->dropUnique('alumno_no_apto_unique'));
        }
    }

    public function down(): void
    {
        // Mismo baile que en up(): el índice sustituto se crea antes de soltar el vigente,
        // porque uno de los dos debe cubrir siempre la FK de alumno_id.
        Schema::table('alumno_no_aptos', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_formativo_id')->nullable(false)->change();
            $table->unique(['alumno_id', 'grupo_formativo_id'], 'alumno_no_apto_unique');
        });

        Schema::table('alumno_no_aptos', function (Blueprint $table) {
            $table->dropUnique('alumno_no_apto_origen_unique');
            $table->dropForeign(['matricula_autonoma_id']);
            $table->dropColumn(['matricula_autonoma_id', 'origen_clave']);
        });

        Schema::table('alumno_notificaciones_log', function (Blueprint $table) {
            $table->dropIndex(['matricula_autonoma_id', 'tipo']);
            $table->dropForeign(['matricula_autonoma_id']);
            $table->dropColumn('matricula_autonoma_id');
        });

        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropUnique('alumno_progreso_matricula_fecha_unique');
            $table->dropForeign(['matricula_autonoma_id']);
            $table->dropColumn('matricula_autonoma_id');
            $table->unsignedBigInteger('grupo_formativo_alumno_id')->nullable(false)->change();
        });
    }
};
