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
    /** @var array<int, string> */
    public array $emailsCc;

    public function __construct(
        string $nombreParticipante,
        string $cif,
        string $razonSocial,
        string $saldoFormateado,
        array $emailsCc = []
    ) {
        $this->nombreParticipante = $nombreParticipante;
        $this->cif                = $cif;
        $this->razonSocial        = $razonSocial;
        $this->saldoFormateado    = $saldoFormateado;
        $this->emailsCc           = array_values(array_filter($emailsCc));

        $this->mailer('saldos');
    }

    public function envelope(): Envelope
    {
        $cc = array_map(fn (string $email) => new Address($email), $this->emailsCc);

        return new Envelope(
            from: new Address('saldoswebcurso@gmail.com', 'WebCurso'),
            replyTo: [
                new Address('administracion@webcurso.es', 'Administración WebCurso'),
            ],
            cc: $cc,
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
