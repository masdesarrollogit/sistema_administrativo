<?php

namespace Database\Seeders;

use App\Models\TipoCandidato;
use App\Models\TipoRequisito;
use Illuminate\Database\Seeder;

class TiposCandidatoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear tipos de candidato
        // Crear tipos de candidato
        $empresaOrganizadora = TipoCandidato::firstOrCreate(
            ['codigo' => 'empresa_organizadora'],
            [
                'nombre' => 'Empresa Bonificable (Organizadora)',
                'descripcion' => 'Empresas bonificables por FUNDAE donde Webcurso gestiona las bonificaciones',
                'activo' => true,
                'orden' => 1,
            ]
        );

        $empresaExterna = TipoCandidato::firstOrCreate(
            ['codigo' => 'empresa_externa'],
            [
                'nombre' => 'Empresa Bonificable (Externa)',
                'descripcion' => 'Empresas bonificables por FUNDAE que gestionan sus propias bonificaciones',
                'activo' => true,
                'orden' => 2,
            ]
        );

        $particular = TipoCandidato::firstOrCreate(
            ['codigo' => 'particular'],
            [
                'nombre' => 'Usuario Particular',
                'descripcion' => 'Usuarios particulares no bonificables por FUNDAE',
                'activo' => true,
                'orden' => 3,
            ]
        );

        // Requisitos para Empresa Organizadora (Tipo 1)
        $this->crearRequisito($empresaOrganizadora->id, 'contrato_enviado', 'Contrato Rellenado', 'Enviar contrato rellenado', 1);
        $this->crearRequisito($empresaOrganizadora->id, 'contrato_firmado', 'Firmar Contrato', 'Enviar contrato firmado y sellado por el Administrador Legal de la empresa', 2);
        $this->crearRequisito($empresaOrganizadora->id, 'datos_alumno', 'Enviar Datos de Alumno/s', 'Rellenar y enviar datos de participante/s, se adjunta Ficha de inscripción', 3);
        $this->crearRequisito($empresaOrganizadora->id, 'curso_seleccionado', 'Seleccionar Curso/s', 'Seleccionar curso/s en el que desea matricularse', 4);
        $this->crearRequisito($empresaOrganizadora->id, 'confirmacion_fecha', 'Confirmar Fecha de Inicio', 'Confirmar la fecha propuesta para el inicio del curso', 5);

        // Requisitos para Empresa Externa (Tipo 2) - solo datos de alumno
        $this->crearRequisito($empresaExterna->id, 'datos_alumno', 'Enviar Datos de Alumno/s', 'Rellenar y enviar datos de participante/s, se adjunta Ficha de inscripción', 1);

        // Eliminar requisitos que ya no aplican para Empresa Externa
        $idsEliminar = TipoRequisito::where('tipo_candidato_id', $empresaExterna->id)
            ->whereNotIn('codigo', ['datos_alumno'])->pluck('id');
        \App\Models\RequisitoCandidato::whereIn('tipo_requisito_id', $idsEliminar)->delete();
        TipoRequisito::whereIn('id', $idsEliminar)->delete();

        // Requisitos para Particular (Tipo 3) - datos personales, fecha y pago
        $this->crearRequisito($particular->id, 'datos_personales', 'Datos Personales', 'Información personal completa del alumno', 1);
        $this->crearRequisito($particular->id, 'confirmacion_fecha', 'Confirmar Fecha de Inicio', 'Confirmar la fecha propuesta para el inicio del curso', 2);
        $this->crearRequisito($particular->id, 'pago_confirmado', 'Pago Confirmado', 'Confirmación del pago del curso', 3);

        // Eliminar requisitos que ya no aplican para Particular
        $idsEliminar = TipoRequisito::where('tipo_candidato_id', $particular->id)
            ->whereNotIn('codigo', ['datos_personales', 'confirmacion_fecha', 'pago_confirmado'])->pluck('id');
        \App\Models\RequisitoCandidato::whereIn('tipo_requisito_id', $idsEliminar)->delete();
        TipoRequisito::whereIn('id', $idsEliminar)->delete();

        $this->command->info('✅ Tipos de candidato y requisitos creados exitosamente');
    }

    private function crearRequisito($tipoCandidatoId, $codigo, $nombre, $descripcion, $orden, $obligatorio = true)
    {
        TipoRequisito::updateOrCreate(
            [
                'tipo_candidato_id' => $tipoCandidatoId,
                'codigo' => $codigo,
            ],
            [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'orden' => $orden,
                'obligatorio' => $obligatorio,
                'activo' => true,
            ]
        );
    }
}
