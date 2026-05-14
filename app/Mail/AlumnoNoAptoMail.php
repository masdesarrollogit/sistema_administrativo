<?php

namespace App\Mail;

use App\Models\Alumno;
use App\Models\AlumnoNoApto;
use App\Models\GrupoFormativo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlumnoNoAptoMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $cursoNombre;
    public string $mailtoUrl;
    public string $adminEmail;
    public int $numOfrecimiento;
    public int $maxOfrecimientos;

    public function __construct(
        public Alumno $alumno,
        public GrupoFormativo $grupo,
        public AlumnoNoApto $noApto,
        int $numOfrecimiento,
    ) {
        $this->cursoNombre = $grupo->accionFormativa?->denominacion_limpia
            ?? ($grupo->accionFormativa?->denominacion ?? 'el curso');
        $this->adminEmail = config('reportes_moodle.copia_admin_email', 'administracion@webcurso.es');
        $this->numOfrecimiento = $numOfrecimiento;
        $this->maxOfrecimientos = (int) config('reportes_moodle.reporte_no_aptos.max_ofrecimientos', 4);

        // mailto: con asunto y body predefinidos para que admin lo identifique fácil.
        $asunto = "REINICIO-{$noApto->id} · Reinicio curso " . $this->cursoNombre;
        $body = "Hola,\n\n"
            . "Soy {$alumno->nombre_completo} (NIF: {$alumno->nif}).\n\n"
            . "Quiero reiniciar el curso \"{$this->cursoNombre}\" (Grupo " . ($grupo->id_grupo_fundae ?? $grupo->id) . ") "
            . "que no aprobé en su edición original (finalizó el {$grupo->fecha_fin?->format('d/m/Y')}).\n\n"
            . "Por favor, contactadme para coordinar el reinicio.\n\n"
            . "Saludos,\n{$alumno->nombre_completo}";

        $this->mailtoUrl = 'mailto:' . $this->adminEmail
            . '?subject=' . rawurlencode($asunto)
            . '&body=' . rawurlencode($body);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('moodle.mail_from', 'tutorias@webcurso.es'),
                'WebCurso',
            ),
            subject: "Una segunda oportunidad para terminar {$this->cursoNombre}",
            cc: [new Address($this->adminEmail, 'Administración WebCurso')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alumno-no-apto');
    }
}
