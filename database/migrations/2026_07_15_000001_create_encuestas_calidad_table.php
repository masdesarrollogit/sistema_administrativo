<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de respuestas del "Cuestionario para la Evaluación de la Calidad de las
 * Acciones Formativas" (exigido por FUNDAE) que el alumno rellena al final del
 * curso en un Microsoft Form.
 *
 * Dos vías de entrada, misma tabla (ver EncuestaCalidadService):
 *  - `import`         → subida manual del Excel/CSV exportado del Form (histórico).
 *  - `power_automate` → correo de Power Automate leído por IMAP (tiempo casi real).
 *
 * `forms_id` (Id. de respuesta del Form) es único → idempotencia entre ambas vías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_calidad', function (Blueprint $table) {
            $table->id();

            // Idempotencia: Id. de respuesta del Microsoft Form
            $table->string('forms_id', 191)->unique();

            // Origen del registro
            $table->string('origen', 20)->default('import')->index(); // import | power_automate

            // Metadatos temporales
            $table->dateTime('hora_inicio')->nullable();
            $table->dateTime('hora_fin')->nullable();
            $table->date('fecha_cumplimentacion')->nullable()->index();

            // Identificación (todo string nullable — el Form puede venir incompleto/anónimo)
            $table->string('alumno_nombre', 191)->nullable();
            $table->string('alumno_email', 191)->nullable()->index();
            $table->string('cif_empresa', 20)->nullable()->index();
            $table->unsignedInteger('numero_accion')->nullable()->index();
            $table->string('numero_grupo', 20)->nullable();
            $table->string('denominacion_accion', 255)->nullable();
            $table->string('modalidad', 60)->nullable();
            $table->string('edad_raw', 60)->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('titulacion', 191)->nullable();
            $table->string('lugar_trabajo', 191)->nullable();
            $table->string('categoria_profesional', 120)->nullable();
            $table->string('horario_curso', 120)->nullable();
            $table->string('porcentaje_jornada', 30)->nullable();
            $table->string('tamano_empresa', 60)->nullable();

            // Valoraciones 1-4 (bloques FUNDAE). item_04 y item_05 cubren el 3.1/3.2 duplicado.
            for ($i = 1; $i <= 19; $i++) {
                $table->unsignedTinyInteger('item_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT))->nullable();
            }
            // Item 10 del cuestionario = grado de satisfacción general (clave de las estadísticas)
            $table->unsignedTinyInteger('satisfaccion_general')->nullable()->index();

            // Observaciones / sugerencias del alumno (texto libre)
            $table->text('observaciones')->nullable();

            // Vínculos resueltos con el resto del Panel (nullable: puede no encontrarse)
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->foreignId('grupo_formativo_id')->nullable()->constrained('grupos_formativos')->nullOnDelete();
            $table->foreignId('accion_formativa_id')->nullable()->constrained('acciones_formativas')->nullOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('tutores')->nullOnDelete();

            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_calidad');
    }
};
