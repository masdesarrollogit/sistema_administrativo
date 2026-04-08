<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaldoBonificadoMensualMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombreParticipante;
    public string $cif;
    public string $razonSocial;
    public string $saldoFormateado;

    public function __construct(
        string $nombreParticipante,
        string $cif,
        string $razonSocial,
        string $saldoFormateado
    ) {
        $this->nombreParticipante = $nombreParticipante;
        $this->cif                = $cif;
        $this->razonSocial        = $razonSocial;
        $this->saldoFormateado    = $saldoFormateado;

        $this->mailer('saldos');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('saldoswebcurso@gmail.com', 'WebCurso'),
            replyTo: [
                new Address('administracion@webcurso.es', 'Administración WebCurso'),
            ],
            cc: [
                new Address('administracion@webcurso.es', 'Administración'),
                new Address('webcurso@webcurso.es', 'WebCurso'),
            ],
            subject: "{$this->razonSocial} - Saldo disponible para formación bonificada",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.saldo-bonificado-mensual',
            with: [
                'nombreParticipante' => $this->nombreParticipante,
                'cif'                => $this->cif,
                'razonSocial'        => $this->razonSocial,
                'saldoFormateado'    => $this->saldoFormateado,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
