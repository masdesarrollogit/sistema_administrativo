<?php

namespace App\Mail;

use App\Models\Tutor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TutorReporteSemanalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{
     *     alumno_nombre:string,
     *     alumno_telefono:?string,
     *     curso:string,
     *     grupo:string,
     *     dias_desde_inicio:int,
     *     emails_enviados:int,
     * }> $noConectados
     * @param array<int, array{
     *     alumno_nombre:string,
     *     alumno_telefono:?string,
     *     curso:string,
     *     grupo:string,
     *     dias_inactivo:int,
     *     dias_restantes:int,
     *     nota_pct:?float,
     *     emails_enviados:int,
     * }> $inactivos
     * @param array<int, array{
     *     alumno_nombre:string,
     *     alumno_telefono:?string,
     *     curso:string,
     *     grupo:string,
     *     dias_restantes:int,
     *     nota_pct:?float,
     *     pct_tiempo_transcurrido:?float,
     *     emails_enviados:int,
     * }> $riesgoCritico
     * @param array<int, array{
     *     alumno_nombre:string,
     *     alumno_telefono:?string,
     *     curso:string,
     *     grupo:string,
     *     horas_restantes:int,
     *     nota_pct:?float,
     *     cuestionario_final_realizado:bool,
     *     emails_enviados:int,
     * }> $preCierre
     * @param array<int, array{
     *     alumno_nombre:string,
     *     alumno_telefono:?string,
     *     curso:string,
     *     grupo:string,
     *     dias_restantes:int,
     *     nota_pct:?float,
     *     emails_enviados:int,
     * }> $aptoSinExamen
     * @param array<int, array{
     *     alumno_nombre:string,
     *     curso:string,
     *     grupo:string,
     *     nota_pct:?float,
     *     emails_enviados:int,
     * }> $aprobados
     */
    public function __construct(
        public Tutor $tutor,
        public array $noConectados,
        public array $inactivos,
        public array $riesgoCritico,
        public array $preCierre,
        public array $aptoSinExamen,
        public array $aprobados,
        public string $semanaEtiqueta,
    ) {
    }

    public function envelope(): Envelope
    {
        $copiaAdmin = config('reportes_moodle.copia_admin_email', 'administracion@webcurso.es');
        $totalAlumnos = count($this->noConectados) + count($this->inactivos) + count($this->riesgoCritico) + count($this->preCierre) + count($this->aptoSinExamen) + count($this->aprobados);

        return new Envelope(
            from: new Address(
                config('moodle.mail_from', 'tutorias@webcurso.es'),
                'WebCurso',
            ),
            subject: "Reporte semanal de alumnos — {$totalAlumnos} requieren atención ({$this->semanaEtiqueta})",
            cc: [new Address($copiaAdmin, 'Administración WebCurso')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tutor-reporte-semanal');
    }
}
