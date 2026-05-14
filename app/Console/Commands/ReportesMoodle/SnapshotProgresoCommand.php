<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Services\Webcurso\AlumnoProgresoSnapshotter;
use Illuminate\Console\Command;

class SnapshotProgresoCommand extends Command
{
    protected $signature = 'reportes-moodle:snapshot
        {--dry-run : Ejecutar sin escribir en BD}
        {--alumno-id= : Refrescar un único alumno por ID}';

    protected $description = 'Snapshot diario del progreso de los alumnos en Moodle (lastaccess + progress)';

    public function handle(AlumnoProgresoSnapshotter $snapshotter): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $alumnoId = $this->option('alumno-id') ? (int) $this->option('alumno-id') : null;

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN activado - no se escribirán filas en alumno_progreso_moodle');
        }
        if ($alumnoId !== null) {
            $this->info("🎯 Filtrando snapshot para alumno_id={$alumnoId}");
        }

        $this->info('🚀 Iniciando snapshot de progreso Moodle...');

        $resumen = $snapshotter->ejecutar(alumnoId: $alumnoId, dryRun: $dryRun);

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Procesados', $resumen['procesados']],
                ['Escritos / actualizados', $resumen['escritos']],
                ['Omitidos', $resumen['omitidos']],
                ['Errores', $resumen['errores']],
            ],
        );

        foreach ($resumen['mensajes'] as $mensaje) {
            $this->line("  · {$mensaje}");
        }

        return self::SUCCESS;
    }
}
