<?php

namespace App\Mail;

use App\Models\Alumno;
use App\Models\GrupoFormativo;
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

    public function __construct(
        public GrupoFormativo $grupo,
        public Alumno $alumno,
        public string $username,
        public string $password,
        public int $moodleCourseId,
    ) {
        $publicUrl = rtrim(config('moodle.public_url', 'https://aula.1curso.com'), '/');
        $this->courseUrl = "{$publicUrl}/course/view.php?id={$moodleCourseId}";
    }

    public function envelope(): Envelope
    {
        $curso = $this->grupo->accionFormativa->denominacion_limpia;

        return new Envelope(
            from: new Address(
                config('moodle.mail_from', 'info@aula.1curso.com'),
                'WebCurso'
            ),
            subject: "Credenciales de acceso - {$curso} - WebCurso",
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
