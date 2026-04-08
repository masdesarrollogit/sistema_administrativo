<?php

namespace App\Mail;

use App\Models\Alumno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CredencialesMoodleMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $courseUrl;
    public string $cursoNombre;
    public int $cursoHoras;
    public $fechaInicio;
    public $fechaFin;

    public function __construct(
        public Alumno $alumno,
        public string $username,
        public string $password,
        public int $moodleCourseId,
        string $cursoNombre = '',
        int $cursoHoras = 0,
        $fechaInicio = null,
        $fechaFin = null,
        public bool $esBonificado = true,
    ) {
        $publicUrl = rtrim(config('moodle.public_url', 'https://aula.1curso.com'), '/');
        $this->courseUrl = "{$publicUrl}/course/view.php?id={$moodleCourseId}";
        $this->cursoNombre = $cursoNombre;
        $this->cursoHoras = $cursoHoras;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('moodle.mail_from', 'info@aula.1curso.com'),
                'WebCurso'
            ),
            subject: "Credenciales de acceso - {$this->cursoNombre} - WebCurso",
            cc: [new Address('administracion@webcurso.es', 'Administración WebCurso')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credenciales-moodle',
        );
    }
}
