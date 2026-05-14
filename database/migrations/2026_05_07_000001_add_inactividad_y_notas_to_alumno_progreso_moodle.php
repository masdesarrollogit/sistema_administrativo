<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->decimal('nota_total', 7, 2)->nullable()->after('completed');
            $table->decimal('nota_max', 7, 2)->nullable()->after('nota_total');
            $table->unsignedSmallInteger('dias_inactivo')->nullable()->after('nota_max');
            $table->boolean('inactivo')->default(false)->after('dias_inactivo');

            $table->index('inactivo');
        });
    }

    public function down(): void
    {
        Schema::table('alumno_progreso_moodle', function (Blueprint $table) {
            $table->dropIndex(['inactivo']);
            $table->dropColumn(['nota_total', 'nota_max', 'dias_inactivo', 'inactivo']);
        });
    }
};
