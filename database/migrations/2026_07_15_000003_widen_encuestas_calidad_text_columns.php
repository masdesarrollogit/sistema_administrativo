<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los campos "de identificación" del Form son libres: algunos alumnos escriben
 * respuestas largas donde se esperaba un valor corto (p.ej. una queja entera en
 * "Modalidad", o "AUXILIAR ADMINISTRATIVO" en "Nº Grupo"), lo que rompía la
 * importación con "Data too long". Se amplían esas columnas a TEXT para que
 * ninguna fila rara falle. Los campos indexados (email, cif) se recortan en el
 * servicio en su lugar.
 */
return new class extends Migration
{
    protected array $columnas = [
        'alumno_nombre', 'numero_grupo', 'denominacion_accion', 'modalidad',
        'edad_raw', 'sexo', 'titulacion', 'lugar_trabajo', 'categoria_profesional',
        'horario_curso', 'porcentaje_jornada', 'tamano_empresa', 'curso_resuelto',
    ];

    public function up(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            foreach ($this->columnas as $col) {
                $table->text($col)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_calidad', function (Blueprint $table) {
            // Volver a longitudes originales (aproximadas)
            $table->string('alumno_nombre', 191)->nullable()->change();
            $table->string('numero_grupo', 20)->nullable()->change();
            $table->string('denominacion_accion', 255)->nullable()->change();
            $table->string('modalidad', 60)->nullable()->change();
            $table->string('edad_raw', 60)->nullable()->change();
            $table->string('sexo', 20)->nullable()->change();
            $table->string('titulacion', 191)->nullable()->change();
            $table->string('lugar_trabajo', 191)->nullable()->change();
            $table->string('categoria_profesional', 120)->nullable()->change();
            $table->string('horario_curso', 120)->nullable()->change();
            $table->string('porcentaje_jornada', 30)->nullable()->change();
            $table->string('tamano_empresa', 60)->nullable()->change();
            $table->string('curso_resuelto', 255)->nullable()->change();
        });
    }
};
