<?php

namespace App\Services\Webcurso;

use App\Models\AccionFormativa;
use App\Models\Alumno;
use App\Models\EncuestaCalidad;
use App\Models\GrupoFormativo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Persistencia y vinculación de las respuestas del cuestionario de calidad FUNDAE.
 *
 * Dos entradas, una sola lógica de guardado (`guardarRespuesta`):
 *  - `importarDesdeArchivo()`  → Excel/CSV exportado del Microsoft Form (histórico).
 *  - comando `encuestas-calidad:leer-imap` → correo de Power Automate (tiempo real).
 *
 * El mapeo de columnas es dirigido por cabecera/código de pregunta (config
 * `encuesta_calidad.campos` y `.preguntas`), no por letras fijas, para tolerar
 * reordenaciones del export de Forms. Los códigos de bloque (1.1, 3.2, 10…)
 * pueden aparecer en medio de la cabecera (p.ej. "3.Duración y horario 3.1 …"),
 * por eso se buscan como token, no como prefijo.
 */
class EncuestaCalidadService
{
    /** Campos de `config.campos` que deben parsearse como fecha/hora. */
    protected const CAMPOS_FECHA = ['hora_inicio', 'hora_fin', 'fecha_cumplimentacion'];

    protected array $logs = [];
    protected int $procesados = 0;
    protected int $errores = 0;
    protected int $omitidos = 0;

    /** Índice lazy de nombres de alumno → id (solo cuando el nombre es único). */
    protected ?array $indiceNombres = null;

    // ─────────────────────────────────────────────────────────────────────────
    //  Importación de archivo (CSV / XLS / XLSX)
    // ─────────────────────────────────────────────────────────────────────────

    public function importarDesdeArchivo(UploadedFile $archivo): array
    {
        $this->logs = [];
        $this->procesados = $this->errores = $this->omitidos = 0;

        $this->log('info', '📋 Iniciando importación de ENCUESTAS DE CALIDAD...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            [$headers, $filas] = $this->leerHoja($archivo);

            if (empty($headers)) {
                $this->log('error', '❌ No se detectó cabecera en el archivo.');
                return $this->resultado();
            }

            $mapa = $this->construirMapaColumnas($headers);

            if ($mapa['satisfaccion'] === null) {
                $this->log('warning', '⚠️ No se localizó la columna del item 10 (grado de satisfacción). Revisa config encuesta_calidad.codigo_satisfaccion.');
            }

            foreach ($filas as $i => $fila) {
                try {
                    $datos = $this->filaADatos($fila, $mapa);

                    // Omitir filas totalmente vacías (sin email, nombre ni satisfacción)
                    if (empty($datos['alumno_email']) && empty($datos['alumno_nombre']) && $datos['satisfaccion_general'] === null) {
                        $this->omitidos++;
                        continue;
                    }

                    $this->guardarRespuesta($datos, 'import');
                    $this->procesados++;

                    if ($this->procesados <= 3) {
                        $this->log('success', "Encuesta {$this->procesados}: " . ($datos['alumno_email'] ?: $datos['alumno_nombre']) . " · satisfacción=" . ($datos['satisfaccion_general'] ?? '—'));
                    }
                } catch (\Throwable $e) {
                    $this->errores++;
                    $this->log('error', 'Error en fila ' . ($i + 2) . ': ' . $e->getMessage());
                }
            }

            $this->log('success', "✅ Encuestas procesadas: {$this->procesados}");
            if ($this->omitidos > 0) {
                $this->log('info', "ℹ️ Filas omitidas (vacías): {$this->omitidos}");
            }
        } catch (\Throwable $e) {
            $this->log('error', '❌ Error general: ' . $e->getMessage());
        }

        return $this->resultado();
    }

    /**
     * Lee la primera hoja / el CSV y devuelve [cabeceras, filas].
     *
     * @return array{0: array<int,string>, 1: array<int,array<int,string>>}
     */
    protected function leerHoja(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (in_array($extension, ['xls', 'xlsx'])) {
            return $this->leerHojaXls($archivo);
        }

        return $this->leerHojaCsv($archivo);
    }

    protected function leerHojaXls(UploadedFile $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = $sheet->getHighestDataRow();
        $maxCol = $sheet->getHighestDataColumn();

        $headers = [];
        foreach (range('A', $maxCol) as $col) {
            $headers[] = trim((string) ($sheet->getCell($col . '1')->getFormattedValue() ?? ''));
        }

        $filas = [];
        for ($row = 2; $row <= $maxRow; $row++) {
            $fila = [];
            foreach (range('A', $maxCol) as $col) {
                $fila[] = trim((string) ($sheet->getCell($col . $row)->getFormattedValue() ?? ''));
            }
            $filas[] = $fila;
        }

        return [$headers, $filas];
    }

    /**
     * Lee CSV respetando comillas y saltos de línea DENTRO de celdas (las
     * observaciones y varias cabeceras del Form vienen multilínea). Usa fgetcsv
     * sobre un stream, que es quote-aware.
     */
    protected function leerHojaCsv(UploadedFile $archivo): array
    {
        $contenido = file_get_contents($archivo->getRealPath());

        // Normalizar a UTF-8 (por si algún export viniera en Windows-1252)
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        // Quitar BOM
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

        // Autodetectar delimitador con la primera línea física
        $primeraLinea = strtok($contenido, "\r\n") ?: '';
        $delim = (substr_count($primeraLinea, ';') >= substr_count($primeraLinea, ',')) ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contenido);
        rewind($handle);

        $headers = [];
        $filas = [];
        $primera = true;
        while (($fila = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
            // fgetcsv devuelve [null] en líneas en blanco
            if ($fila === [null]) {
                continue;
            }
            $fila = array_map(fn ($v) => trim((string) ($v ?? '')), $fila);
            if ($primera) {
                $headers = $fila;
                $primera = false;
            } else {
                $filas[] = $fila;
            }
        }
        fclose($handle);

        return [$headers, $filas];
    }

    /**
     * Resuelve qué índice de columna corresponde a cada campo, item y a la
     * satisfacción, a partir de las cabeceras.
     *
     * @param array<int,string> $headers
     * @return array{campos: array<string,int>, items: array<string,int>, satisfaccion: ?int}
     */
    protected function construirMapaColumnas(array $headers): array
    {
        $norm = [];
        foreach ($headers as $idx => $h) {
            $norm[$idx] = $this->normalizarTexto($h);
        }
        $usados = [];

        $campos = [];

        // forms_id: columna cuya cabecera es exactamente "id"
        foreach ($norm as $idx => $h) {
            if ($h === 'id') {
                $campos['forms_id'] = $idx;
                $usados[$idx] = true;
                break;
            }
        }

        // Campos por alias (substring)
        foreach (config('encuesta_calidad.campos', []) as $campo => $aliases) {
            foreach ($aliases as $alias) {
                $aliasNorm = $this->normalizarTexto($alias);
                foreach ($norm as $idx => $h) {
                    if (isset($usados[$idx]) || $h === '') {
                        continue;
                    }
                    if (str_contains($h, $aliasNorm)) {
                        $campos[$campo] = $idx;
                        $usados[$idx] = true;
                        continue 3;
                    }
                }
            }
        }

        // Items de valoración por código (token dentro de la cabecera)
        $items = [];
        foreach (config('encuesta_calidad.preguntas', []) as $item => $codigo) {
            $idx = $this->buscarPorCodigo($norm, $codigo, $usados);
            if ($idx !== null) {
                $items[$item] = $idx;
                $usados[$idx] = true;
            }
        }

        // Satisfacción general (item 10 del cuestionario)
        $satisfaccion = $this->buscarPorCodigo($norm, (string) config('encuesta_calidad.codigo_satisfaccion', '10'), $usados);
        if ($satisfaccion !== null) {
            $usados[$satisfaccion] = true;
        }

        return ['campos' => $campos, 'items' => $items, 'satisfaccion' => $satisfaccion];
    }

    /**
     * Busca la primera columna (no usada) cuya cabecera contiene el código como
     * token: precedido de inicio o carácter no numérico y seguido de no numérico.
     * Así "3.1" matchea "3.Duración y horario 3.1 …" pero no "3.10" ni "13.1".
     *
     * @param array<int,string> $norm
     * @param array<int,bool> $usados
     */
    protected function buscarPorCodigo(array $norm, string $codigo, array $usados): ?int
    {
        $patron = '/(^|\D)' . preg_quote($codigo, '/') . '(\D|$)/';
        foreach ($norm as $idx => $h) {
            if (isset($usados[$idx]) || $h === '') {
                continue;
            }
            if (preg_match($patron, $h)) {
                return $idx;
            }
        }
        return null;
    }

    /**
     * Convierte una fila cruda en el array normalizado de datos del modelo.
     *
     * @param array<int,string> $fila
     * @param array{campos: array<string,int>, items: array<string,int>, satisfaccion: ?int} $mapa
     */
    protected function filaADatos(array $fila, array $mapa): array
    {
        $val = fn (?int $idx) => ($idx !== null && array_key_exists($idx, $fila)) ? trim((string) $fila[$idx]) : '';
        $c = $mapa['campos'];

        $datos = [
            'forms_id'              => $val($c['forms_id'] ?? null),
            'hora_inicio'          => $this->parsearFecha($val($c['hora_inicio'] ?? null)),
            'hora_fin'             => $this->parsearFecha($val($c['hora_fin'] ?? null)),
            'fecha_cumplimentacion' => $this->parsearFecha($val($c['fecha_cumplimentacion'] ?? null)),
            'alumno_nombre'         => $this->aTitleCase($val($c['alumno_nombre'] ?? null)),
            'alumno_email'          => $this->normalizarEmail($val($c['alumno_email'] ?? null)),
            'numero_accion'         => $this->soloEntero($val($c['numero_accion'] ?? null)),
            'numero_grupo'          => $val($c['numero_grupo'] ?? null) ?: null,
            'cif_empresa'           => strtoupper($val($c['cif_empresa'] ?? null)) ?: null,
            'denominacion_accion'   => $val($c['denominacion_accion'] ?? null) ?: null,
            'modalidad'             => $val($c['modalidad'] ?? null) ?: null,
            'edad_raw'              => $val($c['edad_raw'] ?? null) ?: null,
            'sexo'                  => $val($c['sexo'] ?? null) ?: null,
            'titulacion'            => $val($c['titulacion'] ?? null) ?: null,
            'lugar_trabajo'         => $val($c['lugar_trabajo'] ?? null) ?: null,
            'categoria_profesional' => $val($c['categoria_profesional'] ?? null) ?: null,
            'horario_curso'         => $val($c['horario_curso'] ?? null) ?: null,
            'porcentaje_jornada'    => $val($c['porcentaje_jornada'] ?? null) ?: null,
            'tamano_empresa'        => $val($c['tamano_empresa'] ?? null) ?: null,
            'observaciones'         => $val($c['observaciones'] ?? null) ?: null,
            'satisfaccion_general'  => $this->valorLikert($val($mapa['satisfaccion'] ?? null)),
        ];

        for ($i = 1; $i <= 19; $i++) {
            $item = 'item_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $datos[$item] = $this->valorLikert($val($mapa['items'][$item] ?? null));
        }

        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Persistencia + vinculación (compartida import / IMAP)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Guarda (idempotente por forms_id) una respuesta ya normalizada y resuelve
     * sus vínculos con alumno / grupo / acción / tutor.
     *
     * @param array $datos  Campos del modelo (los que falten se ignoran).
     * @param string $origen  'import' | 'power_automate'
     */
    public function guardarRespuesta(array $datos, string $origen): EncuestaCalidad
    {
        // forms_id estable: si no viene (raro), derivarlo de campos clave
        $formsId = trim((string) ($datos['forms_id'] ?? ''));
        if ($formsId === '') {
            $formsId = 'auto-' . md5(implode('|', [
                $datos['alumno_email'] ?? '',
                $datos['alumno_nombre'] ?? '',
                $datos['numero_accion'] ?? '',
                $datos['numero_grupo'] ?? '',
                (string) ($datos['fecha_cumplimentacion'] ?? ''),
            ]));
        }
        unset($datos['forms_id']);

        // Vínculo con alumno: por email y, si no hay, por nombre (fallback)
        $datos['alumno_id'] = $this->resolverAlumno($datos['alumno_email'] ?? null, $datos['alumno_nombre'] ?? null);

        // Curso al que pertenece la calificación (Nº Acción del Form o, si no viene,
        // por la fecha de cumplimentación contra los cursos del alumno)
        $datos = array_merge($datos, $this->resolverCurso($datos));

        $datos['origen'] = $origen;
        $datos['imported_at'] = now();

        return EncuestaCalidad::updateOrCreate(['forms_id' => $formsId], $datos);
    }

    /**
     * Vincula por email exacto; si falla y hay nombre, intenta por nombre único
     * (decisión de la usuaria: el Excel también trae el nombre del alumno).
     */
    public function resolverAlumno(?string $email, ?string $nombre = null): ?int
    {
        $email = $email ? mb_strtolower(trim($email)) : '';
        if ($email !== '' && $email !== 'anonymous') {
            $id = Alumno::whereRaw('LOWER(email) = ?', [$email])->value('id');
            if ($id) {
                return $id;
            }
        }

        if ($nombre) {
            return $this->resolverAlumnoPorNombre($nombre);
        }

        return null;
    }

    /**
     * Fallback: matchea por conjunto de tokens del nombre (tolera orden
     * "Apellido Nombre"). Solo vincula si el nombre es ÚNICO en la BD.
     */
    protected function resolverAlumnoPorNombre(string $nombre): ?int
    {
        $clave = $this->claveNombre($nombre);
        if ($clave === '') {
            return null;
        }

        if ($this->indiceNombres === null) {
            $this->indiceNombres = [];
            $conteo = [];
            Alumno::query()
                ->select('id', 'nombre', 'apellido1', 'apellido2')
                ->orderBy('id')
                ->chunk(1000, function ($alumnos) use (&$conteo) {
                    foreach ($alumnos as $a) {
                        $k = $this->claveNombre(trim("{$a->nombre} {$a->apellido1} {$a->apellido2}"));
                        if ($k === '') {
                            continue;
                        }
                        $conteo[$k] = ($conteo[$k] ?? 0) + 1;
                        // Guardamos el primer id; si luego resulta duplicado, se invalida
                        if (!isset($this->indiceNombres[$k])) {
                            $this->indiceNombres[$k] = $a->id;
                        }
                    }
                });
            // Invalidar nombres duplicados (ambiguos)
            foreach ($conteo as $k => $n) {
                if ($n > 1) {
                    $this->indiceNombres[$k] = null;
                }
            }
        }

        return $this->indiceNombres[$clave] ?? null;
    }

    /** Clave de nombre: tokens normalizados, ordenados y sin duplicados. */
    protected function claveNombre(string $nombre): string
    {
        $norm = $this->normalizarTexto($nombre);
        $tokens = array_values(array_filter(explode(' ', $norm), fn ($t) => mb_strlen($t) > 1));
        if (count($tokens) < 2) {
            return ''; // demasiado pobre para un match fiable (evita "e", "admin", etc.)
        }
        sort($tokens);
        return implode(' ', array_unique($tokens));
    }

    /**
     * Determina a qué curso pertenece la calificación y devuelve los campos
     * curso_* + FKs. Prioridad:
     *   1) Nº Acción/Grupo del formulario (lo más fiable, aunque casi nunca viene).
     *   2) Por fecha: entre los cursos del alumno, el que cumple inicio <= fecha <= fin.
     *
     * @return array campos curso_* + grupo_formativo_id/accion_formativa_id/tutor_id
     */
    public function resolverCurso(array $datos): array
    {
        $out = [
            'grupo_formativo_id'  => null,
            'accion_formativa_id' => null,
            'tutor_id'            => null,
            'curso_resuelto'      => null,
            'curso_tipo'          => null,
            'curso_fecha_inicio'  => null,
            'curso_fecha_fin'     => null,
            'curso_origen'        => null,
        ];

        // 1) Vía Nº Acción + Nº Grupo
        $vinc = $this->resolverGrupo($datos['numero_accion'] ?? null, $datos['numero_grupo'] ?? null);
        if ($vinc['accion_formativa_id']) {
            $accion = AccionFormativa::find($vinc['accion_formativa_id']);
            $out['accion_formativa_id'] = $vinc['accion_formativa_id'];
            $out['grupo_formativo_id']  = $vinc['grupo_formativo_id'];
            $out['tutor_id']            = $vinc['tutor_id'];
            $out['curso_tipo']          = 'fundae';
            $out['curso_origen']        = 'numero_accion';
            $out['curso_resuelto']      = ($datos['denominacion_accion'] ?? null) ?: ($accion?->denominacion);
            if ($vinc['grupo_formativo_id'] && ($g = GrupoFormativo::find($vinc['grupo_formativo_id']))) {
                $out['curso_fecha_inicio'] = $g->fecha_inicio;
                $out['curso_fecha_fin']    = $g->fecha_fin;
            }
            return $out;
        }

        // 2) Por fecha contra los cursos del alumno.
        //    Se consideran TODAS las fichas de alumno que comparten el email —
        //    la misma persona puede estar duplicada con NIFs distintos (FUNDAE vs
        //    legacy) y su historial de cursos quedar en la otra ficha.
        $fecha = $datos['fecha_cumplimentacion'] ?? null;
        if (is_string($fecha)) {
            $fecha = $this->parsearFecha($fecha);
        }

        $alumnoIds = [];
        if (!empty($datos['alumno_id'])) {
            $alumnoIds[] = (int) $datos['alumno_id'];
        }
        $email = $datos['alumno_email'] ?? null;
        if ($email) {
            $alumnoIds = array_merge($alumnoIds, Alumno::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->pluck('id')->all());
        }
        $alumnoIds = array_values(array_unique(array_map('intval', $alumnoIds)));

        if ($alumnoIds && $fecha instanceof Carbon) {
            $cand = $this->resolverCursoPorFecha($alumnoIds, $fecha, $datos['denominacion_accion'] ?? null);
            if ($cand) {
                return array_merge($out, $cand, ['curso_origen' => 'fecha_alumno']);
            }
        }

        return $out;
    }

    /**
     * Entre los cursos del alumno (4 fuentes), devuelve el que contiene la fecha
     * (inicio <= fecha <= fin). Si hay varios, desempata por coincidencia del
     * texto de denominación y, en su defecto, por la fecha de fin más cercana.
     */
    public function resolverCursoPorFecha(int|array $alumnoIds, Carbon $fecha, ?string $textoDenom): ?array
    {
        $cands = $this->recolectarCursos((array) $alumnoIds);
        if (empty($cands)) {
            return null;
        }

        // Solo dentro de las fechas exactas (inicio <= fecha <= fin)
        $dentro = array_values(array_filter($cands, fn ($c) => $fecha->betweenIncluded($c['curso_fecha_inicio'], $c['curso_fecha_fin'])));

        if (empty($dentro)) {
            return null;
        }
        if (count($dentro) === 1) {
            return $dentro[0];
        }

        // Desempate: (1) mayor coincidencia de texto, (2) el que tiene nombre de
        // curso — un bonificado sin nombre y un legacy con nombre suelen ser el
        // MISMO curso real, y queremos el nombre —, (3) fin más cercano.
        usort($dentro, function ($a, $b) use ($fecha, $textoDenom) {
            $sa = $this->scoreTexto($textoDenom, $a['curso_resuelto']);
            $sb = $this->scoreTexto($textoDenom, $b['curso_resuelto']);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $na = $a['curso_resuelto'] ? 1 : 0;
            $nb = $b['curso_resuelto'] ? 1 : 0;
            if ($na !== $nb) {
                return $nb <=> $na;
            }
            return $a['curso_fecha_fin']->diffInDays($fecha) <=> $b['curso_fecha_fin']->diffInDays($fecha);
        });

        return $dentro[0];
    }

    /**
     * Reúne TODOS los cursos (4 fuentes) de las fichas de alumno indicadas.
     * Cada curso: curso_tipo, curso_resuelto (nombre), curso_fecha_inicio/fin + FKs.
     *
     * @return array<int,array>
     */
    protected function recolectarCursos(array $alumnoIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $alumnoIds)));
        if (empty($ids)) {
            return [];
        }

        $alumnos = Alumno::with([
            'gruposFormativos.accionFormativa', 'gruposFormativos.tutor',
            'participantesBonificados', 'matriculasAutonomas.accionFormativa', 'cursosLegacy',
        ])->whereIn('id', $ids)->get();

        $cands = [];
        $add = function ($tipo, $denom, $ini, $fin, $gId = null, $aId = null, $tId = null) use (&$cands) {
            if ($ini && $fin) {
                $cands[] = [
                    'curso_tipo' => $tipo, 'curso_resuelto' => $denom ?: null,
                    'curso_fecha_inicio' => $ini, 'curso_fecha_fin' => $fin,
                    'grupo_formativo_id' => $gId, 'accion_formativa_id' => $aId, 'tutor_id' => $tId,
                ];
            }
        };

        foreach ($alumnos as $alumno) {
            foreach ($alumno->gruposFormativos as $g) {
                $add('fundae', $g->accionFormativa?->denominacion, $g->fecha_inicio, $g->fecha_fin, $g->id, $g->accion_formativa_id, $g->tutor_id);
            }
            foreach ($alumno->participantesBonificados as $p) {
                $add('bonificado', null, $p->fecha_inicio, $p->fecha_fin);
            }
            foreach ($alumno->matriculasAutonomas as $m) {
                $add('autonomo', $m->accionFormativa?->denominacion, $m->fecha_inicio, $m->fecha_fin, null, $m->accion_formativa_id, $m->tutor_id);
            }
            foreach ($alumno->cursosLegacy as $l) {
                $add('legacy', $l->curso_titulo, $l->fecha_inicio, $l->fecha_fin);
            }
        }

        return $cands;
    }

    /**
     * Historial de cursos de un alumno (para el modal de la pantalla de encuestas).
     * Incluye las fichas gemelas que comparten email. Ordenado por inicio desc y
     * deduplicado por (tipo|nombre|inicio|fin).
     *
     * @return array<int,array>
     */
    public function historialCursos(int|array $alumnoIds): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) $alumnoIds)));

        // Ampliar con fichas gemelas que comparten email
        $emails = Alumno::whereIn('id', $ids)->whereNotNull('email')->where('email', '!=', '')->pluck('email')->all();
        if (!empty($emails)) {
            $mas = Alumno::whereIn('email', $emails)->pluck('id')->all();
            $ids = array_values(array_unique(array_merge($ids, array_map('intval', $mas))));
        }

        $cursos = $this->recolectarCursos($ids);

        // Fechas que ya tienen un curso CON nombre (para descartar el gemelo sin nombre)
        $fechasConNombre = [];
        foreach ($cursos as $c) {
            if ($c['curso_resuelto']) {
                $fechasConNombre[$c['curso_fecha_inicio']->format('Y-m-d') . '|' . $c['curso_fecha_fin']->format('Y-m-d')] = true;
            }
        }

        // Deduplicar: misma matrícula puede venir como bonificado (sin nombre) y
        // legacy (con nombre). Nos quedamos con la que tiene nombre.
        $vistos = [];
        $unicos = [];
        foreach ($cursos as $c) {
            $rangoFechas = $c['curso_fecha_inicio']->format('Y-m-d') . '|' . $c['curso_fecha_fin']->format('Y-m-d');
            if (empty($c['curso_resuelto']) && isset($fechasConNombre[$rangoFechas])) {
                continue; // gemelo sin nombre de un curso ya listado con nombre
            }
            $clave = $c['curso_tipo'] . '|' . mb_strtolower((string) $c['curso_resuelto']) . '|' . $rangoFechas;
            if (!isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $unicos[] = $c;
            }
        }

        usort($unicos, fn ($a, $b) => $b['curso_fecha_inicio']->getTimestamp() <=> $a['curso_fecha_inicio']->getTimestamp());

        return $unicos;
    }

    /** Nº de tokens significativos (>3 letras) compartidos entre dos textos. */
    protected function scoreTexto(?string $a, ?string $b): int
    {
        if (!$a || !$b) {
            return 0;
        }
        $ta = array_filter(explode(' ', $this->normalizarTexto($a)), fn ($t) => mb_strlen($t) > 3);
        $tb = array_filter(explode(' ', $this->normalizarTexto($b)), fn ($t) => mb_strlen($t) > 3);
        return count(array_intersect($ta, $tb));
    }

    /**
     * Resuelve acción/grupo/tutor por Nº Acción + Nº Grupo FUNDAE.
     *
     * @return array{grupo_formativo_id: ?int, accion_formativa_id: ?int, tutor_id: ?int}
     */
    public function resolverGrupo($numeroAccion, $numeroGrupo): array
    {
        $out = ['grupo_formativo_id' => null, 'accion_formativa_id' => null, 'tutor_id' => null];

        if (empty($numeroAccion)) {
            return $out;
        }

        $accion = AccionFormativa::where('numero_accion', $numeroAccion)->first();
        if (!$accion) {
            return $out;
        }
        $out['accion_formativa_id'] = $accion->id;

        if ($numeroGrupo !== null && $numeroGrupo !== '') {
            $grupo = GrupoFormativo::where('accion_formativa_id', $accion->id)
                ->where('id_grupo_fundae', (int) $numeroGrupo)
                ->first();
            if ($grupo) {
                $out['grupo_formativo_id'] = $grupo->id;
                $out['tutor_id'] = $grupo->tutor_id;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Normalizaciones
    // ─────────────────────────────────────────────────────────────────────────

    /** Extrae el valor Likert 1-4 de una celda ("4", "4;De acuerdo", ...). */
    protected function valorLikert(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[1-4]/', $raw, $m)) {
            return (int) $m[0];
        }
        return null;
    }

    protected function soloEntero(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }
        $d = preg_replace('/\D/', '', $raw);
        return $d === '' ? null : (int) $d;
    }

    protected function normalizarEmail(string $raw): ?string
    {
        $raw = mb_strtolower(trim($raw));
        $raw = str_replace(' ', '', $raw);
        if ($raw === '' || $raw === 'anonymous') {
            return null;
        }
        return $raw;
    }

    /** Normaliza texto: minúsculas, sin tildes ni ordinales, espacios colapsados. */
    protected function normalizarTexto(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'º' => '', 'ª' => '', '°' => '',
        ]);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /** Title Case (los PDFs/Forms suelen llegar en MAYÚSCULAS). */
    protected function aTitleCase(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Parser de fecha tolerante. La fuente (Forms EN) usa formato americano M/D/Y.
     * Devuelve Carbon o null.
     */
    public function parsearFecha(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        // Serial de Excel
        if (is_numeric($raw) && $raw > 25000 && $raw < 60000) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw));
            } catch (\Throwable $e) {
                // sigue a los formatos de texto
            }
        }

        // Americano primero (mes/día), luego europeo e ISO.
        // Carbon LANZA excepción cuando el formato no cuaja (no devuelve false),
        // por eso cada intento va en su try/catch.
        $formatos = ['n/j/Y G:i:s', 'n/j/Y H:i:s', 'n/j/y G:i:s', 'n/j/y H:i:s', 'n/j/Y', 'n/j/y', 'j/n/Y H:i:s', 'j/n/Y', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formatos as $f) {
            try {
                return Carbon::createFromFormat('!' . $f, $raw);
            } catch (\Throwable $e) {
                // probar siguiente formato
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Utilidades de resultado
    // ─────────────────────────────────────────────────────────────────────────

    protected function log(string $tipo, string $mensaje): void
    {
        $this->logs[] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    protected function resultado(): array
    {
        return [
            'logs'       => $this->logs,
            'procesados' => $this->procesados,
            'errores'    => $this->errores,
            'omitidos'   => $this->omitidos,
        ];
    }
}
