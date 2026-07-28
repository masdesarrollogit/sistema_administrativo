<?php

namespace App\Mail;

use App\Models\Alumno;
use App\Contracts\OrigenMatriculaMoodle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlumnoAptoSinExamenMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $courseUrl;
    public string $cursoNombre;

    public function __construct(
        public Alumno $alumno,
        public OrigenMatriculaMoodle $matricula,
        public ?float $notaTotal,
        public ?float $notaMax,
        public ?float $notaPorcentaje,
        public int $diasRestantes,
    ) {
        $publicUrl = rtrim(config('moodle.public_url', 'https://aula.1curso.com'), '/');
        $this->courseUrl = "{$publicUrl}/course/view.php?id={$matricula->moodleCourseIdMatricula()}";
        $this->cursoNombre = $matricula->accionFormativaMatricula()?->denominacion_limpia
            ?? ($matricula->accionFormativaMatricula()?->denominacion ?? 'tu curso');
    }

    public function envelope(): Envelope
    {
        $copiaAdmin = config('reportes_moodle.copia_admin_email', 'administracion@webcurso.es');

        return new Envelope(
            from: new Address(
                config('moodle.mail_from', 'tutorias@webcurso.es'),
                'WebCurso',
            ),
            subject: "🎯 Ya tienes los 50 puntos, solo te falta el cuestionario final · {$this->cursoNombre}",
            cc: [new Address($copiaAdmin, 'Administración WebCurso')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alumno-apto-sin-examen');
    }
}
