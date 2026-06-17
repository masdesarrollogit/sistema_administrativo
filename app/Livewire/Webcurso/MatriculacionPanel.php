<?php

namespace App\Livewire\Webcurso;

use App\Models\AccionFormativa;
use App\Models\Alumno;
use App\Models\Candidato;
use App\Models\Grupo;
use App\Models\GrupoFormativo;
use App\Models\MatriculaAutonoma;
use App\Models\Tutor;
use Carbon\Carbon;
use App\Services\Webcurso\CsvImportService;
use App\Services\Webcurso\FundaeXmlService;
use App\Services\Webcurso\PdfFichaInscripcionParser;
use App\Services\Webcurso\PdfNotificacionFundaeParser;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class MatriculacionPanel extends Component
{
    use WithFileUploads;

    public Candidato $candidato;

    // ─── Gestión de alumnos ───
    public bool $mostrarFormAlumno = true;
    public bool $mostrarImportAlumnos = false;
    public bool $mostrarSubidaPdf = false;
    public $archivoAlumnos;
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $archivosPdf = [];
    /** @var array<int, array<string, mixed>>  Cada entrada = un PDF parseado pendiente de confirmar */
    public array $previewPdfs = [];
    public string $alumnoNombre = '';
    public string $alumnoApellido1 = '';
    public string $alumnoApellido2 = '';
    public string $alumnoNif = '';
    public string $alumnoEmail = '';
    public string $alumnoTelefono = '';

    // ─── Crear/seleccionar grupo ───
    public bool $mostrarFormGrupo = false;
    public ?int $grupoSeleccionadoId = null;
    public string $busquedaAccion = '';
    public array $resultadosAccion = [];
    public ?int $nuevaAccionFormativaId = null;
    public ?int $nuevoTutorId = null;
    public string $nuevoTramo = 'tramo_2';
    public string $nuevaFechaInicio = '';
    public string $nuevaFechaFin = '';
    public string $nuevasDias = '';
    public string $nuevaDescripcion = '';
    public int $nuevaJornadaLaboral = 1;

    // ─── Añadir alumnos al grupo ───
    public array $alumnosParaAgregar = [];

    // ─── Editar grupo ───
    public ?int $editandoGrupoId = null;
    public ?int $editGrupoTutorId = null;
    public string $editGrupoTramo = 'tramo_2';
    public string $editGrupoFechaInicio = '';
    public string $editGrupoFechaFin = '';
    public string $editDias = '';
    public int $editGrupoJornada = 1;
    public string $editGrupoDescripcion = '';

    // ─── Editar alumno ───
    public ?int $editandoAlumnoId = null;
    public string $editAlumnoNombre = '';
    public string $editAlumnoApellido1 = '';
    public string $editAlumnoApellido2 = '';
    public string $editAlumnoNif = '';
    public string $editAlumnoEmail = '';
    public string $editAlumnoTelefono = '';

    // ─── Notificación PDF FUNDAE ───
    public ?int $pdfGrupoId = null;
    public $pdfNotificacion;

    // ─── Matriculación Moodle ───
    public array $moodleCursosPorGrupo = []; // [grupoId => moodle_course_id seleccionado]

    // ─── Autónomos (2x1) ───
    public bool $mostrarFormAutonomo = false;
    public string $autonomoBusquedaAccion = '';
    public array $autonomoResultadosAccion = [];
    public ?int $autonomoAccionFormativaId = null;
    public ?int $autonomoTutorId = null;
    public ?int $autonomoAlumnoId = null;
    public string $autonomoFechaInicio = '';
    public string $autonomoFechaFin = '';
    public string $autonomoDias = '';
    // Nuevo alumno autónomo inline
    public bool $autonomoNuevoAlumno = false;
    public string $autonomoNombre = '';
    public string $autonomoApellido1 = '';
    public string $autonomoApellido2 = '';
    public string $autonomoNif = '';
    public string $autonomoEmail = '';
    public string $autonomoTelefono = '';
    public array $moodleCursosPorAutonomo = []; // [matriculaId => moodle_course_id]

    public function mount(Candidato $candidato): void
    {
        $this->candidato = $candidato;
    }

    // ═══════════════════════════════════════════════
    //  GESTIÓN DE ALUMNOS
    // ═══════════════════════════════════════════════

    public function abrirFormAlumno(): void
    {
        $this->resetFormAlumno();
        $this->mostrarFormAlumno = true;
    }

    public function guardarAlumno(): void
    {
        $this->validate([
            'alumnoNombre'    => 'required|string|max:255',
            'alumnoApellido1' => 'required|string|max:255',
            'alumnoApellido2' => 'nullable|string|max:255',
            'alumnoNif'       => 'required|string|max:15|unique:alumnos,nif',
            'alumnoEmail'     => 'required|email|max:255|unique:alumnos,email',
            'alumnoTelefono'  => 'nullable|string|max:20',
        ], [
            'alumnoNif.unique'     => 'Ya existe un alumno con ese NIF.',
            'alumnoEmail.required' => 'El correo electrónico es obligatorio.',
            'alumnoEmail.unique'   => 'Ya existe un alumno con ese correo electrónico.',
        ]);

        if (!$this->candidato->empresa_id) {
            session()->flash('error-matricula', 'El candidato no tiene empresa asociada.');
            return;
        }

        Alumno::updateOrCreate(
            ['nif' => $this->alumnoNif, 'empresa_id' => $this->candidato->empresa_id],
            [
                'nombre'    => $this->aTitleCase($this->alumnoNombre),
                'apellido1' => $this->aTitleCase($this->alumnoApellido1),
                'apellido2' => $this->alumnoApellido2 ? $this->aTitleCase($this->alumnoApellido2) : null,
                'email'     => $this->alumnoEmail ? mb_strtolower(trim($this->alumnoEmail)) : null,
                'telefono'  => $this->alumnoTelefono ?: null,
            ]
        );

        $this->mostrarFormAlumno = false;
        $this->resetFormAlumno();
        session()->flash('message-matricula', 'Alumno guardado correctamente.');
    }

    protected function resetFormAlumno(): void
    {
        $this->reset(['alumnoNombre', 'alumnoApellido1', 'alumnoApellido2', 'alumnoNif', 'alumnoEmail', 'alumnoTelefono']);
        $this->resetValidation();
    }

    public function importarAlumnosDesdeArchivo(): void
    {
        $this->validate([
            'archivoAlumnos' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        if (!$this->candidato->empresa_id) {
            session()->flash('error-matricula', 'El candidato no tiene empresa asociada.');
            return;
        }

        $creados = 0;
        $errores = [];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->archivoAlumnos->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $maxRow      = $sheet->getHighestRow();

            // Localizar la fila de cabecera buscando la celda que empiece por "NIF"
            // (evitar falsos positivos como "Bonificada" que contiene "nif")
            $headerRow = null;
            for ($r = 1; $r <= min($maxRow, 20); $r++) {
                for ($c = 1; $c <= 27; $c++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $v   = trim($sheet->getCell($col . $r)->getFormattedValue());
                    if (preg_match('/^NIF/i', $v)) {
                        $headerRow = $r;
                        break 2;
                    }
                }
            }

            if (!$headerRow) {
                session()->flash('error-matricula', 'No se encontró la cabecera en el archivo. Verifica que sea la Ficha de Inscripción de WebCurso.');
                return;
            }

            // Mapear todas las columnas del header
            $map = [];
            for ($c = 1; $c <= 27; $c++) {
                $col   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $label = mb_strtolower(trim($sheet->getCell($col . $headerRow)->getFormattedValue()));
                if ($label !== '') {
                    $map[$col] = $label;
                }
            }

            // Buscar columnas por contenido del header
            $find = function (string $needle) use ($map): ?string {
                foreach ($map as $col => $label) {
                    if (stripos($label, $needle) !== false) return $col;
                }
                return null;
            };

            $colNombre    = $find('nombre');
            $colApellidos = $find('apellido');
            $colTel       = $find('tel');
            $colEmail     = $find('mail') ?? $find('e-mail');
            $colNacim     = $find('nacimiento');
            $colNif       = $find('nif') ?? $find('nie');
            $colNiss      = $find('seguridad social') ?? $find('niss');
            $colGrupoTGSS = $find('cotización') ?? $find('cotizacion') ?? $find('tgss');
            $colEstudios  = $find('estudios');
            $colGrupoProf = $find('profesional');

            if (!$colNif || !$colNombre) {
                session()->flash('error-matricula', 'No se encontraron las columnas Nombre o NIF en el archivo.');
                return;
            }

            $getCell = fn(string $col, int $row) => trim($sheet->getCell($col . $row)->getFormattedValue());

            for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
                $nombre = $colNombre ? $getCell($colNombre, $row) : '';
                $nif    = $colNif    ? trim($getCell($colNif, $row)) : '';

                if ($nombre === '' || $nif === '') continue;

                $apellidos = $colApellidos ? $getCell($colApellidos, $row) : '';
                $partes    = explode(' ', $apellidos, 2);
                $apellido1 = $partes[0] ?? '';
                $apellido2 = isset($partes[1]) && $partes[1] !== '' ? $partes[1] : null;

                // Normalizar capitalización: nombres y apellidos a Title Case
                // (Excel suele traerlos en MAYÚSCULAS).
                $nombre    = $this->aTitleCase($nombre);
                $apellido1 = $this->aTitleCase($apellido1);
                $apellido2 = $apellido2 !== null ? $this->aTitleCase($apellido2) : null;

                $email    = $colEmail    ? ($getCell($colEmail, $row)    ?: null) : null;
                $telefono = $colTel      ? ($getCell($colTel, $row)      ?: null) : null;
                $nacim    = $colNacim    ? ($getCell($colNacim, $row)    ?: null) : null;

                // NISS: extraer solo dígitos (quitar /, -)
                $nissRaw  = $colNiss ? $getCell($colNiss, $row) : '';
                $niss     = $nissRaw !== '' ? preg_replace('/\D/', '', $nissRaw) : null;

                // Grupo cotización TGSS: extraer número del paréntesis "(07) ..."
                $grupoTGSS = null;
                if ($colGrupoTGSS) {
                    preg_match('/\((\d+)\)/', $getCell($colGrupoTGSS, $row), $m);
                    $grupoTGSS = isset($m[1]) ? ltrim($m[1], '0') ?: '0' : null;
                }

                // Nivel estudios: letra entre paréntesis → 1-10
                $nivelEstudios = null;
                if ($colEstudios) {
                    preg_match('/\(([A-Ja-j])\)/', $getCell($colEstudios, $row), $m);
                    if (isset($m[1])) {
                        $nivelEstudios = ord(strtoupper($m[1])) - ord('A') + 1;
                    }
                }

                // Categoría profesional: número romano → entero
                $catProf = null;
                if ($colGrupoProf) {
                    preg_match('/\((I{1,3}V?|IV|VI{0,3})\)/i', $getCell($colGrupoProf, $row), $m);
                    if (isset($m[1])) {
                        $romano = strtoupper($m[1]);
                        $catProf = match($romano) {
                            'I'    => 1,
                            'II'   => 2,
                            'III'  => 3,
                            'IV'   => 4,
                            'V'    => 5,
                            default => null,
                        };
                    }
                }

                // Fecha nacimiento
                $fechaNacimiento = null;
                if ($nacim) {
                    try {
                        $fechaNacimiento = \Carbon\Carbon::parse($nacim)->format('Y-m-d');
                    } catch (\Exception) {}
                }

                // Email: limpiar saltos de línea y normalizar a minúsculas
                if ($email) {
                    $email = mb_strtolower(trim(str_replace(["\n", "\r"], '', $email)));
                }

                // Validar email único (solo si hay email y no pertenece al mismo alumno por NIF)
                if ($email && Alumno::where('email', $email)->where('nif', '!=', $nif)->exists()) {
                    $errores[] = "Fila {$row}: el correo {$email} ya pertenece a otro alumno.";
                    continue;
                }

                try {
                    Alumno::updateOrCreate(
                        ['nif' => $nif],
                        array_filter([
                            'empresa_id'            => $this->candidato->empresa_id,
                            'nombre'                => $nombre,
                            'apellido1'             => $apellido1,
                            'apellido2'             => $apellido2,
                            'email'                 => $email,
                            'telefono'              => $telefono,
                            'niss'                  => $niss,
                            'fecha_nacimiento'      => $fechaNacimiento,
                            'grupo_cotizacion_tgss' => $grupoTGSS,
                            'nivel_estudios'        => $nivelEstudios,
                            'categoria_profesional' => $catProf,
                        ], fn($v) => $v !== null)
                    );
                    $creados++;
                } catch (\Exception $e) {
                    $errores[] = "Fila {$row}: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            session()->flash('error-matricula', 'Error al leer el archivo: ' . $e->getMessage());
            $this->archivoAlumnos = null;
            return;
        }

        $this->archivoAlumnos = null;
        $this->mostrarImportAlumnos = false;

        // Si hay un grupo seleccionado, agregar automáticamente los alumnos importados
        $agregadosAlGrupo = 0;
        if ($this->grupoSeleccionadoId && $creados > 0) {
            $grupo = GrupoFormativo::with('alumnos', 'tutor')->find($this->grupoSeleccionadoId);
            if ($grupo && $grupo->estaAbierto()) {
                $alumnosEmpresa = Alumno::where('empresa_id', $this->candidato->empresa_id)
                    ->activos()
                    ->get();

                foreach ($alumnosEmpresa as $alumno) {
                    $yaEnGrupo = $grupo->alumnos->contains($alumno->id);
                    if (!$yaEnGrupo && !$alumno->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id)) {
                        $grupo->alumnos()->syncWithoutDetaching([$alumno->id]);
                        $agregadosAlGrupo++;
                    }
                }

                if ($agregadosAlGrupo > 0) {
                    $grupo->update(['descripcion' => $grupo->fresh()->descripcion_fundae]);
                }
            }
        }

        $msg = "{$creados} alumno(s) importado(s)";
        if ($agregadosAlGrupo > 0) {
            $msg .= " y {$agregadosAlGrupo} agregado(s) al grupo";
        }
        $msg .= '.';
        if ($errores) {
            $msg .= ' Errores: ' . implode('; ', array_slice($errores, 0, 3));
        }
        session()->flash('message-matricula', $msg);
    }

    // ═══════════════════════════════════════════════
    //  SUBIDA DE FICHAS PDF (1 alumno por PDF, varios PDFs por subida)
    // ═══════════════════════════════════════════════

    public function toggleSubidaPdf(): void
    {
        $this->mostrarSubidaPdf = !$this->mostrarSubidaPdf;
        if (!$this->mostrarSubidaPdf) {
            $this->resetSubidaPdf();
        }
    }

    public function procesarPdfs(): void
    {
        $this->validate([
            'archivosPdf'   => 'required|array|max:20',
            'archivosPdf.*' => 'file|mimes:pdf|mimetypes:application/pdf|max:10240',
        ], [
            'archivosPdf.required' => 'Selecciona al menos un PDF.',
            'archivosPdf.max'      => 'Máximo 20 PDFs por subida.',
            'archivosPdf.*.mimes'  => 'Solo se aceptan archivos PDF.',
            'archivosPdf.*.max'    => 'Cada PDF puede pesar hasta 10 MB.',
        ]);

        if (!$this->candidato->empresa_id) {
            session()->flash('error-matricula', 'El candidato no tiene empresa asociada.');
            return;
        }

        $grupo = $this->grupoSeleccionadoId ? GrupoFormativo::find($this->grupoSeleccionadoId) : null;
        $cifEmpresaGrupo = null;
        if ($grupo && $grupo->empresa) {
            $cifEmpresaGrupo = $this->normalizarCifSimple($grupo->empresa->cif);
        }

        $parser = new PdfFichaInscripcionParser();
        $preview = [];
        $nifsVistos = [];

        foreach ($this->archivosPdf as $idx => $pdf) {
            try {
                $resultado = $parser->parsear($pdf->getRealPath());
            } catch (\Throwable $e) {
                $resultado = [
                    'exito' => false, 'tipo' => 'ilegible',
                    'datos' => [], 'inferidos' => [], 'faltantes' => [],
                    'avisos' => ['Error al leer el PDF: ' . $e->getMessage()],
                ];
            }
            unset($parser);
            $parser = new PdfFichaInscripcionParser();

            $datos = $resultado['datos'] ?? [];
            $avisos = $resultado['avisos'] ?? [];
            $estado = 'crear';
            $diff = [];
            $nif = $datos['nif'] ?? null;
            $alumnoExistenteId = null;

            // NIF duplicado en la misma subida → marcar y omitir
            if ($nif && isset($nifsVistos[$nif])) {
                $avisos[] = 'PDF duplicado: ya hay otra fila con el mismo NIF, esta se ignorará.';
                $estado = 'duplicado';
            } elseif ($nif) {
                $nifsVistos[$nif] = true;
                $existente = Alumno::where('nif', $nif)->first();
                if ($existente) {
                    $alumnoExistenteId = $existente->id;
                    $estado = 'actualizar';
                    foreach (['nombre', 'apellido1', 'apellido2', 'email', 'telefono', 'niss',
                              'fecha_nacimiento', 'grupo_cotizacion_tgss', 'nivel_estudios',
                              'categoria_profesional'] as $campo) {
                        $nuevo = $datos[$campo] ?? null;
                        $viejo = $existente->{$campo};
                        if ($viejo instanceof \Carbon\Carbon) {
                            $viejo = $viejo->format('Y-m-d');
                        }
                        if ($nuevo !== null && (string) $nuevo !== (string) $viejo) {
                            $diff[$campo] = ['antes' => (string) ($viejo ?? ''), 'ahora' => (string) $nuevo];
                        }
                    }
                }
            }

            // Validar CIF contra empresa del grupo
            if ($cifEmpresaGrupo && !empty($datos['empresa_cif'])) {
                $cifPdf = $this->normalizarCifSimple($datos['empresa_cif']);
                if ($cifPdf && $cifPdf !== $cifEmpresaGrupo) {
                    $avisos[] = "CIF del PDF ({$datos['empresa_cif']}) no coincide con la empresa del grupo ({$grupo->empresa->cif}).";
                }
            }

            // Validar email duplicado en otro alumno
            if (!empty($datos['email']) && $nif) {
                $otro = Alumno::where('email', $datos['email'])->where('nif', '!=', $nif)->first();
                if ($otro) {
                    $avisos[] = "El email \"{$datos['email']}\" ya pertenece a otro alumno (NIF {$otro->nif}). Corrígelo antes de confirmar.";
                    $datos['email'] = null;
                }
            }

            $preview[] = [
                'archivo_nombre'     => $pdf->getClientOriginalName(),
                'archivo_temp_path'  => $pdf->getRealPath(),
                'archivo_temp_name'  => method_exists($pdf, 'getFilename') ? $pdf->getFilename() : basename($pdf->getRealPath()),
                'tipo'               => $resultado['tipo'] ?? 'ilegible',
                'origen'             => $resultado['origen'] ?? 'ilegible',
                'exito'              => $resultado['exito'] ?? false,
                'estado'             => $estado,
                'alumno_existente_id'=> $alumnoExistenteId,
                'datos'              => $datos,
                'inferidos'          => $resultado['inferidos'] ?? [],
                'faltantes'          => $resultado['faltantes'] ?? [],
                'avisos'             => $avisos,
                'diff'               => $diff,
            ];
        }

        $this->previewPdfs = $preview;
        $this->archivosPdf = [];
    }

    public function quitarPdfPreview(int $index): void
    {
        if (isset($this->previewPdfs[$index])) {
            unset($this->previewPdfs[$index]);
            $this->previewPdfs = array_values($this->previewPdfs);
        }
    }

    public function confirmarSubidaPdf(): void
    {
        if (empty($this->previewPdfs)) {
            session()->flash('error-matricula', 'No hay PDFs para confirmar.');
            return;
        }
        if (!$this->candidato->empresa_id) {
            session()->flash('error-matricula', 'El candidato no tiene empresa asociada.');
            return;
        }

        $grupo = $this->grupoSeleccionadoId
            ? GrupoFormativo::with('alumnos', 'tutor')->find($this->grupoSeleccionadoId)
            : null;

        $creados = 0;
        $actualizados = 0;
        $agregadosAlGrupo = 0;
        $errores = [];

        foreach ($this->previewPdfs as $fila) {
            if (($fila['estado'] ?? '') === 'duplicado') {
                continue;
            }

            $datos = $fila['datos'] ?? [];

            // Validaciones mínimas para poder guardar
            $faltantes = [];
            foreach (['nombre', 'apellido1', 'nif', 'email'] as $req) {
                if (empty($datos[$req])) {
                    $faltantes[] = $req;
                }
            }
            if (!empty($faltantes)) {
                $errores[] = "{$fila['archivo_nombre']}: faltan campos obligatorios (" . implode(', ', $faltantes) . ').';
                continue;
            }

            // Verificación final de email único cruzado con BD
            $emailConflict = Alumno::where('email', $datos['email'])
                ->where('nif', '!=', $datos['nif'])->exists();
            if ($emailConflict) {
                $errores[] = "{$fila['archivo_nombre']}: el email ya pertenece a otro alumno.";
                continue;
            }

            try {
                $eraNuevo = !Alumno::where('nif', $datos['nif'])->exists();
                $alumno = Alumno::updateOrCreate(
                    ['nif' => $datos['nif']],
                    array_filter([
                        'empresa_id'            => $this->candidato->empresa_id,
                        'nombre'                => $datos['nombre'],
                        'apellido1'             => $datos['apellido1'],
                        'apellido2'             => $datos['apellido2'] ?: null,
                        'email'                 => $datos['email'],
                        'telefono'              => $datos['telefono'] ?: null,
                        'niss'                  => $datos['niss'] ?: null,
                        'fecha_nacimiento'      => $datos['fecha_nacimiento'] ?: null,
                        'sexo'                  => $datos['sexo'] ?: null,
                        'grupo_cotizacion_tgss' => $datos['grupo_cotizacion_tgss'] ?? null,
                        'nivel_estudios'        => $datos['nivel_estudios'] ?? null,
                        'categoria_profesional' => $datos['categoria_profesional'] ?? null,
                    ], fn ($v) => $v !== null && $v !== '')
                );
                if ($eraNuevo) {
                    $creados++;
                } else {
                    $actualizados++;
                }
            } catch (\Throwable $e) {
                $errores[] = "{$fila['archivo_nombre']}: {$e->getMessage()}";
                continue;
            }

            // Mover el PDF al storage definitivo
            $rutaFinal = null;
            try {
                $tempPath = $fila['archivo_temp_path'] ?? null;
                if ($tempPath && is_file($tempPath) && $grupo) {
                    $nombreLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '', $datos['nif']);
                    $rutaFinal = "fichas-inscripcion/{$grupo->id}/{$nombreLimpio}_" . time() . '.pdf';
                    Storage::disk('local')->putFileAs(
                        dirname($rutaFinal),
                        $tempPath,
                        basename($rutaFinal)
                    );
                }
            } catch (\Throwable $e) {
                $errores[] = "{$fila['archivo_nombre']}: no se pudo archivar el PDF ({$e->getMessage()}).";
            }

            // Asociar al grupo (si hay grupo seleccionado y está abierto)
            if ($grupo && $grupo->estaAbierto()) {
                if ($alumno->matriculasAutonomas()->exists()) {
                    $errores[] = "{$fila['archivo_nombre']}: {$alumno->nombre_completo} es autónomo, no puede entrar en grupo FUNDAE.";
                    continue;
                }
                if ($alumno->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id)) {
                    $errores[] = "{$fila['archivo_nombre']}: {$alumno->nombre_completo} ya está en otro grupo con fechas que se solapan.";
                    continue;
                }
                if (!$grupo->tutor->puedeAceptarEnTramo($grupo->tramo_horario, 1, $grupo->fecha_inicio, $grupo->fecha_fin)) {
                    $errores[] = "El tutor ya tiene 80 alumnos simultáneos en el tramo {$grupo->tramo_horario}.";
                    break;
                }

                $datosPivot = ['estado_moodle' => 'pendiente'];
                if ($rutaFinal) {
                    $datosPivot['ficha_inscripcion_path']        = $rutaFinal;
                    $datosPivot['ficha_inscripcion_tipo']        = $fila['tipo'] === 'ilegible' ? 'manual' : ($fila['tipo'] ?? 'ficha');
                    $datosPivot['ficha_inscripcion_subida_en']   = now();
                }

                $grupo->alumnos()->syncWithoutDetaching([$alumno->id => $datosPivot]);
                $agregadosAlGrupo++;
            }
        }

        if ($grupo && $agregadosAlGrupo > 0) {
            $grupo->update(['descripcion' => $grupo->fresh()->descripcion_fundae]);
        }

        $this->resetSubidaPdf();

        $msg = "{$creados} creado(s), {$actualizados} actualizado(s), {$agregadosAlGrupo} agregado(s) al grupo.";
        if (!empty($errores)) {
            $msg .= ' Errores: ' . implode('; ', array_slice($errores, 0, 5));
            session()->flash('error-matricula', $msg);
        } else {
            session()->flash('message-matricula', $msg);
        }
    }

    public function descargarFichaPdf(int $grupoId, int $alumnoId): mixed
    {
        $grupo = GrupoFormativo::where('id', $grupoId)
            ->where('candidato_id', $this->candidato->id)
            ->firstOrFail();

        $pivot = $grupo->alumnos()->where('alumno_id', $alumnoId)->first()?->pivot;
        if (!$pivot || !$pivot->ficha_inscripcion_path) {
            session()->flash('error-matricula', 'Este alumno no tiene ficha PDF archivada.');
            return null;
        }

        if (!Storage::disk('local')->exists($pivot->ficha_inscripcion_path)) {
            session()->flash('error-matricula', 'El archivo PDF no existe en disco.');
            return null;
        }

        return Storage::disk('local')->download($pivot->ficha_inscripcion_path);
    }

    protected function resetSubidaPdf(): void
    {
        $this->archivosPdf = [];
        $this->previewPdfs = [];
        $this->resetValidation(['archivosPdf', 'previewPdfs']);
    }

    private function normalizarCifSimple(?string $cif): ?string
    {
        if (!$cif) return null;
        $n = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($cif)));
        return $n ?: null;
    }

    // ═══════════════════════════════════════════════
    //  CREAR GRUPO FORMATIVO
    // ═══════════════════════════════════════════════

    public function abrirFormGrupo(): void
    {
        $this->resetFormGrupo();
        $this->mostrarFormGrupo = true;
    }

    public function updatedBusquedaAccion(): void
    {
        if (strlen($this->busquedaAccion) >= 2) {
            $this->resultadosAccion = AccionFormativa::activas()
                ->where(function ($q) {
                    $q->where('denominacion', 'like', "%{$this->busquedaAccion}%")
                      ->orWhere('numero_accion', 'like', "%{$this->busquedaAccion}%");
                })
                ->limit(10)
                ->get()
                ->map(fn($a) => [
                    'id'        => $a->id,
                    'label'     => "#{$a->numero_accion} - {$a->denominacion_limpia} ({$a->horas}h)",
                    'plataforma' => $a->codigo_plataforma, // 'm' = Moodle, 'a' = Aulasystem
                ])
                ->toArray();
        } else {
            $this->resultadosAccion = [];
        }
    }

    public function seleccionarAccion(int $id): void
    {
        $this->nuevaAccionFormativaId = $id;
        $accion = AccionFormativa::find($id);
        $this->busquedaAccion = "#{$accion->numero_accion} - {$accion->denominacion_limpia}";
        $this->resultadosAccion = [];
    }

    public function updatedNuevasDias(string $value): void
    {
        $dias = (int) $value;
        if ($dias > 0 && $this->nuevaFechaInicio) {
            $this->nuevaFechaFin = Carbon::parse($this->nuevaFechaInicio)->addDays($dias)->format('Y-m-d');
        }
    }

    public function updatedNuevaFechaInicio(string $value): void
    {
        $dias = (int) $this->nuevasDias;
        if ($dias > 0 && $value) {
            $this->nuevaFechaFin = Carbon::parse($value)->addDays($dias)->format('Y-m-d');
        }
    }

    public function updatedEditDias(string $value): void
    {
        $dias = (int) $value;
        if ($dias > 0 && $this->editGrupoFechaInicio) {
            $this->editGrupoFechaFin = Carbon::parse($this->editGrupoFechaInicio)->addDays($dias)->format('Y-m-d');
        }
    }

    public function updatedEditGrupoFechaInicio(string $value): void
    {
        $dias = (int) $this->editDias;
        if ($dias > 0 && $value) {
            $this->editGrupoFechaFin = Carbon::parse($value)->addDays($dias)->format('Y-m-d');
        }
    }

    public function crearGrupo(): void
    {
        $this->validate([
            'nuevaAccionFormativaId' => 'required|exists:acciones_formativas,id',
            'nuevoTutorId' => 'required|exists:tutores,id',
            'nuevoTramo' => 'required|in:tramo_1,tramo_2',
            'nuevaFechaInicio' => 'required|date|after_or_equal:today',
            'nuevaFechaFin' => 'required|date|after:nuevaFechaInicio',
            'nuevaJornadaLaboral' => 'required|in:1,2',
        ]);

        $tutor = Tutor::findOrFail($this->nuevoTutorId);
        if (!$tutor->puedeAceptarEnTramo($this->nuevoTramo, 1, $this->nuevaFechaInicio, $this->nuevaFechaFin)) {
            session()->flash('error-matricula', "El tutor {$tutor->nombre_completo} ya tiene 80 alumnos simultáneos en {$this->nuevoTramo} para esas fechas.");
            return;
        }

        $grupo = GrupoFormativo::create([
            'candidato_id' => $this->candidato->id,
            'accion_formativa_id' => $this->nuevaAccionFormativaId,
            'tutor_id' => $this->nuevoTutorId,
            'empresa_id' => $this->candidato->empresa_id,
            'tramo_horario' => $this->nuevoTramo,
            'descripcion' => $this->nuevaDescripcion ?: null,
            'fecha_inicio' => $this->nuevaFechaInicio,
            'fecha_fin' => $this->nuevaFechaFin,
            'jornada_laboral' => $this->nuevaJornadaLaboral,
            'estado' => 'abierto',
        ]);

        $grupo->asignarIdGrupoFundae();

        $this->mostrarFormGrupo = false;
        $this->grupoSeleccionadoId = $grupo->id;
        $this->resetFormGrupo();
        session()->flash('message-matricula', "Grupo formativo creado (FUNDAE: {$grupo->codigo_fundae}). Ahora agrega los alumnos.");
    }

    protected function resetFormGrupo(): void
    {
        $this->reset([
            'busquedaAccion', 'resultadosAccion', 'nuevaAccionFormativaId',
            'nuevoTutorId', 'nuevoTramo', 'nuevaFechaInicio', 'nuevaFechaFin',
            'nuevasDias', 'nuevaDescripcion', 'nuevaJornadaLaboral',
        ]);
        $this->nuevoTramo = 'tramo_2';
        $this->nuevaJornadaLaboral = 1;
        $this->resetValidation();
    }

    // ═══════════════════════════════════════════════
    //  AÑADIR ALUMNOS AL GRUPO
    // ═══════════════════════════════════════════════

    public function seleccionarGrupo(int $grupoId): void
    {
        $this->grupoSeleccionadoId = $grupoId;
        $this->alumnosParaAgregar = [];
        $this->mostrarFormAlumno = true;
        $this->mostrarImportAlumnos = false;
        $this->mostrarSubidaPdf = false;
        $this->archivoAlumnos = null;
        $this->resetSubidaPdf();
    }

    public function deseleccionarGrupo(): void
    {
        $this->grupoSeleccionadoId = null;
        $this->alumnosParaAgregar = [];
        $this->mostrarFormAlumno = true;
        $this->mostrarImportAlumnos = false;
        $this->mostrarSubidaPdf = false;
        $this->archivoAlumnos = null;
        $this->resetSubidaPdf();
    }

    public function toggleAlumnoEnGrupo(int $grupoId, int $alumnoId): void
    {
        $grupo = GrupoFormativo::findOrFail($grupoId);

        if (!$grupo->estaAbierto()) {
            session()->flash('error-matricula', 'El grupo ya no está abierto (menos de 2 días para el inicio).');
            return;
        }

        $yaEnGrupo = $grupo->alumnos()->where('alumno_id', $alumnoId)->exists();

        if ($yaEnGrupo) {
            $grupo->alumnos()->detach($alumnoId);
        } else {
            $alumno = Alumno::find($alumnoId);
            if (!$alumno) return;

            if ($alumno->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id)) {
                session()->flash('error-matricula', "{$alumno->nombre_completo} ya está en otro grupo con fechas que se solapan.");
                return;
            }

            $tutor = $grupo->tutor;
            // No se excluye el grupo actual: sus alumnos ya asignados forman parte de la carga base.
            if (!$tutor->puedeAceptarEnTramo($grupo->tramo_horario, 1, $grupo->fecha_inicio, $grupo->fecha_fin)) {
                session()->flash('error-matricula', "El tutor ya tiene 80 alumnos simultáneos en {$grupo->tramo_horario}.");
                return;
            }

            $grupo->alumnos()->syncWithoutDetaching([$alumnoId]);
        }

        $grupo->update(['descripcion' => $grupo->fresh()->descripcion_fundae]);
    }

    public function agregarAlumnosAlGrupo(?int $alumnoId = null): void
    {
        $ids = $alumnoId ? [$alumnoId] : $this->alumnosParaAgregar;

        if (empty($ids)) {
            session()->flash('error-matricula', 'Selecciona al menos un alumno.');
            return;
        }

        $grupo = GrupoFormativo::findOrFail($this->grupoSeleccionadoId);

        if (!$grupo->estaAbierto()) {
            session()->flash('error-matricula', 'El grupo ya no está abierto (menos de 2 días para el inicio).');
            return;
        }

        $tutor = $grupo->tutor;
        if (!$tutor->puedeAceptarEnTramo($grupo->tramo_horario, count($ids), $grupo->fecha_inicio, $grupo->fecha_fin)) {
            session()->flash('error-matricula', "El tutor superaría los 80 alumnos simultáneos en este tramo.");
            return;
        }

        $agregados = 0;
        $rechazados = [];
        $conCurso = [];
        foreach ($ids as $id) {
            $alumno = Alumno::find($id);
            if (!$alumno) continue;

            if ($alumno->matriculasAutonomas()->exists()) {
                $rechazados[] = $alumno->nombre_completo;
                continue;
            }

            if ($alumno->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id)) {
                $conCurso[] = $alumno->nombre_completo;
                continue;
            }

            $grupo->alumnos()->syncWithoutDetaching([$id]);
            $agregados++;
        }

        // Actualizar descripción automática
        $grupo->update(['descripcion' => $grupo->fresh()->descripcion_fundae]);

        $this->alumnosParaAgregar = [];

        $errores = [];
        if (!empty($rechazados)) {
            $errores[] = 'Autónomo(s): ' . implode(', ', $rechazados);
        }
        if (!empty($conCurso)) {
            $errores[] = 'Con curso en ese período: ' . implode(', ', $conCurso);
        }

        if (!empty($errores)) {
            session()->flash('error-matricula', 'No añadidos — ' . implode('. ', $errores));
        }
        if ($agregados > 0) {
            session()->flash('message-matricula', "{$agregados} alumno(s) agregado(s) al grupo.");
        }
    }

    public function quitarAlumnoDelGrupo(int $grupoId, int $alumnoId): void
    {
        $grupo = GrupoFormativo::findOrFail($grupoId);
        $grupo->alumnos()->detach($alumnoId);
        $grupo->update(['descripcion' => $grupo->fresh()->descripcion_fundae]);
    }

    // ═══════════════════════════════════════════════
    //  ACCIONES SOBRE GRUPOS
    // ═══════════════════════════════════════════════

    public function generarIdGrupoFundae(int $grupoId): void
    {
        $grupo = GrupoFormativo::findOrFail($grupoId);
        $grupo->asignarIdGrupoFundae();
        session()->flash('message-matricula', "ID grupo FUNDAE asignado: {$grupo->fresh()->codigo_fundae}");
    }

    public function ejecutarEnMoodle(int $grupoId): void
    {
        $grupo = GrupoFormativo::with(['alumnos', 'tutor', 'accionFormativa'])->findOrFail($grupoId);

        // Si ya tiene curso asignado de una matriculación anterior, usarlo
        $moodleCourseId = $grupo->moodle_course_id
            ?? ($this->moodleCursosPorGrupo[$grupoId] ?? null);

        if (!$moodleCourseId) {
            $cursosVinculados = $grupo->accionFormativa->moodleCursos()->where('tipo', 'activa')->get();

            if ($cursosVinculados->isEmpty()) {
                session()->flash('error-matricula', 'No hay cursos de Moodle vinculados a esta acción formativa. Vincula primero en Acciones Formativas.');
                return;
            }

            // Intentar autodetectar por el username del tutor
            $tutor = $grupo->tutor;
            if ($tutor?->moodle_username && $cursosVinculados->count() > 1) {
                try {
                    $moodle = app(\Modules\Moodle\Services\MoodleService::class);
                    $moodleUser = $moodle->findUserByUsername($tutor->moodle_username);

                    if ($moodleUser) {
                        $cursosTutor = collect($moodle->getUserCourses($moodleUser['id']))
                            ->pluck('id')
                            ->toArray();

                        $coincidencias = $cursosVinculados->filter(
                            fn($c) => in_array($c->moodle_course_id, $cursosTutor)
                        );

                        if ($coincidencias->count() === 1) {
                            $moodleCourseId = $coincidencias->first()->moodle_course_id;
                        } elseif ($coincidencias->count() > 1) {
                            // Múltiples coincidencias: necesita selección manual
                            $this->moodleCursosPorGrupo[$grupoId] = null;
                            session()->flash('error-matricula', 'El tutor está en varios cursos vinculados. Selecciona manualmente el aula.');
                            return;
                        }
                    }
                } catch (\Exception $e) {
                    // Si falla la consulta Moodle, continuar con selección manual
                }
            }

            // Si solo hay un curso vinculado, usarlo directamente
            if (!$moodleCourseId && $cursosVinculados->count() === 1) {
                $moodleCourseId = $cursosVinculados->first()->moodle_course_id;
            }

            if (!$moodleCourseId) {
                session()->flash('error-matricula', 'Selecciona el aula de Moodle antes de matricular.');
                return;
            }
        }

        $resultados = $grupo->ejecutarEnMoodle($moodleCourseId);
        session()->flash('message-matricula', "Moodle: {$resultados['exitos']} matriculados, {$resultados['errores']} errores.");
    }

    public function marcarMatriculadoAulasystem(int $grupoId): void
    {
        $grupo = GrupoFormativo::where('id', $grupoId)
            ->where('candidato_id', $this->candidato->id)
            ->whereIn('estado', ['abierto', 'comunicado'])
            ->firstOrFail();

        if ($grupo->alumnos->isEmpty()) {
            session()->flash('error-matricula', 'El grupo no tiene alumnos asignados.');
            return;
        }

        // Marcar todos los alumnos como matriculados en aulasystem
        $grupo->alumnos()->each(function ($alumno) use ($grupo) {
            $grupo->alumnos()->updateExistingPivot($alumno->id, [
                'estado_moodle' => 'aulasystem',
            ]);
        });

        $grupo->update(['estado' => 'en_curso']);

        session()->flash('message-matricula', "Grupo marcado como matriculado en Aulasystem ({$grupo->alumnos->count()} alumno(s)).");
    }

    // ═══════════════════════════════════════════════
    //  NOTIFICACIÓN PDF FUNDAE → COMUNICADO
    // ═══════════════════════════════════════════════

    public function abrirSubirPdf(int $grupoId): void
    {
        $this->pdfGrupoId      = $grupoId;
        $this->pdfNotificacion = null;
        $this->resetValidation('pdfNotificacion');
    }

    public function cerrarSubirPdf(): void
    {
        $this->pdfGrupoId      = null;
        $this->pdfNotificacion = null;
        $this->resetValidation('pdfNotificacion');
    }

    public function procesarPdfNotificacion(): void
    {
        $this->validate([
            'pdfNotificacion' => 'required|file|mimes:pdf|max:10240',
        ], [
            'pdfNotificacion.required' => 'Selecciona el archivo PDF de la notificación.',
            'pdfNotificacion.mimes'    => 'El archivo debe ser un PDF.',
        ]);

        $grupo = GrupoFormativo::where('id', $this->pdfGrupoId)
            ->where('candidato_id', $this->candidato->id)
            ->where('estado', 'abierto')
            ->with('accionFormativa')
            ->first();

        if (!$grupo) {
            session()->flash('error-matricula', 'El grupo no existe o no está en estado abierto.');
            return;
        }

        if (!$grupo->id_grupo_fundae) {
            session()->flash('error-matricula', 'El grupo no tiene ID FUNDAE asignado. Genera el ID primero.');
            return;
        }

        try {
            $parser = new PdfNotificacionFundaeParser();
            $datos  = $parser->parsear($this->pdfNotificacion->getRealPath());
        } catch (\Exception $e) {
            session()->flash('error-matricula', 'No se pudo leer el PDF: ' . $e->getMessage());
            return;
        }

        if (empty($datos['numero_accion']) || empty($datos['id_grupo_fundae'])) {
            session()->flash('error-matricula', 'No se encontraron los datos de Acción Formativa y Grupo en el PDF. Asegúrate de que sea una Notificación de Inicio de Grupo de FUNDAE.');
            return;
        }

        if ((int) $grupo->accionFormativa->numero_accion !== $datos['numero_accion']
            || (int) $grupo->id_grupo_fundae !== $datos['id_grupo_fundae']) {
            session()->flash('error-matricula', sprintf(
                'El PDF no corresponde a este grupo. PDF: Acción %d / Grupo %d — Panel: Acción %s / Grupo %d.',
                $datos['numero_accion'],
                $datos['id_grupo_fundae'],
                $grupo->accionFormativa->numero_accion,
                $grupo->id_grupo_fundae
            ));
            return;
        }

        // Crear o actualizar en tabla grupos (relación 1-1 por numero_accion + id_grupo)
        Grupo::updateOrCreate(
            [
                'codigo_grupo_accion_formativa' => (string) $datos['numero_accion'],
                'codigo_grupo'                  => (string) $datos['id_grupo_fundae'],
            ],
            [
                'grupo_id'             => $datos['grupo_id'] ?? null,
                'denominacion'         => $datos['denominacion'] ?? null,
                'cif'                  => $datos['cif'] ?? null,
                'inicio'               => $datos['inicio'] ?? null,
                'fin'                  => $datos['fin'] ?? null,
                'not_inicio'           => $datos['not_inicio'] ?? null,
                'modalidad'            => $datos['modalidad'],
                'duracion'             => $datos['duracion'] ?? 0,
                'estado'               => $datos['estado'],
                'numero_participantes' => $datos['numero_participantes'] ?? 0,
            ]
        );

        $grupo->update(['estado' => 'comunicado']);

        $this->pdfGrupoId      = null;
        $this->pdfNotificacion = null;

        session()->flash('message-matricula', "Notificación FUNDAE procesada correctamente. Grupo {$grupo->codigo_fundae} marcado como comunicado. Ya puedes matricular a los alumnos.");
    }

    public function marcarComunicado(int $grupoId): void
    {
        GrupoFormativo::where('id', $grupoId)
            ->where('candidato_id', $this->candidato->id)
            ->where('estado', 'abierto')
            ->update(['estado' => 'comunicado']);

        session()->flash('message-matricula', 'Grupo marcado como comunicado manualmente.');
    }

    public function cancelarGrupo(int $grupoId): void
    {
        GrupoFormativo::where('id', $grupoId)
            ->where('candidato_id', $this->candidato->id)
            ->whereIn('estado', ['abierto'])
            ->update(['estado' => 'cancelado']);
    }

    public function descargarXmlInicioGrupo(int $grupoId): mixed
    {
        $grupo = GrupoFormativo::findOrFail($grupoId);

        if (!$grupo->id_grupo_fundae) {
            $grupo->asignarIdGrupoFundae();
            $grupo->refresh();
        }

        $service = new FundaeXmlService();
        $xml = $service->generarXmlInicioGrupo([$grupo->id]);
        $filename = "INICIO_GRUPO_{$grupo->codigo_fundae}_{$grupo->fecha_inicio->format('Ymd')}.xml";
        $filename = str_replace('/', '_', $filename);

        return response()->streamDownload(fn() => print($xml), $filename, ['Content-Type' => 'application/xml']);
    }

    // ═══════════════════════════════════════════════
    //  EDITAR / ELIMINAR GRUPO
    // ═══════════════════════════════════════════════

    public function abrirEditarGrupo(int $grupoId): void
    {
        $grupo = GrupoFormativo::findOrFail($grupoId);
        $this->editandoGrupoId      = $grupoId;
        $this->editGrupoTutorId     = $grupo->tutor_id;
        $this->editGrupoTramo       = $grupo->tramo_horario;
        $this->editGrupoFechaInicio = $grupo->fecha_inicio->format('Y-m-d');
        $this->editGrupoFechaFin    = $grupo->fecha_fin->format('Y-m-d');
        $this->editDias             = '';
        $this->editGrupoJornada     = $grupo->jornada_laboral;
        $this->editGrupoDescripcion = $grupo->descripcion ?? '';
        $this->resetValidation();
    }

    public function cerrarEditarGrupo(): void
    {
        $this->editandoGrupoId = null;
    }

    public function actualizarGrupo(): void
    {
        $this->validate([
            'editGrupoTutorId'     => 'required|exists:tutores,id',
            'editGrupoTramo'       => 'required|in:tramo_1,tramo_2',
            'editGrupoFechaInicio' => 'required|date',
            'editGrupoFechaFin'    => 'required|date|after_or_equal:editGrupoFechaInicio',
            'editGrupoJornada'     => 'required|integer|in:1,2',
        ]);

        GrupoFormativo::where('id', $this->editandoGrupoId)
            ->where('candidato_id', $this->candidato->id)
            ->where('estado', 'abierto')
            ->update([
                'tutor_id'       => $this->editGrupoTutorId,
                'tramo_horario'  => $this->editGrupoTramo,
                'fecha_inicio'   => $this->editGrupoFechaInicio,
                'fecha_fin'      => $this->editGrupoFechaFin,
                'jornada_laboral'=> $this->editGrupoJornada,
                'descripcion'    => $this->editGrupoDescripcion ?: null,
            ]);

        $this->editandoGrupoId = null;
        session()->flash('message-matricula', 'Grupo actualizado correctamente.');
    }

    public function eliminarGrupo(int $grupoId): void
    {
        $grupo = GrupoFormativo::where('id', $grupoId)
            ->where('candidato_id', $this->candidato->id)
            ->where('estado', 'abierto')
            ->firstOrFail();

        $grupo->alumnos()->detach();
        $grupo->delete();

        session()->flash('message-matricula', 'Grupo eliminado.');
    }

    // ═══════════════════════════════════════════════
    //  EDITAR ALUMNO
    // ═══════════════════════════════════════════════

    public function abrirEditarAlumno(int $alumnoId): void
    {
        $alumno = Alumno::findOrFail($alumnoId);
        $this->editandoAlumnoId    = $alumnoId;
        $this->editAlumnoNombre    = $alumno->nombre;
        $this->editAlumnoApellido1 = $alumno->apellido1;
        $this->editAlumnoApellido2 = $alumno->apellido2 ?? '';
        $this->editAlumnoNif       = $alumno->nif;
        $this->editAlumnoEmail     = $alumno->email ?? '';
        $this->editAlumnoTelefono  = $alumno->telefono ?? '';
        $this->resetValidation();
    }

    public function cerrarEditarAlumno(): void
    {
        $this->editandoAlumnoId = null;
    }

    public function actualizarAlumno(): void
    {
        $this->validate([
            'editAlumnoNombre'    => 'required|string|max:255',
            'editAlumnoApellido1' => 'required|string|max:255',
            'editAlumnoApellido2' => 'nullable|string|max:255',
            'editAlumnoNif'       => 'required|string|max:15|unique:alumnos,nif,' . $this->editandoAlumnoId,
            'editAlumnoEmail'     => 'required|email|max:255|unique:alumnos,email,' . $this->editandoAlumnoId,
            'editAlumnoTelefono'  => 'nullable|string|max:20',
        ], [
            'editAlumnoNif.unique'   => 'Ya existe otro alumno con ese NIF.',
            'editAlumnoEmail.unique' => 'Ya existe otro alumno con ese correo.',
        ]);

        Alumno::where('id', $this->editandoAlumnoId)->update([
            'nombre'    => $this->aTitleCase($this->editAlumnoNombre),
            'apellido1' => $this->aTitleCase($this->editAlumnoApellido1),
            'apellido2' => $this->editAlumnoApellido2 ? $this->aTitleCase($this->editAlumnoApellido2) : null,
            'nif'       => $this->editAlumnoNif,
            'email'     => mb_strtolower(trim($this->editAlumnoEmail)),
            'telefono'  => $this->editAlumnoTelefono ?: null,
        ]);

        $this->editandoAlumnoId = null;
        session()->flash('message-matricula', 'Datos del alumno actualizados.');
    }

    // ═══════════════════════════════════════════════
    //  AUTÓNOMOS (2x1)
    // ═══════════════════════════════════════════════

    public function abrirFormAutonomo(): void
    {
        $this->resetFormAutonomo();
        $this->mostrarFormAutonomo = true;
    }

    public function updatedAutonomoBusquedaAccion(): void
    {
        if (strlen($this->autonomoBusquedaAccion) >= 2) {
            $this->autonomoResultadosAccion = AccionFormativa::activas()
                ->where(function ($q) {
                    $q->where('denominacion', 'like', "%{$this->autonomoBusquedaAccion}%")
                      ->orWhere('numero_accion', 'like', "%{$this->autonomoBusquedaAccion}%");
                })
                ->limit(10)
                ->get()
                ->map(fn($a) => [
                    'id'         => $a->id,
                    'label'      => "#{$a->numero_accion} - {$a->denominacion_limpia} ({$a->horas}h)",
                    'plataforma' => $a->codigo_plataforma,
                ])
                ->toArray();
        } else {
            $this->autonomoResultadosAccion = [];
        }
    }

    public function seleccionarAccionAutonomo(int $id): void
    {
        $this->autonomoAccionFormativaId = $id;
        $accion = AccionFormativa::find($id);
        $this->autonomoBusquedaAccion = "#{$accion->numero_accion} - {$accion->denominacion_limpia}";
        $this->autonomoResultadosAccion = [];
    }

    public function updatedAutonomoDias(string $value): void
    {
        $dias = (int) $value;
        if ($dias > 0 && $this->autonomoFechaInicio) {
            $this->autonomoFechaFin = Carbon::parse($this->autonomoFechaInicio)->addDays($dias)->format('Y-m-d');
        }
    }

    public function updatedAutonomoFechaInicio(string $value): void
    {
        $dias = (int) $this->autonomoDias;
        if ($dias > 0 && $value) {
            $this->autonomoFechaFin = Carbon::parse($value)->addDays($dias)->format('Y-m-d');
        }
    }

    public function crearMatriculaAutonoma(): void
    {
        $rules = [
            'autonomoAccionFormativaId' => 'required|exists:acciones_formativas,id',
            'autonomoTutorId'           => 'required|exists:tutores,id',
            'autonomoFechaInicio'       => 'nullable|date',
            'autonomoFechaFin'          => 'nullable|date|after_or_equal:autonomoFechaInicio',
        ];

        if ($this->autonomoNuevoAlumno) {
            $rules += [
                'autonomoNombre'    => 'required|string|max:255',
                'autonomoApellido1' => 'required|string|max:255',
                'autonomoApellido2' => 'nullable|string|max:255',
                'autonomoNif'       => 'required|string|max:15|unique:alumnos,nif',
                'autonomoEmail'     => 'required|email|max:255|unique:alumnos,email',
                'autonomoTelefono'  => 'nullable|string|max:20',
            ];
        } else {
            $rules['autonomoAlumnoId'] = 'required|exists:alumnos,id';
        }

        $this->validate($rules, [
            'autonomoAlumnoId.required' => 'Selecciona un alumno o crea uno nuevo.',
            'autonomoNif.unique'        => 'Ya existe un alumno con ese NIF.',
            'autonomoEmail.unique'      => 'Ya existe un alumno con ese correo.',
        ]);

        if (!$this->candidato->empresa_id) {
            session()->flash('error-matricula', 'El candidato no tiene empresa asociada.');
            return;
        }

        // Verificar que el alumno existente no sea bonificado (FUNDAE)
        if (!$this->autonomoNuevoAlumno) {
            $alumno = Alumno::find($this->autonomoAlumnoId);
            if ($alumno && $alumno->gruposFormativos()->exists()) {
                session()->flash('error-matricula', 'Este alumno tiene grupos FUNDAE. Un alumno bonificado no puede ser autónomo.');
                return;
            }
        }

        // Crear nuevo alumno si es necesario
        if ($this->autonomoNuevoAlumno) {
            $alumno = Alumno::create([
                'empresa_id' => $this->candidato->empresa_id,
                'nombre'     => $this->aTitleCase($this->autonomoNombre),
                'apellido1'  => $this->aTitleCase($this->autonomoApellido1),
                'apellido2'  => $this->autonomoApellido2 ? $this->aTitleCase($this->autonomoApellido2) : null,
                'nif'        => $this->autonomoNif,
                'email'      => mb_strtolower(trim($this->autonomoEmail)),
                'telefono'   => $this->autonomoTelefono ?: null,
            ]);
            $alumnoId = $alumno->id;
        } else {
            $alumnoId = $this->autonomoAlumnoId;
        }

        MatriculaAutonoma::create([
            'candidato_id'        => $this->candidato->id,
            'alumno_id'           => $alumnoId,
            'accion_formativa_id' => $this->autonomoAccionFormativaId,
            'tutor_id'            => $this->autonomoTutorId,
            'empresa_id'          => $this->candidato->empresa_id,
            'fecha_inicio'        => $this->autonomoFechaInicio ?: null,
            'fecha_fin'           => $this->autonomoFechaFin ?: null,
            'estado'              => 'pendiente',
        ]);

        $this->mostrarFormAutonomo = false;
        $this->resetFormAutonomo();
        session()->flash('message-matricula', 'Matrícula de autónomo creada correctamente.');
    }

    public function ejecutarEnMoodleAutonomo(int $matriculaId): void
    {
        $matricula = MatriculaAutonoma::with(['alumno', 'tutor', 'accionFormativa'])
            ->where('candidato_id', $this->candidato->id)
            ->findOrFail($matriculaId);

        $moodleCourseId = $matricula->moodle_course_id
            ?? ($this->moodleCursosPorAutonomo[$matriculaId] ?? null);

        if (!$moodleCourseId) {
            $cursosVinculados = $matricula->accionFormativa->moodleCursos()->where('tipo', 'activa')->get();

            if ($cursosVinculados->isEmpty()) {
                session()->flash('error-matricula', 'No hay cursos Moodle vinculados a esta acción formativa.');
                return;
            }

            // Autodetectar por tutor
            $tutor = $matricula->tutor;
            if ($tutor?->moodle_username && $cursosVinculados->count() > 1) {
                try {
                    $moodle = app(\Modules\Moodle\Services\MoodleService::class);
                    $moodleUser = $moodle->findUserByUsername($tutor->moodle_username);

                    if ($moodleUser) {
                        $cursosTutor = collect($moodle->getUserCourses($moodleUser['id']))->pluck('id')->toArray();
                        $coincidencias = $cursosVinculados->filter(fn($c) => in_array($c->moodle_course_id, $cursosTutor));

                        if ($coincidencias->count() === 1) {
                            $moodleCourseId = $coincidencias->first()->moodle_course_id;
                        } elseif ($coincidencias->count() > 1) {
                            session()->flash('error-matricula', 'El tutor está en varios cursos vinculados. Selecciona manualmente el aula.');
                            return;
                        }
                    }
                } catch (\Exception $e) {
                    // Continuar con selección manual
                }
            }

            if (!$moodleCourseId && $cursosVinculados->count() === 1) {
                $moodleCourseId = $cursosVinculados->first()->moodle_course_id;
            }

            if (!$moodleCourseId) {
                session()->flash('error-matricula', 'Selecciona el aula de Moodle antes de matricular.');
                return;
            }
        }

        $resultados = $matricula->ejecutarEnMoodle($moodleCourseId);
        session()->flash('message-matricula', "Autónomo Moodle: {$resultados['exitos']} matriculado(s), {$resultados['errores']} error(es).");
    }

    public function eliminarMatriculaAutonoma(int $matriculaId): void
    {
        MatriculaAutonoma::where('id', $matriculaId)
            ->where('candidato_id', $this->candidato->id)
            ->firstOrFail()
            ->delete();

        session()->flash('message-matricula', 'Matrícula de autónomo eliminada.');
    }

    protected function resetFormAutonomo(): void
    {
        $this->reset([
            'autonomoBusquedaAccion', 'autonomoResultadosAccion', 'autonomoAccionFormativaId',
            'autonomoTutorId', 'autonomoAlumnoId', 'autonomoFechaInicio', 'autonomoFechaFin',
            'autonomoDias', 'autonomoNuevoAlumno', 'autonomoNombre', 'autonomoApellido1',
            'autonomoApellido2', 'autonomoNif', 'autonomoEmail', 'autonomoTelefono',
        ]);
        $this->resetValidation();
    }

    // ═══════════════════════════════════════════════
    //  RENDER
    // ═══════════════════════════════════════════════

    public function render()
    {
        $alumnos = collect();
        if ($this->candidato->empresa_id) {
            $alumnos = Alumno::where('empresa_id', $this->candidato->empresa_id)
                ->activos()
                ->orderBy('apellido1')
                ->get();
        }

        $grupos = GrupoFormativo::where('candidato_id', $this->candidato->id)
            ->with(['accionFormativa', 'tutor', 'empresa', 'alumnos'])
            ->orderByDesc('created_at')
            ->get();

        // Para grupos abiertos con ID asignado, verificar si ya existe registro en tabla grupos (FUNDAE)
        $gruposEnFundae = [];
        foreach ($grupos as $g) {
            if ($g->estado === 'abierto' && $g->id_grupo_fundae && $g->accionFormativa) {
                $gruposEnFundae[$g->id] = Grupo::where('codigo_grupo_accion_formativa', (string) $g->accionFormativa->numero_accion)
                    ->where('codigo_grupo', (string) $g->id_grupo_fundae)
                    ->exists();
            }
        }

        $gruposAbiertos = GrupoFormativo::where('candidato_id', $this->candidato->id)
            ->abiertos()
            ->with('accionFormativa')
            ->get();

        $tutores = Tutor::activos()->orderBy('apellido1')->get();

        $grupoActivo = $this->grupoSeleccionadoId
            ? GrupoFormativo::with('alumnos')->find($this->grupoSeleccionadoId)
            : null;

        // Alumnos disponibles para autónomos (sin grupos FUNDAE)
        $alumnosParaAutonomo = $alumnos->filter(fn ($a) => $a->gruposFormativos()->doesntExist());

        // Matrículas de autónomos
        $matriculasAutonomas = MatriculaAutonoma::where('candidato_id', $this->candidato->id)
            ->with(['alumno', 'accionFormativa', 'tutor'])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.webcurso.matriculacion-panel', [
            'alumnos'              => $alumnos,
            'grupos'               => $grupos,
            'gruposAbiertos'       => $gruposAbiertos,
            'tutores'              => $tutores,
            'grupoActivo'          => $grupoActivo,
            'gruposEnFundae'       => $gruposEnFundae,
            'alumnosParaAutonomo'  => $alumnosParaAutonomo,
            'matriculasAutonomas'  => $matriculasAutonomas,
        ]);
    }

    /**
     * Title Case unicode-safe: convierte "GREICY LISBETH" → "Greicy Lisbeth".
     * Primera letra de cada palabra en mayúscula, resto en minúscula. Maneja
     * separadores espacio, guion y apóstrofo.
     */
    private function aTitleCase(?string $texto): string
    {
        if ($texto === null) return '';
        $texto = trim($texto);
        if ($texto === '') return '';

        $lower = mb_strtolower($texto, 'UTF-8');
        return preg_replace_callback(
            '/(?<=^|[\s\-\'\.])(\p{L})/u',
            fn ($m) => mb_strtoupper($m[1], 'UTF-8'),
            $lower
        );
    }
}
