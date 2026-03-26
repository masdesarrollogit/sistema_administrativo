<?php

namespace App\Services\Webcurso;

use App\Models\AccionFormativa;
use App\Models\GrupoFormativo;
use SimpleXMLElement;

class FundaeXmlService
{
    protected array $centro;

    public function __construct()
    {
        $this->centro = config('webcurso.centro');
    }

    /**
     * Generar XML de Acciones Formativas (AF) para carga masiva en FUNDAE.
     */
    public function generarXmlAccionesFormativas(array $accionIds): string
    {
        $acciones = AccionFormativa::whereIn('id', $accionIds)->orderBy('numero_accion')->get();
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AccionesFormativas/>');

        foreach ($acciones as $accion) {
            $af = $xml->addChild('AccionFormativa');
            $af->addChild('codAccion', $accion->numero_accion);
            $af->addChild('nombreAccion', $this->xmlSafe($accion->denominacion));
            $af->addChild('codGrupoAccion', $accion->cod_grupo_accion ?? '068-06');
            $af->addChild('codAreaProfesional', $accion->codigo_actividad ?? 'ADGG');

            $modalidad = $af->addChild('modalidadTeleformacion');
            $modalidad->addChild('horasTe', $accion->horas);

            if ($accion->nif_proveedor_plataforma) {
                $af->addChild('cifPlataforma', $accion->nif_proveedor_plataforma);
            }

            $plataforma = $accion->codigo_plataforma;
            if ($plataforma === 'm') {
                $af->addChild('razonSocialPlataforma', 'MARKETING SOFTWARE 2012');
                $af->addChild('uri', 'aula.1curso.com');
                $af->addChild('usuario', 'supervisortripartita@webcurso.es');
                $af->addChild('password', $accion->clave_acceso ?? 'Tr1part1ta4444*');
            } elseif ($plataforma === 'a') {
                $af->addChild('razonSocialPlataforma', 'System Centros de Formacion, S.L.');
                $af->addChild('uri', 'www.plataformateleformacion.com');
                $af->addChild('usuario', $accion->usuario_supervision ?? 'Smarkesoft');
                $af->addChild('password', $accion->clave_acceso ?? 'SuperMarket4444');
            }

            $af->addChild('objetivos', $this->xmlSafe($accion->objetivos ?? 'Objetivos del curso'));
            $af->addChild('contenidos', $this->xmlSafe($accion->contenidos ?? 'Contenidos del curso'));
        }

        return $this->formatXml($xml);
    }

    /**
     * Generar XML de Inicio de Grupo Formativo (IGF).
     * Sigue el patron del XML real de WebCurso (INICIO_GRUPO_BUENO_2026_david.xml).
     */
    public function generarXmlInicioGrupo(array $grupoIds): string
    {
        $grupos = GrupoFormativo::whereIn('id', $grupoIds)
            ->with(['alumnos', 'accionFormativa', 'tutor', 'empresa'])
            ->get();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><grupos/>');

        foreach ($grupos as $grupoFormativo) {
            $accion = $grupoFormativo->accionFormativa;
            $tutor = $grupoFormativo->tutor;
            $empresa = $grupoFormativo->empresa;

            $grupo = $xml->addChild('grupo');
            $grupo->addChild('idAccion', $accion->numero_accion);
            $grupo->addChild('idGrupo', $grupoFormativo->id_grupo_fundae ?? $grupoFormativo->id);
            $grupo->addChild('descripcion', $this->xmlSafe($grupoFormativo->descripcion ?? $grupoFormativo->descripcion_fundae));
            $grupo->addChild('NumeroParticipante', $grupoFormativo->alumnos->count());
            $grupo->addChild('fechaInicio', $grupoFormativo->fecha_inicio->format('d/m/Y'));
            $grupo->addChild('fechaFin', $grupoFormativo->fecha_fin->format('d/m/Y'));
            $grupo->addChild('responsable', $this->centro['responsable']);
            $grupo->addChild('telefonoContacto', $this->centro['telefono']);

            // Teleformación
            $distancia = $grupo->addChild('distanciaTeleformacion');
            $asistencia = $distancia->addChild('asistenciaTeleformacion');

            $centro = $asistencia->addChild('centro');
            $centro->addChild('cif', $this->centro['cif']);
            $centro->addChild('nombreCentro', $this->centro['nombre']);
            $centro->addChild('direccionDetallada', $this->centro['direccion']);
            $centro->addChild('codPostal', $this->centro['cod_postal']);
            $centro->addChild('localidad', $this->centro['localidad']);
            $asistencia->addChild('telefono', $this->centro['telefono']);

            // Horario según tramo del GRUPO
            $horario = $distancia->addChild('horario');
            $horario->addChild('horaTotales', $accion->horas);

            if ($grupoFormativo->tramo_horario === 'tramo_1') {
                $horario->addChild('horaInicioTramo1', '08:00');
                $horario->addChild('horaFinTramo1', '11:00');
            } else {
                $horario->addChild('horaInicioTramo2', '15:00');
                $horario->addChild('horaFinTramo2', '18:00');
            }
            $horario->addChild('dias', 'LMXJV');

            // Tutor
            $tutorXml = $distancia->addChild('Tutor');
            $tutorXml->addChild('numeroHoras', $accion->horas);
            $tutorXml->addChild('tipoDocumento', 10);
            $tutorXml->addChild('documento', $tutor->nif);
            $tutorXml->addChild('nombre', $tutor->nombre);
            $tutorXml->addChild('apellido1', $tutor->apellido1);
            $tutorXml->addChild('apellido2', $tutor->apellido2 ?? '');
            $tutorXml->addChild('telefono', $tutor->telefono);
            $tutorXml->addChild('correoElectronico', $tutor->email);

            $tutoria = $tutorXml->addChild('tutoria');
            $tipoTutoria = $tutoria->addChild('tipoTutoria');
            $tipoTutoria->addChild('tutorias', 1);
            $tutoria->addChild('descripcion', 'Informacion adicional');

            // Empresa participante
            $empresas = $grupo->addChild('EmpresasParticipantes');
            $emp = $empresas->addChild('empresa');
            $emp->addChild('cifEmpresaParticipante', $empresa->cif);
            $emp->addChild('jornadaLaboral', $grupoFormativo->jornada_laboral);

            $grupo->addChild('observaciones', '');
        }

        return $this->formatXml($xml);
    }

    /**
     * Generar XML de Finalización de Grupo Formativo (FGF).
     */
    public function generarXmlFinalizacionGrupo(array $grupoIds): string
    {
        $grupos = GrupoFormativo::whereIn('id', $grupoIds)
            ->where('estado', 'completado')
            ->with(['alumnos', 'accionFormativa', 'empresa'])
            ->get();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><grupos/>');

        foreach ($grupos as $grupoFormativo) {
            $grupo = $xml->addChild('grupo');
            $grupo->addChild('idAccion', str_pad($grupoFormativo->accionFormativa->numero_accion, 5, '0', STR_PAD_LEFT));
            $grupo->addChild('idGrupo', str_pad($grupoFormativo->id_grupo_fundae ?? $grupoFormativo->id, 5, '0', STR_PAD_LEFT));

            $participantes = $grupo->addChild('participantes');
            foreach ($grupoFormativo->alumnos as $alumno) {
                $p = $participantes->addChild('participante');
                $p->addChild('nif', $alumno->nif);
                $p->addChild('N_TIPO_DOCUMENTO', 10);
                $p->addChild('ERTE_RD_ley', 'false');
                $p->addChild('email', $alumno->email ?? '');
                $p->addChild('telefono', $alumno->telefono ?? '');
                $p->addChild('discapacidad', 'false');
                $p->addChild('afectadosTerrorismo', 'false');
                $p->addChild('afectadosViolenciaGenero', 'false');
                $p->addChild('categoriaprofesional', $alumno->categoria_profesional ?? 3);
                $p->addChild('nivelestudios', $alumno->nivel_estudios ?? 4);
                $p->addChild('DiplomaAcreditativo', 'N');
                $p->addChild('fijoDiscontinuo', 'false');
            }

            $costesXml = $grupo->addChild('costes');
            $coste = $costesXml->addChild('coste');
            $coste->addChild('cifagrupada', $grupoFormativo->empresa->cif);
            $coste->addChild('directos', '0');
            $coste->addChild('indirectos', '0');
            $coste->addChild('organizacion', '0');
            $coste->addChild('salariales', '0');
        }

        return $this->formatXml($xml);
    }

    protected function xmlSafe(?string $text): string
    {
        return $text ? htmlspecialchars(strip_tags($text), ENT_XML1 | ENT_QUOTES, 'UTF-8') : '';
    }

    protected function formatXml(SimpleXMLElement $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }
}
