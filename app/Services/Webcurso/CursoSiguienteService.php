<?php

namespace App\Services\Webcurso;

use App\Models\AccionFormativa;
use App\Models\Alumno;
use App\Models\CursoSiguiente;
use App\Models\Empresa;
use App\Models\EncuestaCalidad;
use App\Models\MoodleCurso;
use App\Models\MoodleMatriculaIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Curso siguiente" a partir de las encuestas de calidad:
 *  - `sugerir()`  propone parejas origen → destino por nivel en el nombre
 *    (Nivel 1→2, básico→intermedio→avanzado, I→II). Quedan pendientes de confirmar.
 *  - `oportunidades()` lista a quién ofrecérselo: alumnos que valoraron alto su
 *    curso, lo recomendarían y aún no han hecho el siguiente.
 */
class CursoSiguienteService
{
    /**
     * Separa una clave de curso en [base, nivel]. Nivel 0 = sin nivel explícito.
     *
     * @return array{0: string, 1: int}
     */
    public static function nivel(string $clave): array
    {
        $nivel = 0;
        $base = $clave;

        $patrones = [
            '/\bnivel\s*(\d+)\b/'                                    => null,
            '/\b(basico|basica|inicial|iniciacion|introduccion)\b/' => 1,
            '/\b(intermedio|intermedia)\b/'                         => 2,
            '/\b(avanzado|avanzada|experto|experta|superior)\b/'    => 3,
        ];

        foreach ($patrones as $patron => $valor) {
            if (preg_match($patron, $clave, $m)) {
                $nivel = $valor ?? (int) $m[1];
                $base = preg_replace($patron, ' ', $clave);
                break;
            }
        }

        if ($nivel === 0 && preg_match('/\s(i{1,3}|iv)$/', $clave, $m)) {
            $nivel = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4][$m[1]];
            $base = substr($clave, 0, -strlen($m[0]));
        }

        return [trim(preg_replace('/\s+/', ' ', $base)), $nivel];
    }

    /**
     * Crea propuestas automáticas (inactivas, pendientes de confirmar) para cada
     * curso que aparece en las encuestas. No toca las parejas ya existentes.
     *
     * Busca destino en las dos fuentes: las ACCIONES FORMATIVAS FUNDAE (los cursos
     * que se imparten hoy) y el catálogo WEB de webcurso.es (`moodle_cursos`).
     * Si un mismo curso está en ambas se propone una sola vez, como acción formativa.
     *
     * @return int Nº de propuestas nuevas
     */
    public function sugerir(): int
    {
        $web = MoodleCurso::with('categoria:id,nombre')->get(['id', 'titulo', 'moodle_categoria_id']);

        // Índices del catálogo web: por clave exacta y por categoría con nivel
        // ("ChatGPT Nivel 2" → base "chatgpt", nivel 2).
        $webPorClave = [];
        $porCategoria = [];
        $candidatos = [];
        foreach ($web as $c) {
            $clave = CursoSiguiente::claveCurso($c->titulo);
            $webPorClave[$clave][] = $c;
            if ($cat = self::nivelCategoria($c->categoria?->nombre)) {
                $porCategoria[$cat[0]][$cat[1]][] = $c->id;
            }
            $candidatos[] = ['tipo' => 'web', 'id' => $c->id, 'clave' => $clave];
        }
        foreach (AccionFormativa::activas()->get(['id', 'denominacion']) as $a) {
            $candidatos[] = ['tipo' => 'accion', 'id' => $a->id, 'clave' => CursoSiguiente::claveCurso($a->denominacion)];
        }
        $claveWeb = $web->mapWithKeys(fn ($c) => [$c->id => CursoSiguiente::claveCurso($c->titulo)]);

        $nuevas = 0;
        foreach ($this->cursosDeEncuestas() as $clave => $nombre) {
            $destinos = array_merge(
                array_map(fn ($id) => ['tipo' => 'web', 'id' => $id, 'clave' => $claveWeb[$id]],
                    $this->destinosPorCategoria($clave, $webPorClave, $porCategoria)),
                $this->destinosPorTitulo($clave, $candidatos),
            );

            // Un mismo curso (misma clave) una sola vez, preferentemente como acción formativa
            $unicos = collect($destinos)
                ->reject(fn ($d) => $d['clave'] === $clave)
                ->sortBy(fn ($d) => $d['tipo'] === 'accion' ? 0 : 1)
                ->unique('clave')
                ->take(8)
                ->values();

            foreach ($unicos as $i => $d) {
                $col = $d['tipo'] === 'accion' ? 'accion_formativa_id' : 'moodle_curso_id';
                $pareja = CursoSiguiente::firstOrCreate(
                    ['curso_origen_clave' => $clave, $col => $d['id']],
                    ['curso_origen_nombre' => $nombre, 'prioridad' => $i + 1, 'activo' => false, 'sugerido_auto' => true],
                );
                if ($pareja->wasRecentlyCreated) {
                    $nuevas++;
                }
            }
        }

        return $nuevas;
    }

    /**
     * Estrategia 1 — el catálogo de webcurso.es organiza la progresión en las
     * CATEGORÍAS ("ChatGPT Nivel 1/2/3", "Power Bi nivel 1/2/3"), no en el título.
     * Si el curso hecho está en "X Nivel N", se proponen los de "X Nivel N+1".
     *
     * @return array<int,int> ids de moodle_cursos
     */
    protected function destinosPorCategoria(string $clave, array $webPorClave, array $porCategoria): array
    {
        foreach ($webPorClave[$clave] ?? [] as $origen) {
            $cat = self::nivelCategoria($origen->categoria?->nombre);
            if (!$cat) {
                continue;
            }
            [$base, $nivel] = $cat;
            $niveles = array_filter(array_keys($porCategoria[$base] ?? []), fn ($n) => $n > $nivel);
            if ($niveles) {
                return $porCategoria[$base][min($niveles)];
            }
        }

        return [];
    }

    /**
     * Estrategia 2 — el mismo curso con un nivel mayor en el título, en acciones
     * formativas y catálogo web: "Claude Code" → "Claude Code Avanzado Agentes…",
     * "ChatGPT Inicial" → "ChatGPT Intermedio: …". El destino debe empezar por el
     * nombre base del origen. Un curso sin nivel cuenta como el primero. Se
     * devuelven todos los del nivel inmediatamente superior.
     *
     * @param array<int, array{tipo: string, id: int, clave: string}> $candidatos
     * @return array<int, array{tipo: string, id: int, clave: string}>
     */
    protected function destinosPorTitulo(string $clave, array $candidatos): array
    {
        [$base, $nivel] = self::nivel($clave);
        if ($base === '') {
            return [];
        }

        $mayores = collect($candidatos)
            ->map(fn ($c) => $c + ['nivel' => self::nivel($c['clave'])[1], 'base' => self::nivel($c['clave'])[0]])
            ->filter(fn ($c) => ($c['base'] === $base || str_starts_with($c['base'], $base . ' ')) && $c['nivel'] > max($nivel, 1));

        if ($mayores->isEmpty()) {
            return [];
        }

        return $mayores->where('nivel', $mayores->min('nivel'))
            ->map(fn ($c) => ['tipo' => $c['tipo'], 'id' => $c['id'], 'clave' => $c['clave']])
            ->values()->all();
    }

    /**
     * Ficha unificada del curso que se ofrece, venga de una acción formativa o
     * del catálogo web. Si la acción tiene gemelo en la web (misma clave), toma de
     * ahí el enlace y el precio; si no, el coste se estima con el módulo FUNDAE
     * de teleformación (horas × €/h), que es lo que consumiría del crédito.
     */
    public function destino(CursoSiguiente $s, array $webPorClave): ?object
    {
        if ($s->accionFormativa) {
            $a = $s->accionFormativa;
            $gemelo = $webPorClave[CursoSiguiente::claveCurso($a->denominacion)] ?? null;
            $estimado = $gemelo?->precio === null;

            return (object) [
                'id'              => 'a:' . $a->id,
                'tipo'            => 'accion',
                'titulo'          => $a->denominacion_limpia,
                'numero_accion'   => $a->numero_accion,
                'horas'           => $a->horas,
                'precio'          => $estimado
                    ? ($a->horas ? $a->horas * (float) config('encuesta_calidad.modulo_teleformacion_hora', 7) : null)
                    : (float) $gemelo->precio,
                'precio_estimado' => $estimado,
                'url'             => $gemelo?->url,
            ];
        }

        if ($c = $s->cursoDestino) {
            return (object) [
                'id'              => 'w:' . $c->id,
                'tipo'            => 'web',
                'titulo'          => $c->titulo,
                'numero_accion'   => null,
                'horas'           => $c->horas,
                'precio'          => $c->precio !== null ? (float) $c->precio : null,
                'precio_estimado' => false,
                'url'             => $c->url,
            ];
        }

        return null;
    }

    /** Catálogo web indexado por clave (para enlazar acciones con su ficha web). */
    public function webPorClave(): array
    {
        return MoodleCurso::query()->get(['id', 'titulo', 'precio', 'horas', 'url'])
            ->keyBy(fn ($c) => CursoSiguiente::claveCurso($c->titulo))
            ->all();
    }

    /**
     * "ChatGPT Nivel 2" → ['chatgpt', 2]; categorías sin nivel → null.
     *
     * @return array{0: string, 1: int}|null
     */
    public static function nivelCategoria(?string $categoria): ?array
    {
        $clave = CursoSiguiente::claveCurso($categoria);
        if (!preg_match('/^(.*)\bnivel\s*(\d+)\b/', $clave, $m) || trim($m[1]) === '') {
            return null;
        }

        return [trim($m[1]), (int) $m[2]];
    }

    /**
     * Cursos distintos presentes en las encuestas: clave => nombre representativo.
     *
     * @return array<string,string>
     */
    public function cursosDeEncuestas(): array
    {
        $out = [];
        EncuestaCalidad::query()
            ->whereNotNull('curso_resuelto')->where('curso_resuelto', '!=', '')
            ->distinct()->orderBy('curso_resuelto')
            ->pluck('curso_resuelto')
            ->each(function ($nombre) use (&$out) {
                $clave = CursoSiguiente::claveCurso($nombre);
                if ($clave !== '' && !isset($out[$clave])) {
                    $out[$clave] = $nombre;
                }
            });

        return $out;
    }

    /**
     * Encuestas que son oportunidad de ofrecer el curso siguiente.
     *
     * Criterios: nota >= umbral, "¿lo recomendaría?" = Sí o sin dato (el Form no
     * lo preguntaba), alumno identificable, curso con siguiente ACTIVO y que el
     * alumno no haya hecho ya ese siguiente (en ninguna de sus fuentes).
     * Si respondió varias veces por el mismo curso, se queda la más reciente.
     *
     * @param Builder $query Encuestas ya filtradas por la pantalla
     * @return Collection<int, array{encuesta: EncuestaCalidad, destinos: Collection}>
     */
    public function oportunidades(Builder $query): Collection
    {
        // Destinos activos ya normalizados (acción formativa o catálogo web), sin
        // repetir el mismo curso aunque se haya añadido por las dos fuentes.
        $webPorClave = $this->webPorClave();
        $siguientes = CursoSiguiente::activos()->with(['cursoDestino', 'accionFormativa'])
            ->orderBy('prioridad')->get()
            ->groupBy('curso_origen_clave')
            ->map(fn ($grupo) => $grupo->map(fn ($s) => $this->destino($s, $webPorClave))
                ->filter()
                ->unique(fn ($d) => CursoSiguiente::claveCurso($d->titulo))
                ->values())
            ->filter(fn ($grupo) => $grupo->isNotEmpty());

        if ($siguientes->isEmpty()) {
            return collect();
        }

        $q = (clone $query)
            ->where('satisfaccion_general', '>=', (int) config('encuesta_calidad.oportunidad_nota_minima', 4))
            ->where(fn ($w) => $w->whereNull('sino_recomendaria')->orWhere('sino_recomendaria', 1))
            ->where(fn ($w) => $w->whereNotNull('alumno_id')->orWhere(fn ($e) => $e->whereNotNull('alumno_email')->where('alumno_email', '!=', '')))
            ->whereNotNull('curso_resuelto');

        // Solo los cursos que tienen siguiente (la clave se calcula en PHP)
        $nombres = (clone $q)->reorder()->distinct()->pluck('curso_resuelto')
            ->filter(fn ($n) => $siguientes->has(CursoSiguiente::claveCurso($n)))
            ->values()->all();
        if (empty($nombres)) {
            return collect();
        }

        $encuestas = $q->whereIn('curso_resuelto', $nombres)
            ->with(['alumno:id,nombre,apellido1,apellido2,email,telefono,empresa_id,empresa_texto', 'alumno.empresa:id,cif,razon_social,credito_disponible'])
            ->orderByDesc('fecha_cumplimentacion')->orderByDesc('id')
            ->get();

        $historial = $this->historialPorPersona($encuestas);

        // Empresa por CIF de la encuesta, para quien no tiene ficha con empresa
        $empresasPorCif = Empresa::query()
            ->whereIn('cif', $encuestas->pluck('cif_empresa')->filter()->map(fn ($c) => strtoupper(trim($c)))->unique()->values()->all() ?: [''])
            ->get(['id', 'cif', 'razon_social', 'credito_disponible'])
            ->keyBy(fn ($e) => strtoupper($e->cif));

        $vistos = [];
        $out = collect();
        foreach ($encuestas as $e) {
            $clave = CursoSiguiente::claveCurso($e->curso_resuelto);
            $persona = $this->clavePersona($e);
            if (isset($vistos[$persona . '|' . $clave])) {
                continue; // ya está la respuesta más reciente de ese alumno a ese curso
            }
            $vistos[$persona . '|' . $clave] = true;

            $hechos = $historial[$persona] ?? [];
            $destinos = $siguientes[$clave]
                ->reject(fn ($c) => isset($hechos[CursoSiguiente::claveCurso($c->titulo)]))
                ->values();

            if ($destinos->isNotEmpty()) {
                $empresa = $e->alumno?->empresa ?? $empresasPorCif[strtoupper(trim((string) $e->cif_empresa))] ?? null;
                $saldo = $empresa ? (float) $empresa->credito_disponible : null;

                $out->push([
                    'encuesta' => $e,
                    'destinos' => $destinos,
                    // Saldo FUNDAE de la empresa (null = particular / externa / sin empresa
                    // registrada: no calculamos su crédito).
                    'empresa'  => $empresa,
                    'saldo'    => $saldo,
                    // ¿El saldo cubre el precio de cada curso sugerido? (id => bool|null)
                    'cubre'    => $destinos->mapWithKeys(fn ($c) => [
                        $c->id => $saldo === null || $c->precio === null ? null : $saldo >= $c->precio,
                    ])->all(),
                ]);
            }
        }

        return $out;
    }

    /** Identificador de persona: email si lo hay, si no el alumno. */
    protected function clavePersona(EncuestaCalidad $e): string
    {
        $email = mb_strtolower(trim((string) ($e->alumno_email ?: $e->alumno?->email)));

        return $email !== '' ? 'e:' . $email : 'a:' . $e->alumno_id;
    }

    /**
     * Claves de los cursos que ya hizo cada persona, reunidas en bloque de:
     * otras encuestas, el índice de matrículas de Moodle y las fuentes del Panel
     * (grupos, matrículas individuales y legacy) de todas sus fichas.
     *
     * @return array<string, array<string,bool>>
     */
    protected function historialPorPersona(Collection $encuestas): array
    {
        $hist = [];
        $marcar = function (string $persona, ?string $curso) use (&$hist) {
            $k = CursoSiguiente::claveCurso($curso);
            if ($k !== '') {
                $hist[$persona][$k] = true;
            }
        };

        $emails = $encuestas->map(fn ($e) => mb_strtolower(trim((string) ($e->alumno_email ?: $e->alumno?->email))))
            ->filter()->unique()->values()->all();
        $alumnoIds = $encuestas->pluck('alumno_id')->filter()->unique()->values()->all();

        if ($emails) {
            EncuestaCalidad::query()->whereIn('alumno_email', $emails)->whereNotNull('curso_resuelto')
                ->get(['alumno_email', 'curso_resuelto'])
                ->each(fn ($r) => $marcar('e:' . mb_strtolower($r->alumno_email), $r->curso_resuelto));

            MoodleMatriculaIndex::query()->whereIn('email', $emails)
                ->get(['email', 'curso_fullname'])
                ->each(fn ($r) => $marcar('e:' . mb_strtolower($r->email), $r->curso_fullname));
        }

        $alumnos = Alumno::query()
            ->with(['gruposFormativos.accionFormativa', 'matriculasAutonomas.accionFormativa', 'cursosLegacy'])
            ->where(function ($w) use ($alumnoIds, $emails) {
                $w->whereIn('id', $alumnoIds ?: [0]);
                if ($emails) {
                    $w->orWhereIn('email', $emails);
                }
            })
            ->get();

        foreach ($alumnos as $a) {
            $personas = array_filter([
                $a->email ? 'e:' . mb_strtolower(trim($a->email)) : null,
                'a:' . $a->id,
            ]);
            $cursos = collect()
                ->merge($a->gruposFormativos->map(fn ($g) => $g->accionFormativa?->denominacion))
                ->merge($a->matriculasAutonomas->map(fn ($m) => $m->accionFormativa?->denominacion))
                ->merge($a->cursosLegacy->pluck('curso_titulo'));

            foreach ($personas as $p) {
                foreach ($cursos as $c) {
                    $marcar($p, $c);
                }
            }
        }

        return $hist;
    }
}
