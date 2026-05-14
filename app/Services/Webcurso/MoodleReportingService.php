<?php

namespace App\Services\Webcurso;

use Modules\Moodle\Services\MoodleService;

class MoodleReportingService
{
    /**
     * Cache de categorías Moodle en memoria por request.
     * @var array<int, array{name:string, path:string}>|null
     */
    protected ?array $categoriasCache = null;

    public function __construct(protected MoodleService $moodle)
    {
    }

    /**
     * Devuelve los IDs de categorías Moodle cuyo nombre coincide (case-insensitive)
     * con alguno de los nombres dados, MÁS todos sus descendientes (cualquier
     * subcategoría bajo ellas en cualquier nivel de profundidad).
     *
     * Ejemplo: si pasas ['Foros'] y existen 'Foros' (id=16) y 'Foros Dominio…' (id=10, parent=16),
     * devuelve [16, 10] aunque 'Foros Dominio…' no esté en la lista de nombres.
     *
     * @param string[] $nombres
     * @return int[] IDs de las categorías matched + todos sus descendientes
     */
    public function resolverCategoriaIds(array $nombres): array
    {
        if (empty($nombres)) {
            return [];
        }

        $normalizados = array_map(fn ($n) => mb_strtolower(trim((string) $n)), $nombres);

        if ($this->categoriasCache === null) {
            $this->categoriasCache = [];
            try {
                foreach ($this->moodle->getCategories() as $cat) {
                    if (isset($cat['id'], $cat['name'])) {
                        $this->categoriasCache[(int) $cat['id']] = [
                            'name' => (string) $cat['name'],
                            'path' => (string) ($cat['path'] ?? "/{$cat['id']}"),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Si la función no está habilitada, devolvemos array vacío.
                return [];
            }
        }

        // 1) Match por nombre → ancestros
        $ancestros = [];
        foreach ($this->categoriasCache as $id => $info) {
            if (in_array(mb_strtolower($info['name']), $normalizados, true)) {
                $ancestros[] = $id;
            }
        }

        if (empty($ancestros)) {
            return [];
        }

        // 2) Por cada ancestro, encontrar todos los descendientes
        // (categorías cuyo `path` contiene "/{ancestro}/" o termina en "/{ancestro}")
        $idsFinales = $ancestros;
        foreach ($this->categoriasCache as $id => $info) {
            if (in_array($id, $idsFinales, true)) {
                continue;
            }
            foreach ($ancestros as $ancestroId) {
                $needle = "/{$ancestroId}/";
                if (str_contains($info['path'], $needle) || str_ends_with($info['path'], "/{$ancestroId}")) {
                    $idsFinales[] = $id;
                    break;
                }
            }
        }

        return array_values(array_unique($idsFinales));
    }

    /**
     * Devuelve, para cada moodle_user_id, el lastaccess GLOBAL del usuario.
     *
     * @param int[] $moodleUserIds
     * @return array<int, int> [moodle_user_id => lastaccess]
     */
    public function getLastAccessGlobalBatch(array $moodleUserIds, int $loteSize = 50): array
    {
        $moodleUserIds = array_values(array_unique(array_filter(array_map('intval', $moodleUserIds))));
        $resultado = [];

        foreach (array_chunk($moodleUserIds, max(1, $loteSize)) as $chunk) {
            $usuarios = $this->moodle->getUsersByIds($chunk);
            foreach ($chunk as $id) {
                $resultado[$id] = isset($usuarios[$id]['lastaccess']) ? (int) $usuarios[$id]['lastaccess'] : 0;
            }
        }

        return $resultado;
    }

    /**
     * Devuelve los grade items estructurados (cada actividad + total + cuestionario final).
     *
     * @return array{
     *     items: array<int, array{
     *         id:int,
     *         itemname:string,
     *         itemtype:?string,
     *         itemmodule:?string,
     *         grade:?float,
     *         grademax:?float,
     *         grademin:?float,
     *         percentageformatted:?string,
     *         lettergrade:?string,
     *         is_course_total:bool,
     *         is_final_quiz:bool,
     *         graded_at:?int
     *     }>,
     *     total: ?array,
     *     final_quiz: ?array
     * }
     */
    public function getUserGrades(int $moodleUserId, int $moodleCourseId): array
    {
        try {
            $raw = $this->moodle->getUserGradeItems($moodleUserId, $moodleCourseId);
        } catch (\Throwable $e) {
            return ['items' => [], 'total' => null, 'final_quiz' => null];
        }

        $items = $raw['usergrades'][0]['gradeitems'] ?? [];
        $parsed = [];
        $total = null;
        $finalQuiz = null;
        $lastQuiz = null;

        foreach ($items as $it) {
            $itemtype = $it['itemtype'] ?? null;
            $itemmodule = $it['itemmodule'] ?? null;
            $itemname = (string) ($it['itemname'] ?? '');

            $isCourseTotal = ($itemtype === 'course');
            $isQuiz = ($itemmodule === 'quiz');

            $row = [
                'id'                  => (int) ($it['id'] ?? 0),
                'itemname'            => $itemname !== '' ? $itemname : ($isCourseTotal ? 'Total del curso' : 'Sin nombre'),
                'itemtype'            => $itemtype,
                'itemmodule'          => $itemmodule,
                'grade'               => isset($it['graderaw']) ? (float) $it['graderaw'] : null,
                'grademax'            => isset($it['grademax']) ? (float) $it['grademax'] : null,
                'grademin'            => isset($it['grademin']) ? (float) $it['grademin'] : null,
                'percentageformatted' => $it['percentageformatted'] ?? null,
                'lettergrade'         => $it['lettergrade'] ?? null,
                'is_course_total'     => $isCourseTotal,
                'is_final_quiz'       => false,  // se marca abajo (último quiz)
                'graded_at'           => isset($it['gradedategraded']) ? (int) $it['gradedategraded'] : null,
            ];

            $parsed[] = $row;

            if ($isCourseTotal) {
                $total = $row;
            }
            if ($isQuiz) {
                $lastQuiz = $row;  // se asume que el último quiz que aparece es el final
            }
        }

        // Marcar último quiz como cuestionario final
        if ($lastQuiz !== null) {
            foreach ($parsed as &$row) {
                if ($row['id'] === $lastQuiz['id']) {
                    $row['is_final_quiz'] = true;
                    $finalQuiz = $row;
                    break;
                }
            }
            unset($row);
        }

        return [
            'items'      => $parsed,
            'total'      => $total,
            'final_quiz' => $finalQuiz,
        ];
    }

    /**
     * Devuelve para un usuario los datos de un curso concreto (lastaccess, progress, completed,
     * enablecompletion, category). Si el usuario no está enrolado en el curso, retorna null.
     *
     * @return array{lastaccess:int, progress:?float, completed:?bool, enablecompletion:bool, category:?int}|null
     */
    public function getUserCourseStats(int $moodleUserId, int $moodleCourseId): ?array
    {
        $cursos = $this->moodle->getUserCourses($moodleUserId);

        foreach ($cursos as $curso) {
            if ((int) ($curso['id'] ?? 0) !== $moodleCourseId) {
                continue;
            }

            return [
                'lastaccess'       => (int) ($curso['lastaccess'] ?? 0),
                'progress'         => isset($curso['progress']) ? (float) $curso['progress'] : null,
                'completed'        => isset($curso['completed']) ? (bool) $curso['completed'] : null,
                'enablecompletion' => (bool) ($curso['enablecompletion'] ?? false),
                'category'         => isset($curso['category']) ? (int) $curso['category'] : null,
            ];
        }

        return null;
    }
}
