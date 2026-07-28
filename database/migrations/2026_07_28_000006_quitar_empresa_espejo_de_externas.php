<?php

use App\Models\Candidato;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se revierte la "empresa espejo" de las empresas externas.
     *
     * Motivo de negocio: la tabla `empresas` es el universo FUNDAE nuestro (credito,
     * plantilla, CIF verificado). Una empresa externa gestiona su propia bonificacion y
     * nosotros NO hemos calculado su saldo, asi que no puede figurar en /webcurso/empresas
     * con credito cero: seria un dato inventado. Sus datos ya viven en `empresas_externas`,
     * que es de donde se leen para control.
     *
     * En consecuencia `grupos_formativos.empresa_id` pasa a opcional (igual que ya lo es
     * en `alumnos`), y los alumnos de empresa externa guardan la razon social en
     * `empresa_texto`, el mismo campo informativo que usan los particulares.
     */
    public function up(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
        });

        if (!Schema::hasColumn('empresas', 'bonificacion_externa')) {
            return;
        }

        // Desvincular lo que colgaba de un espejo antes de borrarlo.
        $espejos = DB::table('empresas')->where('bonificacion_externa', true)->get(['id', 'razon_social']);

        foreach ($espejos as $espejo) {
            DB::table('alumnos')
                ->where('empresa_id', $espejo->id)
                ->update([
                    'empresa_id'    => null,
                    'empresa_texto' => DB::raw('COALESCE(empresa_texto, ' . DB::getPdo()->quote($espejo->razon_social) . ')'),
                ]);

            DB::table('grupos_formativos')->where('empresa_id', $espejo->id)->update(['empresa_id' => null]);
            DB::table('matriculas_autonomas')->where('empresa_id', $espejo->id)->update(['empresa_id' => null]);
        }

        DB::table('empresas')->where('bonificacion_externa', true)->delete();

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('bonificacion_externa');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('bonificacion_externa')->default(false)->after('razon_social');
        });

        // Los espejos borrados no se recrean: los datos siguen en `empresas_externas`.
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
        });
    }
};
