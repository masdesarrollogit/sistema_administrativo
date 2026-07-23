<?php

namespace App\Console\Commands;

use App\Models\MoodleMatriculaIndex;
use App\Services\Webcurso\MoodleReportingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Moodle\Services\MoodleService;

/**
 * Construye el índice inverso "email → cursos de Moodle" recorriendo todos los
 * cursos con core_enrol_get_enrolled_users(onlyactive=0), que SÍ incluye las
 * matrículas caducadas (alumnos que ya terminaron el curso).
 *
 * Es la base, 100% webservice y sin tocar la BD de Moodle, para resolver el curso
 * de las encuestas de calidad de alumnos que no tienen ficha/curso en el Panel
 * (comando `encuestas-calidad:resolver-moodle` y botón "Buscar en Moodle").
 *
 * Reemplaza el índice entero en cada ejecución (truncate + insert por lotes).
 */
class SnapshotMatriculasMoodle extends Command
{
    protected $signature = 'encuestas-calidad:snapshot-moodle
        {--dry-run : Recorre Moodle y reporta, pero NO escribe el índice}
        {--limit=0 : Limita el nº de cursos a recorrer (0 = todos; para pruebas)}';

    protected $description = 'Construye el índice email→cursos de Moodle (incl. matrículas caducadas) para resolver encuestas de calidad';

    public function handle(MoodleService $moodle, MoodleReportingService $reporting): int
    {
        if (!config('encuesta_calidad.moodle_resolucion_enabled', true)) {
            $this->warn('⏸ Resolución vía Moodle DESACTIVADA (encuesta_calidad.moodle_resolucion_enabled = false).');
            return self::SUCCESS;
        }

        if (empty(config('moodle.token'))) {
            $this->error('❌ No hay MOODLE_TOKEN configurado. No se puede consultar Moodle.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        // 1) Cursos del sitio
        try {
            $cursos = $moodle->getCoursesBasic();
        } catch (\Throwable $e) {
            $this->error('❌ Error al listar cursos de Moodle: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('📚 Cursos en Moodle: ' . count($cursos));

        // 2) Categorías a excluir (Foros / Repaso) — misma convención que Reportes Moodle
        $nombresExcluidos = (array) config('reportes_moodle.categorias_excluidas', ['Repaso', 'Foros']);
        $categoriaIdsExcluidos = $reporting->resolverCategoriaIds($nombresExcluidos);
        if ($categoriaIdsExcluidos) {
            $this->line('   Excluyendo categorías (Foros/Repaso): ' . implode(', ', $categoriaIdsExcluidos));
        }

        // 3) Filtrar cursos + (opcional) limitar
        $cursos = array_values(array_filter($cursos, fn ($c) => !in_array((int) ($c['categoryid'] ?? 0), $categoriaIdsExcluidos, true)));
        if ($limit > 0) {
            $cursos = array_slice($cursos, 0, $limit);
            $this->line("   (limitado a {$limit} cursos)");
        }
        $this->info('📖 Cursos a recorrer (tras exclusión): ' . count($cursos));

        // 4) Recorrer cursos y acumular filas del índice
        $filas = [];
        $errores = 0;
        $ahora = now();
        $bar = $this->output->createProgressBar(count($cursos));
        $bar->start();

        foreach ($cursos as $curso) {
            $courseId = (int) ($curso['id'] ?? 0);
            if ($courseId <= 0) {
                $bar->advance();
                continue;
            }

            try {
                $usuarios = $moodle->getEnrolledUsersBasic($courseId, ['id', 'email', 'lastcourseaccess']);
            } catch (\Throwable $e) {
                $errores++;
                $bar->advance();
                continue;
            }

            // Tutor del curso (profesor real por moodle_username). Álvaro/Raquel
            // comparten aula → etiqueta conjunta; David tiene aula propia.
            $tutorLabel = null;
            try {
                $usernames = array_map(fn ($t) => (string) ($t['username'] ?? ''), $moodle->getCourseTeachers($courseId));
                $tutorLabel = $this->etiquetaTutor($usernames);
            } catch (\Throwable $e) {
                // sin tutor si falla
            }

            $startdate = $this->tsADate($curso['startdate'] ?? null);
            $enddate   = $this->tsADate($curso['enddate'] ?? null);
            $fullname  = mb_substr(trim((string) ($curso['fullname'] ?? '')), 0, 255);
            $catId     = (int) ($curso['categoryid'] ?? 0) ?: null;

            foreach ($usuarios as $u) {
                $email = mb_strtolower(trim((string) ($u['email'] ?? '')));
                if ($email === '') {
                    continue;
                }
                $filas[] = [
                    'email'            => mb_substr($email, 0, 191),
                    'moodle_course_id' => $courseId,
                    'curso_fullname'   => $fullname,
                    'categoria_id'     => $catId,
                    'curso_startdate'  => $startdate,
                    'curso_enddate'    => $enddate,
                    'ultimo_acceso'    => $this->tsADate($u['lastcourseaccess'] ?? null),
                    'tutor_label'      => $tutorLabel,
                    'capturado_en'     => $ahora,
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('🔗 Filas email→curso recolectadas: ' . count($filas));
        if ($errores > 0) {
            $this->warn("⚠️ Cursos con error al pedir matriculados: {$errores}");
        }

        if ($dryRun) {
            $this->warn('🧪 --dry-run: no se escribe el índice.');
            return self::SUCCESS;
        }

        // 5) Reemplazar el índice entero. TRUNCATE hace commit implícito en MySQL,
        // así que va FUERA de transacción; los inserts se agrupan aparte.
        MoodleMatriculaIndex::query()->truncate();
        DB::transaction(function () use ($filas) {
            foreach (array_chunk($filas, 500) as $chunk) {
                MoodleMatriculaIndex::insert($chunk);
            }
        });

        $this->info('✅ Índice de matrículas Moodle actualizado (' . count($filas) . ' filas, ' . MoodleMatriculaIndex::distinct('email')->count('email') . ' emails únicos).');

        return self::SUCCESS;
    }

    /**
     * Traduce los usernames de los profesores de un curso a la etiqueta de tutor:
     *  - solo David  → "David Guerra"
     *  - Álvaro y/o Raquel (comparten aula) → "Álvaro Pino / Raquel García"
     *  - vacío o mezcla no clasificable → null
     *
     * @param string[] $usernames
     */
    protected function etiquetaTutor(array $usernames): ?string
    {
        $mapa = (array) config('encuesta_calidad.moodle_tutor_usernames', []);
        $compartidos = (array) config('encuesta_calidad.moodle_tutores_compartidos', []);
        $labelCompartida = (string) config('encuesta_calidad.moodle_tutor_label_compartida', '');

        $tutores = [];
        foreach ($usernames as $u) {
            if ($u !== '' && isset($mapa[$u])) {
                $tutores[$mapa[$u]] = true;
            }
        }
        $tutores = array_keys($tutores);

        if (empty($tutores)) {
            return null;
        }
        // Si TODOS los tutores presentes son del par compartido → etiqueta conjunta.
        if (empty(array_diff($tutores, $compartidos))) {
            return $labelCompartida ?: implode(' / ', $tutores);
        }
        // Un único tutor con aula propia (David) → su nombre.
        if (count($tutores) === 1) {
            return $tutores[0];
        }
        // Mezcla no esperada (p.ej. David + compartido) → no clasificamos.
        return null;
    }

    /** Convierte un timestamp Unix de Moodle a 'Y-m-d' (0/vacío → null). */
    protected function tsADate($ts): ?string
    {
        $ts = (int) $ts;
        if ($ts <= 0) {
            return null;
        }
        return Carbon::createFromTimestamp($ts, 'Europe/Madrid')->format('Y-m-d');
    }
}
